<?php
declare(strict_types=1);

/*
 * CEPFMS scheduled citizen-notification processor.
 *
 * Example Windows Task Scheduler command:
 * C:\xampp\php\php.exe C:\xampp\htdocs\cepfms\cron\process_notifications.php
 */

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';

if(PHP_SAPI!=='cli'){
    requireCefPermission('cepfms.notifications.manage');
}

$pdo=db();

$ids=$pdo->query(
    "SELECT id
     FROM cef_notifications
     WHERE status IN ('Ready','Scheduled','Partially Sent')
       AND (scheduled_at IS NULL OR scheduled_at<=NOW())
     ORDER BY COALESCE(scheduled_at,created_at)
     LIMIT 200"
)->fetchAll(PDO::FETCH_COLUMN);

$result=[
    'time'=>date('Y-m-d H:i:s'),
    'notifications_checked'=>count($ids),
    'processed_recipients'=>0,
    'pending_external'=>0,
    'errors'=>[],
];

foreach($ids as $id){
    try{
        $pdo->beginTransaction();
        $r=cefProcessNotification($pdo,(int)$id);
        $pdo->commit();
        $result['processed_recipients']+=(int)$r['processed'];
        $result['pending_external']+=(int)$r['pending_external'];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $result['errors'][]='Notification #'.$id.': '.$e->getMessage();
    }
}

if(PHP_SAPI==='cli'){
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}else{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
