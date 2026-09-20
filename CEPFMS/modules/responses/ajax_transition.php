<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$responseId=(int)($_POST['response_id']??0);
$action=clean($_POST['action']??'');$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare(
        'SELECT r.*,s.reference_number submission_reference,s.status submission_status,
                s.moderation_status
         FROM cef_responses r
         JOIN cef_submissions s ON s.id=r.submission_id
         WHERE r.id=:id
         FOR UPDATE'
    );
    $q->execute([':id'=>$responseId]);$r=$q->fetch();
    if(!$r){$pdo->rollBack();jsonResponse(false,'Response not found.');}

    $old=$r['status'];$new=$old;

    if($action==='submit_review'){
        if(!in_array($old,['Draft','Returned'],true)){$pdo->rollBack();jsonResponse(false,'Only a draft can be submitted for review.');}
        $new='Under Review';
        $pdo->prepare('UPDATE cef_responses SET status="Under Review",updated_at=NOW() WHERE id=:id')->execute([':id'=>$responseId]);

    }elseif($action==='return_draft'){
        if($old!=='Under Review'){$pdo->rollBack();jsonResponse(false,'Only an Under Review response can be returned.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Return reason is required.');}
        $new='Returned';
        $pdo->prepare(
            'UPDATE cef_responses
             SET status="Returned",reviewed_by=:user,reviewed_at=NOW(),approved_by=NULL,approved_at=NULL,updated_at=NOW()
             WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$responseId]);

    }elseif($action==='approve'){
        if(!cefHasPermission('cepfms.responses.approve')){$pdo->rollBack();jsonResponse(false,'You do not have permission to approve an official citizen response.');}
        if($old!=='Under Review'){$pdo->rollBack();jsonResponse(false,'Response must be Under Review before approval.');}
        $new='Approved';
        $pdo->prepare(
            'UPDATE cef_responses
             SET status="Approved",reviewed_by=COALESCE(reviewed_by,:reviewer),
                 reviewed_at=COALESCE(reviewed_at,NOW()),
                 approved_by=:approver,approved_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':reviewer'=>currentUserId(),':approver'=>currentUserId(),':id'=>$responseId
        ]);

    }elseif($action==='deliver'){
        if($old!=='Approved'){$pdo->rollBack();jsonResponse(false,'Only an Approved response can be released to the citizen.');}

        $recipients=cefNotificationRecipientsForSubmission($pdo,(int)$r['submission_id']);
        $notificationId=cefQueueNotification(
            $pdo,(int)$r['submission_id'],$responseId,'Citizen Response',
            $r['subject'],$r['body'],$recipients,null
        );

        $new='Delivered';
        $pdo->prepare(
            'UPDATE cef_responses
             SET status="Delivered",delivery_channel="Secure Portal + Preferred Notification",
                 delivered_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':id'=>$responseId]);

        if(in_array($r['submission_status'],['Resolved','Closed'],true)){
            $submissionNew=$r['submission_status'];
        }elseif($r['response_type']==='Clarification Request'){
            $submissionNew='Awaiting Citizen';
        }else{
            $submissionNew='Responded';
        }

        $pdo->prepare(
            'UPDATE cef_submissions
             SET status=:status,updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$submissionNew,':id'=>$r['submission_id']]);

        cefSubmissionHistory(
            $pdo,(int)$r['submission_id'],'Official Response Released',
            $r['submission_status'],$submissionNew,
            'Approved official response '.$r['response_reference'].' is available in secure citizen tracking.',
            true
        );

        cefResponseHistory(
            $pdo,$responseId,'Deliver',$old,$new,
            'Published to secure citizen tracking and queued notification #'.$notificationId.'.'
        );

        $pdo->commit();

        $pdo->beginTransaction();
        $delivery=cefProcessNotification($pdo,$notificationId);
        $pdo->commit();

        cepfmsLogActivity(
            currentUserId(),'CEPFMS Response Delivered',
            "{$r['response_reference']} · {$r['submission_reference']} · notification #{$notificationId}."
        );

        jsonResponse(
            true,
            $delivery['pending_external']>0
                ? 'Response published to secure citizen tracking. External email/phone notification remains pending until a provider is configured.'
                : 'Response published and notification processed.',
            ['notification_id'=>$notificationId,'delivery'=>$delivery]
        );

    }elseif($action==='cancel'){
        if(in_array($old,['Delivered','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Response cannot be cancelled.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
        $new='Cancelled';
        $pdo->prepare('UPDATE cef_responses SET status="Cancelled",updated_at=NOW() WHERE id=:id')->execute([':id'=>$responseId]);

    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid response action.');
    }

    cefResponseHistory($pdo,$responseId,ucwords(str_replace('_',' ',$action)),$old,$new,$notes);
    cepfmsLogActivity(currentUserId(),'CEPFMS Response '.ucwords(str_replace('_',' ',$action)),"{$r['response_reference']} · {$old} -> {$new}.");
    $pdo->commit();
    jsonResponse(true,"Response is now {$new}.",['status'=>$new]);

}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update response workflow.');
}
