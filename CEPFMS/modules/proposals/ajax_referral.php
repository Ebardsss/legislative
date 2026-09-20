<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.proposals.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$submissionId=(int)($_POST['submission_id']??0);$itemId=(int)($_POST['legislative_item_id']??0);
$notes=trim((string)($_POST['notes']??''));
if(!$itemId)jsonResponse(false,'Choose a legislative item.');

try{
    $pdo->beginTransaction();
    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s||$s['submission_type']!=='Proposal'){$pdo->rollBack();jsonResponse(false,'Proposal not found.');}
    if($s['moderation_status']!=='Validated'){
        $pdo->rollBack();
        jsonResponse(false,'Proposal must first be validated in Moderation & Validation before legislative referral.');
    }

    $q=$pdo->prepare('SELECT id,reference_number,title FROM legislative_items WHERE id=:id AND deleted_at IS NULL');
    $q->execute([':id'=>$itemId]);$item=$q->fetch();
    if(!$item){$pdo->rollBack();jsonResponse(false,'Legislative item not found.');}

    $pdo->prepare(
        'INSERT INTO cef_legislative_referrals
         (submission_id,legislative_item_id,referral_type,status,notes,referred_by,referred_at,updated_at)
         VALUES(:submission,:item,"Proposal Referral","Referred",:notes,:user,NOW(),NOW())
         ON DUPLICATE KEY UPDATE status="Referred",notes=VALUES(notes),referred_by=VALUES(referred_by),referred_at=NOW(),updated_at=NOW()'
    )->execute([
        ':submission'=>$submissionId,':item'=>$itemId,':notes'=>$notes?:null,':user'=>currentUserId()
    ]);

    $pdo->prepare('UPDATE cef_proposals SET feasibility_status="Referred",disposition=:disposition,updated_at=NOW() WHERE submission_id=:id')
        ->execute([':disposition'=>'Referred to '.$item['reference_number'],':id'=>$submissionId]);

    $newStatus=in_array($s['status'],['Submitted','Under Moderation','Validated'],true)?'In Progress':$s['status'];
    $pdo->prepare('UPDATE cef_submissions SET status=:status,updated_at=NOW() WHERE id=:id')
        ->execute([':status'=>$newStatus,':id'=>$submissionId]);

    cefSubmissionHistory(
        $pdo,$submissionId,'Legislative Referral',$s['status'],$newStatus,
        'Proposal linked to '.$item['reference_number'].' · '.$item['title'].($notes?' · '.$notes:''),
        true
    );
    cepfmsLogActivity(currentUserId(),'CEPFMS Proposal Referral',"{$s['reference_number']} -> {$item['reference_number']}.");
    $pdo->commit();
    jsonResponse(true,'Proposal referred to the selected legislative item.');
}catch(PDOException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to create legislative referral.');
}
