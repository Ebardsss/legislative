<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('modules/engagement/index.php'));
requireCsrf();

$pdo=db();
$submissionId=(int)($_POST['submission_id']??0);
$message=trim((string)($_POST['message']??''));

if($message===''){
    setFlash('danger','Message is required.');
    redirect(appUrl('modules/engagement/view.php?id='.$submissionId));
}

if(mb_strlen($message)>5000){
    setFlash('danger','Message is too long. Maximum is 5,000 characters.');
    redirect(appUrl('modules/engagement/view.php?id='.$submissionId));
}

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare(
        'SELECT id,reference_number,status,moderation_status
         FROM cef_submissions
         WHERE id=:submission
           AND citizen_user_id=:user
           AND deleted_at IS NULL
         LIMIT 1
         FOR UPDATE'
    );
    $q->execute([
        ':submission'=>$submissionId,
        ':user'=>currentUserId(),
    ]);
    $submission=$q->fetch();

    if(!$submission)throw new RuntimeException('Submission not found.');

    if(citizenTicketChatClosed((string)$submission['status'])){
        throw new RuntimeException('This ticket is '.$submission['status'].'. The conversation is read-only.');
    }

    $pdo->prepare(
        'INSERT INTO cef_followups
         (submission_id,response_id,direction,sender_user_id,sender_name,
          message,public_visible,created_at)
         VALUES(:submission,NULL,"Citizen to Council",:user,:sender_name,
          :message,1,NOW())'
    )->execute([
        ':submission'=>$submissionId,
        ':user'=>currentUserId(),
        ':sender_name'=>currentUser()['full_name'],
        ':message'=>$message,
    ]);

    $oldStatus=(string)$submission['status'];
    $newStatus=$oldStatus;

    if($oldStatus==='Awaiting Citizen'){
        $newStatus=$submission['moderation_status']==='Needs Clarification'
            ? 'Under Moderation'
            : 'In Progress';

        $pdo->prepare(
            'UPDATE cef_submissions
             SET status=:status,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':status'=>$newStatus,
            ':id'=>$submissionId,
        ]);
    }else{
        $pdo->prepare(
            'UPDATE cef_submissions
             SET updated_at=NOW()
             WHERE id=:id'
        )->execute([':id'=>$submissionId]);
    }

    citizenCefHistory(
        $pdo,
        $submissionId,
        'Ticket Conversation',
        $oldStatus,
        $newStatus,
        'Citizen sent a message inside the ticket conversation.',
        true,
        currentUserId()
    );

    portalLog(
        currentUserId(),
        'Citizen Ticket Conversation',
        $submission['reference_number'].' · citizen message sent.'
    );

    $pdo->commit();
    setFlash('success','Your message was sent.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();

    $safe=str_starts_with($e->getMessage(),'This ticket is')
        ? $e->getMessage()
        : (APP_DEBUG?$e->getMessage():'Unable to send message.');

    setFlash('danger',$safe);
}

redirect(appUrl('modules/engagement/view.php?id='.$submissionId));
