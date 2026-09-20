<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.proposals.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['submission_id']??0);
$category=(int)($_POST['category_id']??0)?:null;
$priority=clean($_POST['priority_level']??'Normal');
$status=clean($_POST['status']??'Submitted');
$feasibility=clean($_POST['feasibility_status']??'Not Reviewed');
$disposition=clean($_POST['disposition']??'')?:null;
$note=trim((string)($_POST['note']??''));
$public=!empty($_POST['public_visible']);

if(!in_array($priority,cefPriorityLevels(),true))jsonResponse(false,'Invalid priority.');
if(!in_array($status,['Submitted','Under Moderation','Validated','Assigned','In Progress','Responded','Resolved','Closed','Rejected','Duplicate','Withdrawn','Awaiting Citizen'],true))jsonResponse(false,'Invalid proposal status.');
if(!in_array($feasibility,['Not Reviewed','For Study','Feasible','Needs Revision','Not Feasible','Referred'],true))jsonResponse(false,'Invalid feasibility status.');

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM cef_submissions WHERE id=:id AND submission_type="Proposal" AND deleted_at IS NULL FOR UPDATE');
    $q->execute([':id'=>$id]);$s=$q->fetch();
    if(!$s){$pdo->rollBack();jsonResponse(false,'Proposal record not found.');}

    if($s['moderation_status']!=='Validated'){
        if($status === 'Validated'){
            // Automatically complete moderation review and validate
            $review = cefEnsureModerationReview($pdo, $id);
            $pdo->prepare('UPDATE cef_moderation_reviews SET decision="Validated", reviewed_at=NOW(), updated_at=NOW() WHERE id=:id')->execute([':id'=>$review['id']]);
            $pdo->prepare('UPDATE cef_moderation_checklist SET status="Pass", updated_at=NOW() WHERE moderation_review_id=:id AND status="Pending"')->execute([':id'=>$review['id']]);
            $pdo->prepare('UPDATE cef_submissions SET moderation_status="Validated", updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
            $s['moderation_status'] = 'Validated';
        } elseif(!in_array($status,['Submitted','Under Moderation','Awaiting Citizen','Withdrawn'],true)){
            $pdo->rollBack();
            jsonResponse(false,'Formal validation, rejection, and duplicate decisions must be completed in Moderation & Validation before proposal disposition.');
        }
    }else{
        $allowedPostValidation=['Validated','Assigned','In Progress','Responded','Resolved','Closed','Withdrawn'];
        if(!in_array($status,$allowedPostValidation,true)){
            $pdo->rollBack();
            jsonResponse(false,'Use the post-validation proposal workflow. Rejection and duplicate decisions remain controlled by Moderation & Validation.');
        }
    }

    $pdo->prepare('UPDATE cef_submissions SET category_id=:category,priority_level=:priority,status=:status,updated_at=NOW() WHERE id=:id')
        ->execute([':category'=>$category,':priority'=>$priority,':status'=>$status,':id'=>$id]);
    $pdo->prepare('UPDATE cef_proposals SET feasibility_status=:feasibility,disposition=:disposition,updated_at=NOW() WHERE submission_id=:id')
        ->execute([':feasibility'=>$feasibility,':disposition'=>$disposition,':id'=>$id]);

    cefSubmissionHistory($pdo,$id,'Proposal Management Update',$s['status'],$status,$note?:'Proposal assessment updated.',$public);
    cepfmsLogActivity(currentUserId(),'CEPFMS Proposal Update',"{$s['reference_number']} · {$s['status']} -> {$status}.");
    $pdo->commit();
    jsonResponse(true,'Proposal updated.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update proposal.');
}
