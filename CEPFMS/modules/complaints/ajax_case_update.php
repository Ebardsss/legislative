<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);$assignmentId=(int)($_POST['assignment_id']??0)?:null;
$type=clean($_POST['update_type']??'Progress Update');$text=trim((string)($_POST['update_text']??''));
$public=!empty($_POST['public_visible']);
if($text==='')jsonResponse(false,'Update details are required.');

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s||$s['submission_type']!=='Complaint')jsonResponse(false,'Complaint not found.');
    $pdo->prepare(
        'INSERT INTO cef_case_updates
         (submission_id,assignment_id,update_type,update_text,public_visible,created_by,created_at)
         VALUES(:submission,:assignment,:type,:text,:public,:user,NOW())'
    )->execute([
        ':submission'=>$submissionId,':assignment'=>$assignmentId,':type'=>$type,
        ':text'=>$text,':public'=>$public?1:0,':user'=>currentUserId()
    ]);
    cefSubmissionHistory($pdo,$submissionId,$type,$s['status'],$s['status'],$text,$public);
    cepfmsLogActivity(currentUserId(),'CEPFMS Complaint Update',"{$s['reference_number']} · {$type}.");
    jsonResponse(true,'Case update added.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save case update.');
}
