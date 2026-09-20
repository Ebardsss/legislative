<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);
$office=(int)($_POST['office_id']??0)?:null;$committee=(int)($_POST['committee_id']??0)?:null;
$user=(int)($_POST['assigned_user_id']??0)?:null;$due=clean($_POST['due_at']??'')?:null;
if($due&&strtotime($due)!==false)$due=date('Y-m-d H:i:s',strtotime($due));
$notes=trim((string)($_POST['notes']??''));
if(!$office&&!$committee&&!$user)jsonResponse(false,'Assign at least one responsible office, committee, or staff user.');
if($due&&strtotime($due)===false)jsonResponse(false,'Invalid assignment due date.');

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM cef_submissions WHERE id=:id AND submission_type="Complaint" AND deleted_at IS NULL FOR UPDATE');
    $q->execute([':id'=>$submissionId]);$s=$q->fetch();
    if(!$s){$pdo->rollBack();jsonResponse(false,'Complaint not found.');}
    if($s['moderation_status']!=='Validated'){
        $pdo->rollBack();
        jsonResponse(false,'Complaint must first be validated in Moderation & Validation before assignment.');
    }
    if(in_array($s['status'],['Resolved','Closed','Rejected','Withdrawn'],true)){$pdo->rollBack();jsonResponse(false,'Closed complaint cannot be reassigned.');}

    $assignmentId=cefCreateOrUpdatePrimaryAssignment($pdo,$submissionId,$office,$committee,$user,$due,$notes);
    $newStatus=in_array($s['status'],['Submitted','Under Moderation','Validated'],true)?'Assigned':$s['status'];

    $pdo->prepare('UPDATE cef_submissions SET status=:status,updated_at=NOW() WHERE id=:id')
        ->execute([':status'=>$newStatus,':id'=>$submissionId]);

    $pdo->prepare(
        'INSERT INTO cef_assignment_history
         (assignment_id,submission_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:assignment,:submission,"Assign",NULL,"Assigned",:details,:user,NOW())'
    )->execute([
        ':assignment'=>$assignmentId,':submission'=>$submissionId,
        ':details'=>$notes?:'Primary complaint assignment updated.',':user'=>currentUserId()
    ]);

    cefSubmissionHistory($pdo,$submissionId,'Complaint Assigned',$s['status'],$newStatus,'Complaint routed to the responsible office/committee/staff.',true);
    cepfmsLogActivity(currentUserId(),'CEPFMS Complaint Assignment',"{$s['reference_number']} assigned.");
    $pdo->commit();
    jsonResponse(true,'Complaint assignment saved.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to assign complaint.');
}
