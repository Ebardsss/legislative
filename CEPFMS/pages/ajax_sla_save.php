<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.configuration.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.',[],405);
requireCsrf();

$pdo=db();$id=(int)($_POST['service_level_id']??0);
$ack=(int)($_POST['acknowledgement_hours']??0);
$response=(int)($_POST['response_hours']??0);
$resolution=(int)($_POST['resolution_hours']??0);
$active=!empty($_POST['is_active'])?1:0;

if($ack<1||$response<1||$resolution<1)jsonResponse(false,'All SLA target values must be at least 1 hour.');
if($ack>$response||$response>$resolution){
    jsonResponse(false,'SLA order must be Acknowledgement ≤ Response ≤ Resolution.');
}
if($resolution>8760)jsonResponse(false,'Resolution target cannot exceed one year.');

try{
    $q=$pdo->prepare('SELECT * FROM cef_service_levels WHERE id=:id');
    $q->execute([':id'=>$id]);$old=$q->fetch();
    if(!$old)jsonResponse(false,'Service-level rule not found.');

    $pdo->prepare(
        'UPDATE cef_service_levels
         SET acknowledgement_hours=:ack,response_hours=:response,
             resolution_hours=:resolution,is_active=:active,updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':ack'=>$ack,':response'=>$response,':resolution'=>$resolution,
        ':active'=>$active,':id'=>$id
    ]);

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS Complaint SLA Updated',
        $old['urgency_level'].' · '.$old['acknowledgement_hours'].'/'.
        $old['response_hours'].'/'.$old['resolution_hours'].'h -> '.
        $ack.'/'.$response.'/'.$resolution.'h.'
    );

    jsonResponse(true,'Complaint SLA rule updated.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update SLA rule.');
}
