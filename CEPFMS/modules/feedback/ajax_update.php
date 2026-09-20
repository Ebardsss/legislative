<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['submission_id']??0);
$category=(int)($_POST['category_id']??0)?:null;
$priority=clean($_POST['priority_level']??'Normal');
$status=clean($_POST['status']??'Submitted');
$note=trim((string)($_POST['note']??''));
$public=!empty($_POST['public_visible']);

if(!in_array($priority,cefPriorityLevels(),true))jsonResponse(false,'Invalid priority.');

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM cef_submissions WHERE id=:id AND submission_type="Feedback" AND deleted_at IS NULL FOR UPDATE');
    $q->execute([':id'=>$id]);$s=$q->fetch();
    if(!$s){$pdo->rollBack();jsonResponse(false,'Feedback record not found.');}

    $allowedStatuses = ($s['moderation_status'] === 'Validated')
        ? ['Validated','In Progress','Awaiting Citizen','Responded','Resolved','Closed']
        : ['Submitted','Under Moderation'];

    if(!in_array($status, $allowedStatuses, true) && $status !== $s['status']){
        $pdo->rollBack();
        jsonResponse(false,'Validation, rejection, and duplicate decisions must be completed in Moderation & Validation.');
    }

    $moderation=$status==='Under Moderation'?'Under Review':$s['moderation_status'];
    $closedAt = in_array($status, ['Closed','Resolved'], true) ? date('Y-m-d H:i:s') : null;

    $pdo->prepare(
        'UPDATE cef_submissions
         SET category_id=:category,priority_level=:priority,status=:status,
             moderation_status=:moderation,closed_at=:closed_at,updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':category'=>$category,':priority'=>$priority,':status'=>$status,
        ':moderation'=>$moderation,':closed_at'=>$closedAt,':id'=>$id
    ]);

    cefSubmissionHistory(
        $pdo,$id,'Feedback Intake Update',$s['status'],$status,
        $note?:'Feedback classification/status updated.',$public
    );
    cepfmsLogActivity(currentUserId(),'CEPFMS Feedback Update',"{$s['reference_number']} · {$s['status']} -> {$status}.");

    $pdo->commit();
    jsonResponse(true,'Feedback record updated.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update feedback.');
}
