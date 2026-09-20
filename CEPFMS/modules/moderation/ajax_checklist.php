<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$reviewId=(int)($_POST['review_id']??0);
$itemCode=clean($_POST['item_code']??'');
$status=clean($_POST['status']??'Pending');
$notes=trim((string)($_POST['notes']??''));

if(!in_array($status,['Pending','Pass','Fail','Not Applicable'],true)){
    jsonResponse(false,'Invalid checklist status.');
}

try{
    $q=$pdo->prepare(
        'SELECT c.id,r.submission_id
         FROM cef_moderation_checklist c
         JOIN cef_moderation_reviews r ON r.id=c.moderation_review_id
         WHERE c.moderation_review_id=:review
           AND c.item_code=:code
         LIMIT 1'
    );
    $q->execute([':review'=>$reviewId,':code'=>$itemCode]);
    $row=$q->fetch();
    if(!$row)jsonResponse(false,'Checklist item not found.');

    $pdo->prepare(
        'UPDATE cef_moderation_checklist
         SET status=:status,notes=:notes,updated_by=:user,updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status'=>$status,':notes'=>$notes?:null,
        ':user'=>currentUserId(),':id'=>$row['id']
    ]);

    $map=[
        'completeness'=>'completeness_status',
        'relevance'=>'relevance_status',
        'duplicate'=>'duplicate_status',
        'content'=>'content_status',
        'classification'=>'classification_status',
        'contact'=>'identity_status',
    ];
    if(isset($map[$itemCode])){
        $column=$map[$itemCode];
        $pdo->prepare(
            "UPDATE cef_moderation_reviews
             SET `$column`=:status,updated_at=NOW()
             WHERE id=:id"
        )->execute([':status'=>$status,':id'=>$reviewId]);
    }

    jsonResponse(true,'Checklist item updated.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update checklist.');
}
