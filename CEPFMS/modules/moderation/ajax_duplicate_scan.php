<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);

function cefSimilarityText(string $a,string $b): float
{
    $normalize=static function(string $text): string {
        $text=mb_strtolower($text);
        $text=preg_replace('/[^\pL\pN\s]+/u',' ',$text)??$text;
        $text=preg_replace('/\s+/u',' ',trim($text))??trim($text);
        return mb_substr($text,0,4000);
    };
    $a=$normalize($a);$b=$normalize($b);
    if($a===''||$b==='')return 0.0;
    similar_text($a,$b,$percent);
    return round((float)$percent,3);
}

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s)jsonResponse(false,'Submission not found.');

    $q=$pdo->prepare(
        'SELECT id,reference_number,title,summary,details,submission_type,status,created_at
         FROM cef_submissions
         WHERE id<>:id
           AND submission_type=:type
           AND deleted_at IS NULL
         ORDER BY created_at DESC
         LIMIT 200'
    );
    $q->execute([':id'=>$submissionId,':type'=>$s['submission_type']]);
    $candidates=$q->fetchAll();

    $base=$s['title'].' '.($s['summary']??'').' '.$s['details'];
    $matches=[];

    foreach($candidates as $candidate){
        $candidateText=$candidate['title'].' '.($candidate['summary']??'').' '.$candidate['details'];
        $score=cefSimilarityText($base,$candidateText);
        if($score<45.0)continue;

        $pdo->prepare(
            'INSERT INTO cef_duplicate_matches
             (submission_id,matched_submission_id,similarity_score,match_source,status,created_at)
             VALUES(:submission,:matched,:score,"Text Similarity","Suggested",NOW())
             ON DUPLICATE KEY UPDATE
               similarity_score=VALUES(similarity_score),
               match_source="Text Similarity",
               status=CASE WHEN status="Confirmed" THEN status ELSE "Suggested" END'
        )->execute([
            ':submission'=>$submissionId,
            ':matched'=>$candidate['id'],
            ':score'=>$score
        ]);

        $matches[]=[
            'id'=>(int)$candidate['id'],
            'reference_number'=>$candidate['reference_number'],
            'title'=>$candidate['title'],
            'status'=>$candidate['status'],
            'score'=>$score,
        ];
    }

    usort($matches,fn($a,$b)=>$b['score']<=>$a['score']);
    $matches=array_slice($matches,0,10);

    // AI Semantic Duplicate Evaluation (if requested or if lexical matches are low)
    if(!empty($_POST['ai_scan']) && count($matches) < 5){
        try{
            $ai = cepfmsAiService();
            $semQ = $pdo->prepare(
                'SELECT id, reference_number, title, summary, details, status
                 FROM cef_submissions
                 WHERE id <> :id
                   AND submission_type = :type
                   AND (category_id = :cat OR (barangay IS NOT NULL AND barangay = :brgy))
                   AND deleted_at IS NULL
                 ORDER BY created_at DESC LIMIT 5'
            );
            $semQ->execute([
                ':id' => $submissionId,
                ':type' => $s['submission_type'],
                ':cat' => $s['category_id'] ?? 0,
                ':brgy' => $s['barangay'] ?? '',
            ]);
            $existingIds = array_column($matches, 'id');
            foreach($semQ->fetchAll() as $cand){
                if(in_array((int)$cand['id'], $existingIds, true)) continue;
                $candText = $cand['title'] . "\n" . ($cand['summary'] ?? '') . "\n" . $cand['details'];
                $semRes = $ai->checkSemanticDuplicate($base, $candText);
                if(!empty($semRes['is_duplicate']) || ($semRes['similarity_score'] ?? 0) >= 50){
                    $score = (float)($semRes['similarity_score'] ?? 50);
                    $pdo->prepare(
                        'INSERT INTO cef_duplicate_matches
                         (submission_id,matched_submission_id,similarity_score,match_source,status,created_at)
                         VALUES(:submission,:matched,:score,"Ollama Semantic AI","Suggested",NOW())
                         ON DUPLICATE KEY UPDATE
                           similarity_score=VALUES(similarity_score),
                           match_source="Ollama Semantic AI",
                           status=CASE WHEN status="Confirmed" THEN status ELSE "Suggested" END'
                    )->execute([
                        ':submission' => $submissionId,
                        ':matched' => $cand['id'],
                        ':score' => $score
                    ]);
                    $matches[] = [
                        'id' => (int)$cand['id'],
                        'reference_number' => $cand['reference_number'],
                        'title' => $cand['title'],
                        'status' => $cand['status'],
                        'score' => $score,
                        'source' => 'Ollama Semantic AI',
                    ];
                }
            }
            usort($matches,fn($a,$b)=>$b['score']<=>$a['score']);
            $matches=array_slice($matches,0,10);
        }catch(Throwable $aiEx){
            // Soft fail on AI error
        }
    }

    cepfmsLogActivity(
        currentUserId(),'CEPFMS Duplicate Scan',
        "{$s['reference_number']} · ".count($matches).' potential match(es).'
    );

    jsonResponse(true,'Duplicate scan completed.',['matches'=>$matches]);
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to scan for duplicates.');
}
