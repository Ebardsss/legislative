<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$submissionId=(int)($_POST['submission_id']??0);
$closingNote=trim((string)($_POST['closing_note']??''));

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s)jsonResponse(false,'Ticket not found.',[],404);

    $permission=cefTicketManagePermission((string)$s['submission_type']);
    if(!cefHasPermission($permission)&&!cefHasPermission('cepfms.responses.manage')){
        jsonResponse(false,'You do not have permission to manage this ticket.',[],403);
    }

    if(cefTicketChatClosed((string)$s['status'])){
        jsonResponse(false,'This ticket conversation is already concluded (Status: '.$s['status'].').');
    }

    $pdo->beginTransaction();

    $previousStatus=(string)$s['status'];
    $newStatus=in_array($_POST['target_status'] ?? '', ['Resolved', 'Closed'], true) ? (string)$_POST['target_status'] : 'Closed';

    $pdo->prepare(
        'UPDATE cef_submissions
         SET status=:status,closed_at=COALESCE(closed_at,NOW()),updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status'=>$newStatus,
        ':id'=>$submissionId,
    ]);

    $message="Official Notice: Council staff has concluded this ticket conversation and marked the record as {$newStatus}.";
    if($closingNote!==''){
        $message.="\n\nClosing Remarks: ".$closingNote;
    }

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
        'Conversation Concluded',
        $previousStatus,
        $newStatus,
        'Staff concluded the ticket conversation and marked the record as Resolved.'.($closingNote!==''?' Reason: '.$closingNote:''),
        true
    );

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS Ticket Conversation Concluded',
        $s['reference_number'].' · conversation concluded by staff.'
    );

    $pdo->commit();
    jsonResponse(true,'Conversation concluded and ticket marked as Resolved.');
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to conclude conversation.',[],500);
}
