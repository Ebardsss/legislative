<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.notifications.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['notification_id']??0);$mode=clean($_POST['mode']??'process');

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare('SELECT * FROM cef_notifications WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$n=$q->fetch();
    if(!$n){$pdo->rollBack();jsonResponse(false,'Notification not found.');}

    if($mode==='process'){
        $pdo->commit();
        $pdo->beginTransaction();
        $result=cefProcessNotification($pdo,$id);
        $pdo->commit();

        $message=!empty($result['not_due'])
            ? 'This notification is scheduled for a future date/time.'
            : ($result['pending_external']>0
                ? 'Portal/internal delivery processed. External provider delivery remains pending.'
                : 'Notification delivery processed.');

        jsonResponse(true,$message,$result);
    }

    if($mode==='cancel'){
        if(in_array($n['status'],['Sent','Cancelled'],true)){
            $pdo->rollBack();jsonResponse(false,'Notification cannot be cancelled.');
        }
        $pdo->prepare(
            'UPDATE cef_notifications
             SET status="Cancelled",updated_at=NOW()
             WHERE id=:id'
        )->execute([':id'=>$id]);
        $pdo->prepare(
            "UPDATE cef_notification_recipients
             SET delivery_status='Cancelled'
             WHERE notification_id=:id
               AND delivery_status='Pending'"
        )->execute([':id'=>$id]);
        $pdo->commit();
        jsonResponse(true,'Notification cancelled.');
    }

    $pdo->rollBack();
    jsonResponse(false,'Invalid notification action.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to process notification.');
}
