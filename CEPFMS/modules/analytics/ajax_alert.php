<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['alert_id']??0);$notes=trim((string)($_POST['resolution_notes']??''));
if($notes==='')jsonResponse(false,'Resolution notes are required.');

try{
    $q=$pdo->prepare('SELECT * FROM cef_analytics_alerts WHERE id=:id AND status="Open"');
    $q->execute([':id'=>$id]);$a=$q->fetch();
    if(!$a)jsonResponse(false,'Open analytics alert not found.');

    $pdo->prepare(
        'UPDATE cef_analytics_alerts
         SET status="Resolved",resolved_by=:user,resolved_at=NOW(),resolution_notes=:notes
         WHERE id=:id'
    )->execute([':user'=>currentUserId(),':notes'=>$notes,':id'=>$id]);

    cepfmsLogActivity(currentUserId(),'CEPFMS Analytics Alert Resolved',"Alert #{$id} · {$a['dimension_value']}.");
    jsonResponse(true,'Analytics alert resolved.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to resolve analytics alert.');
}
