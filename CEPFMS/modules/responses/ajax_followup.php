<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);
$responseId=(int)($_POST['response_id']??0)?:null;
$message=trim((string)($_POST['message']??''));
$public=!empty($_POST['public_visible']);
if($message==='')jsonResponse(false,'Follow-up message is required.');

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s)jsonResponse(false,'Submission not found.');
    if(cefTicketChatClosed((string)$s['status'])){
        jsonResponse(false,'This ticket is '.$s['status'].'. The conversation is read-only.');
    }

    $pdo->prepare(
        'INSERT INTO cef_followups
         (submission_id,response_id,direction,sender_user_id,sender_name,message,public_visible,created_at)
         VALUES(:submission,:response,"Council to Citizen",:user,:name,:message,:public,NOW())'
    )->execute([
        ':submission'=>$submissionId,':response'=>$responseId,
        ':user'=>currentUserId(),':name'=>currentUserName(),
        ':message'=>$message,':public'=>$public?1:0
    ]);

    cefSubmissionHistory(
        $pdo,$submissionId,'Council Follow-Up',$s['status'],$s['status'],
        $message,$public
    );
    cepfmsLogActivity(currentUserId(),'CEPFMS Council Follow-Up',$s['reference_number'].'.');
    jsonResponse(true,'Follow-up recorded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save follow-up.');
}
