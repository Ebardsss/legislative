<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);
$reviewId=(int)($_POST['review_id']??0);
$decision=clean($_POST['decision']??'');
$notes=trim((string)($_POST['notes']??''));
$duplicateId=(int)($_POST['duplicate_of_submission_id']??0)?:null;

$allowed=['Validate','Needs Clarification','Reject','Duplicate'];
if(!in_array($decision,$allowed,true))jsonResponse(false,'Invalid moderation decision.');

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare(
        'SELECT * FROM cef_submissions
         WHERE id=:id AND deleted_at IS NULL
         FOR UPDATE'
    );
    $q->execute([':id'=>$submissionId]);$s=$q->fetch();
    if(!$s){$pdo->rollBack();jsonResponse(false,'Submission not found.');}

    $r=$pdo->prepare(
        'SELECT * FROM cef_moderation_reviews
         WHERE id=:review AND submission_id=:submission
         FOR UPDATE'
    );
    $r->execute([':review'=>$reviewId,':submission'=>$submissionId]);
    $review=$r->fetch();
    if(!$review){$pdo->rollBack();jsonResponse(false,'Moderation review not found.');}

    $old=$s['status'];$new=$old;$moderation=$s['moderation_status'];
    $reviewDecision='Pending';

    if($decision==='Validate'){
        $selectedCatId = (int)($_POST['category_id'] ?? 0);
        if($selectedCatId > 0) {
            $pdo->prepare('UPDATE cef_submissions SET category_id = :cat WHERE id = :id')->execute([':cat'=>$selectedCatId, ':id'=>$submissionId]);
            $s['category_id'] = $selectedCatId;
        }
        if(empty($s['category_id'])){
            $pdo->rollBack();
            jsonResponse(false,'Choose a citizen-engagement category before validation.');
        }
        if(!cefAllChecklistPassed($pdo,$reviewId)){
            $pdo->rollBack();
            jsonResponse(false,'Every moderation checklist item must be Pass before validation.');
        }
        $new='Validated';$moderation='Validated';$reviewDecision='Validated';
    }elseif($decision==='Needs Clarification'){
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Clarification request details are required.');}
        $new='Awaiting Citizen';$moderation='Needs Clarification';$reviewDecision='Needs Clarification';
    }elseif($decision==='Reject'){
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Rejection reason is required.');}
        $new='Rejected';$moderation='Rejected';$reviewDecision='Rejected';
    }elseif($decision==='Duplicate'){
        if(!$duplicateId){$pdo->rollBack();jsonResponse(false,'Choose the existing submission that this record duplicates.');}
        if($duplicateId===$submissionId){$pdo->rollBack();jsonResponse(false,'A submission cannot duplicate itself.');}

        $dq=$pdo->prepare(
            'SELECT id,reference_number,title
             FROM cef_submissions
             WHERE id=:id AND deleted_at IS NULL'
        );
        $dq->execute([':id'=>$duplicateId]);$existing=$dq->fetch();
        if(!$existing){$pdo->rollBack();jsonResponse(false,'Selected duplicate target was not found.');}

        $new='Duplicate';$moderation='Duplicate';$reviewDecision='Duplicate';

        $pdo->prepare(
            'INSERT INTO cef_duplicate_matches
             (submission_id,matched_submission_id,similarity_score,match_source,status,
              reviewed_by,reviewed_at,created_at)
             VALUES(:submission,:matched,NULL,"Manual","Confirmed",:user,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
               status="Confirmed",reviewed_by=VALUES(reviewed_by),reviewed_at=NOW()'
        )->execute([
            ':submission'=>$submissionId,':matched'=>$duplicateId,
            ':user'=>currentUserId()
        ]);

        $notes=trim($notes.' Duplicate of '.$existing['reference_number'].' · '.$existing['title']);
    }

    $pdo->prepare(
        'UPDATE cef_moderation_reviews
         SET reviewer_id=:user,decision=:decision,duplicate_of_submission_id=:duplicate,
             notes=:notes,reviewed_at=NOW(),updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':user'=>currentUserId(),':decision'=>$reviewDecision,
        ':duplicate'=>$duplicateId,':notes'=>$notes?:null,':id'=>$reviewId
    ]);

    $pdo->prepare(
        'UPDATE cef_submissions
         SET status=:status,moderation_status=:moderation,updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status'=>$new,':moderation'=>$moderation,':id'=>$submissionId
    ]);

    if($decision==='Validate'&&!empty($s['category_id'])){
        $route=$pdo->prepare(
            'SELECT default_office_id,default_committee_id
             FROM cef_categories
             WHERE id=:id
             LIMIT 1'
        );
        $route->execute([':id'=>$s['category_id']]);
        $defaultRoute=$route->fetch()?:[];

        $officeId=(int)($defaultRoute['default_office_id']??0)?:null;
        $committeeId=(int)($defaultRoute['default_committee_id']??0)?:null;

        if($officeId||$committeeId){
            cefCreateOrUpdatePrimaryAssignment(
                $pdo,$submissionId,$officeId,$committeeId,null,null,
                'Automatically routed from the validated citizen-engagement category.'
            );

            $new='Assigned';
            $pdo->prepare(
                'UPDATE cef_submissions
                 SET status="Assigned",updated_at=NOW()
                 WHERE id=:id'
            )->execute([':id'=>$submissionId]);
        }
    }

    cefSubmissionHistory(
        $pdo,$submissionId,'Moderation Decision',$old,$new,
        $notes?:(
            'Moderation decision: '.$reviewDecision.'.'.
            ($decision==='Validate'&&$new==='Assigned'
                ? ' Default category routing created a primary assignment.'
                : '')
        ),true
    );

    cepfmsLogActivity(
        currentUserId(),'CEPFMS Moderation Decision',
        "{$s['reference_number']} · {$reviewDecision}."
    );

    $pdo->commit();
    jsonResponse(true,'Moderation decision saved.',['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save moderation decision.');
}
