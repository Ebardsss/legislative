<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare(
        'SELECT * FROM cef_submissions
         WHERE id=:id AND deleted_at IS NULL
         FOR UPDATE'
    );
    $q->execute([':id'=>$submissionId]);
    $s=$q->fetch();
    if(!$s){$pdo->rollBack();jsonResponse(false,'Submission not found.');}
    if(in_array($s['status'],['Rejected','Duplicate','Withdrawn','Closed'],true)){
        $pdo->rollBack();jsonResponse(false,'This submission is already closed from moderation.');
    }

    $review=cefEnsureModerationReview($pdo,$submissionId);

    $newStatus=$s['status']==='Submitted'?'Under Moderation':$s['status'];
    $pdo->prepare(
        'UPDATE cef_submissions
         SET moderation_status="Under Review",status=:status,updated_at=NOW()
         WHERE id=:id'
    )->execute([':status'=>$newStatus,':id'=>$submissionId]);

    cefSubmissionHistory(
        $pdo,$submissionId,'Moderation Started',$s['status'],$newStatus,
        'Submission entered formal moderation and validation review.',true
    );
    cepfmsLogActivity(
        currentUserId(),'CEPFMS Moderation Start',
        "{$s['reference_number']} · review round {$review['review_round']}."
    );

    $pdo->commit();
    jsonResponse(true,'Moderation review started.',['review_id'=>(int)$review['id']]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to start moderation.');
}
