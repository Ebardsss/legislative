<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['submission_id']??0);
$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM cef_submissions WHERE id=:id AND submission_type="Feedback" AND deleted_at IS NULL FOR UPDATE');
    $q->execute([':id'=>$id]);
    $s=$q->fetch();
    if(!$s){
        $pdo->rollBack();
        jsonResponse(false,'Feedback record not found.');
    }
    $old=$s['status'];
    $new=$old;
    $public=true;

    if($action==='start'){
        if($s['moderation_status']!=='Validated'&&!in_array($old,['Validated','Submitted','Assigned'],true)){
            $pdo->rollBack();
            jsonResponse(false,'Feedback must first be validated in Moderation & Validation.');
        }
        $new='In Progress';
        $pdo->prepare('UPDATE cef_submissions SET status="In Progress",updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }elseif($action==='resolve'){
        if(!in_array($old,['Validated','Assigned','In Progress','Responded','Awaiting Citizen','Submitted'],true)){
            $pdo->rollBack();
            jsonResponse(false,'Feedback cannot be resolved from its current status.');
        }
        $new='Resolved';
        $pdo->prepare('UPDATE cef_submissions SET status="Resolved",closed_at=NOW(),updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }elseif($action==='close'){
        $new='Closed';
        $pdo->prepare('UPDATE cef_submissions SET status="Closed",closed_at=NOW(),updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }elseif($action==='reopen'){
        if(!in_array($old,['Resolved','Closed'],true)){
            $pdo->rollBack();
            jsonResponse(false,'Only resolved or closed feedback can be reopened.');
        }
        $new='In Progress';
        $pdo->prepare('UPDATE cef_submissions SET status="In Progress",closed_at=NULL,updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }else{
        $pdo->rollBack();
        jsonResponse(false,'Invalid feedback workflow action.');
    }

    $actionLabel = match($action) {
        'start' => 'Processing Started',
        'resolve' => 'Feedback Resolved',
        'close' => 'Feedback Closed',
        'reopen' => 'Feedback Reopened',
        default => ucwords(str_replace('_',' ',$action))
    };

    cefSubmissionHistory(
        $pdo,$id,'Feedback '.$actionLabel,$old,$new,
        $notes?:($action==='close'?'Feedback ticket concluded and ticket conversation closed.':cefPublicStatus($new)),
        $public
    );
    cepfmsLogActivity(
        currentUserId(),'CEPFMS Feedback '.$actionLabel,
        "{$s['reference_number']} · {$old} -> {$new}."
    );
    $pdo->commit();
    jsonResponse(
        true,
        "Feedback is now {$new}." . (in_array($new,['Closed','Resolved'],true) ? ' Ticket conversation is now closed.' : ''),
        ['status'=>$new]
    );
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update feedback workflow.');
}
