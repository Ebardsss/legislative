<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);
$assignment=(int)($_POST['assignment_id']??0)?:null;
$level=clean($_POST['escalation_level']??'Attention');
$reason=trim((string)($_POST['reason']??''));
$user=(int)($_POST['escalated_to_user_id']??0)?:null;
$office=(int)($_POST['escalated_to_office_id']??0)?:null;
if($reason==='')jsonResponse(false,'Escalation reason is required.');
if(!in_array($level,['Attention','High','Urgent','Executive Review'],true))jsonResponse(false,'Invalid escalation level.');

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s||$s['submission_type']!=='Complaint')jsonResponse(false,'Complaint not found.');

    $pdo->prepare(
        'INSERT INTO cef_escalations
         (submission_id,assignment_id,escalation_level,reason,status,
          escalated_to_user_id,escalated_to_office_id,created_by,created_at)
         VALUES(:submission,:assignment,:level,:reason,"Open",:user,:office,:created,NOW())'
    )->execute([
        ':submission'=>$submissionId,':assignment'=>$assignment,':level'=>$level,
        ':reason'=>$reason,':user'=>$user,':office'=>$office,':created'=>currentUserId()
    ]);

    cefSubmissionHistory($pdo,$submissionId,'Complaint Escalated',$s['status'],$s['status'],$level.': '.$reason,true);
    cepfmsLogActivity(currentUserId(),'CEPFMS Complaint Escalation',"{$s['reference_number']} · {$level}.");
    jsonResponse(true,'Complaint escalation recorded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to escalate complaint.');
}
