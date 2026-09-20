<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.notifications.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$submissionId=(int)($_POST['submission_id']??0);
$type=clean($_POST['notification_type']??'Status Update');
$subject=clean($_POST['subject']??'');
$message=trim((string)($_POST['message']??''));
$scheduled=clean($_POST['scheduled_at']??'')?:null;

if($subject===''||$message==='')jsonResponse(false,'Subject and message are required.');
if($scheduled&&strtotime($scheduled)===false)jsonResponse(false,'Invalid notification schedule.');

try{
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s)jsonResponse(false,'Citizen submission not found.');
    if(in_array($s['status'],['Rejected','Duplicate','Withdrawn','Closed'],true)){
        jsonResponse(false,'Closed or rejected citizen records cannot receive a new manual notice.');
    }

    $recipients=cefNotificationRecipientsForSubmission($pdo,$submissionId);
    $notificationId=cefQueueNotification(
        $pdo,$submissionId,null,$type,$subject,$message,$recipients,$scheduled
    );

    cefSubmissionHistory(
        $pdo,$submissionId,'Citizen Notice Queued',
        $s['status'],$s['status'],
        $subject,
        true
    );

    cepfmsLogActivity(
        currentUserId(),'CEPFMS Citizen Notice Queued',
        "{$s['reference_number']} · notification #{$notificationId}."
    );

    jsonResponse(true,'Citizen notice queued.',['notification_id'=>$notificationId]);
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to queue citizen notice.');
}
