<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$submissionId=(int)($_POST['submission_id']??0);
$message=trim((string)($_POST['message']??''));

if($message==='')jsonResponse(false,'Message is required.');
if(mb_strlen($message)>5000)jsonResponse(false,'Message is too long. Maximum is 5,000 characters.');

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s)jsonResponse(false,'Ticket not found.',[],404);

    $permission=cefTicketManagePermission((string)$s['submission_type']);
    if(!cefHasPermission($permission) && !cefHasPermission('cepfms.responses.manage')){
        jsonResponse(false,'You do not have permission to reply to this ticket.',[],403);
    }

    if(cefTicketChatClosed((string)$s['status'])){
        jsonResponse(false,'This ticket is '.$s['status'].'. The conversation is read-only.');
    }

    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO cef_followups
         (submission_id,response_id,direction,sender_user_id,sender_name,
          message,public_visible,created_at)
         VALUES(:submission,NULL,"Council to Citizen",:user,:name,
          :message,1,NOW())'
    )->execute([
        ':submission'=>$submissionId,
        ':user'=>currentUserId(),
        ':name'=>currentUserName(),
        ':message'=>$message,
    ]);

    cefSubmissionHistory(
        $pdo,
        $submissionId,
        'Ticket Conversation',
        $s['status'],
        $s['status'],
        'CEPFMS staff sent a message to the citizen inside the ticket conversation.',
        true
    );

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS Ticket Conversation',
        $s['reference_number'].' · staff message sent.'
    );

    $pdo->commit();
    jsonResponse(true,'Message sent.');
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to send ticket message.',[],500);
}
