<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$reference=clean($_POST['reference_number']??'');
$token=clean($_POST['tracking_token']??'');
$message=trim((string)($_POST['message']??''));

if($reference===''||$token===''||$message===''){
    jsonResponse(false,'Reference number, private tracking token, and message are required.');
}
if(mb_strlen($message)>5000)jsonResponse(false,'Follow-up message is too long.');

try{
    $pdo=db();
    $tracked=cefPublicTrack($pdo,$reference,$token);
    if(!$tracked)jsonResponse(false,'Reference number or private tracking token is incorrect.',[],404);

    $s=$tracked['submission'];
    if(in_array($s['status'],['Closed','Rejected','Duplicate','Withdrawn'],true)){
        jsonResponse(false,'This citizen record is closed and no longer accepts public follow-up.');
    }

    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO cef_followups
         (submission_id,response_id,direction,sender_user_id,sender_name,message,public_visible,created_at)
         VALUES(:submission,NULL,"Citizen to Council",NULL,"Citizen",:message,1,NOW())'
    )->execute([':submission'=>$s['id'],':message'=>$message]);

    $newStatus=$s['status'];
    if($s['status']==='Awaiting Citizen'){
        $newStatus=$s['moderation_status']==='Needs Clarification'
            ? 'Under Moderation'
            : 'In Progress';
    }
    if($newStatus!==$s['status']){
        $pdo->prepare(
            'UPDATE cef_submissions SET status=:status,updated_at=NOW() WHERE id=:id'
        )->execute([':status'=>$newStatus,':id'=>$s['id']]);
    }else{
        $pdo->prepare('UPDATE cef_submissions SET updated_at=NOW() WHERE id=:id')
            ->execute([':id'=>$s['id']]);
    }

    cefSubmissionHistory(
        $pdo,(int)$s['id'],'Citizen Follow-Up',$s['status'],$newStatus,
        'Citizen supplied additional information through secure tracking.',true,null
    );

    cepfmsLogActivity(null,'CEPFMS Citizen Follow-Up',$reference.' · secure tracking follow-up received.');
    $pdo->commit();

    jsonResponse(true,'Your follow-up was received.');
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to submit follow-up.',[],500);
}
