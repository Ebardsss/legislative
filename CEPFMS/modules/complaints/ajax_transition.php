<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['submission_id']??0);$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM cef_submissions WHERE id=:id AND submission_type="Complaint" AND deleted_at IS NULL FOR UPDATE');
    $q->execute([':id'=>$id]);$s=$q->fetch();
    if(!$s){$pdo->rollBack();jsonResponse(false,'Complaint not found.');}
    $old=$s['status'];$new=$old;$public=true;

    if($action==='start'){
        if($s['moderation_status']!=='Validated'||!in_array($old,['Validated','Assigned'],true)){$pdo->rollBack();jsonResponse(false,'Complaint must first be validated in Moderation & Validation.');}
        $new='In Progress';
        $pdo->prepare('UPDATE cef_submissions SET status="In Progress",updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }elseif($action==='await_citizen'){
        if(!in_array($old,['Assigned','In Progress'],true)){$pdo->rollBack();jsonResponse(false,'Complaint cannot request citizen information from its current status.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Describe the information required from the citizen.');}
        $new='Awaiting Citizen';
        $pdo->prepare('UPDATE cef_submissions SET status="Awaiting Citizen",updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }elseif($action==='resume'){
        if($old!=='Awaiting Citizen'){$pdo->rollBack();jsonResponse(false,'Only Awaiting Citizen complaints can resume.');}
        $new='In Progress';
        $pdo->prepare('UPDATE cef_submissions SET status="In Progress",updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }elseif($action==='resolve'){
        if(!in_array($old,['Assigned','In Progress','Awaiting Citizen','Responded'],true)){$pdo->rollBack();jsonResponse(false,'Complaint cannot be resolved from its current status.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Resolution summary is required.');}
        $new='Resolved';
        $pdo->prepare('UPDATE cef_submissions SET status="Resolved",closed_at=NULL,updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        $pdo->prepare('UPDATE cef_complaints SET resolution_summary=:notes,resolved_at=NOW(),citizen_confirmation="Requested",updated_at=NOW() WHERE submission_id=:id')->execute([':notes'=>$notes,':id'=>$id]);
        $pdo->prepare("UPDATE cef_assignments SET status='Completed',completed_at=NOW(),updated_at=NOW() WHERE submission_id=:id AND status<>'Cancelled'")->execute([':id'=>$id]);
    }elseif($action==='close'){
        if($old!=='Resolved'){$pdo->rollBack();jsonResponse(false,'Only resolved complaints can be closed.');}
        $new='Closed';
        $pdo->prepare('UPDATE cef_submissions SET status="Closed",closed_at=NOW(),updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid complaint workflow action.');
    }

    cefSubmissionHistory($pdo,$id,'Complaint '.ucwords(str_replace('_',' ',$action)),$old,$new,$notes?:cefPublicStatus($new),$public);
    cepfmsLogActivity(currentUserId(),'CEPFMS Complaint '.ucwords(str_replace('_',' ',$action)),"{$s['reference_number']} · {$old} -> {$new}.");
    $pdo->commit();
    jsonResponse(true,"Complaint is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update complaint workflow.');
}
