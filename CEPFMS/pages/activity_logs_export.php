<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.activity_logs.view');

$pdo=db();
$search=clean($_GET['search']??'');$action=clean($_GET['action']??'');
$userId=(int)($_GET['user_id']??0);$from=clean($_GET['from']??'');$to=clean($_GET['to']??'');

$where=['al.system_id=:system'];$params=[':system'=>cepfmsSystemId()];
if($search!==''){
    $where[]='(al.action LIKE :s1 OR al.details LIKE :s2 OR u.full_name LIKE :s3 OR u.email LIKE :s4 OR al.ip_address LIKE :s5)';
    $like='%'.$search.'%';foreach(['s1','s2','s3','s4','s5'] as $k)$params[':'.$k]=$like;
}
if($action!==''){$where[]='al.action=:action';$params[':action']=$action;}
if($userId>0){$where[]='al.user_id=:user';$params[':user']=$userId;}
if($from!==''){$where[]='al.created_at>=:from';$params[':from']=$from.' 00:00:00';}
if($to!==''){$where[]='al.created_at<=:to';$params[':to']=$to.' 23:59:59';}

$q=$pdo->prepare(
    'SELECT al.created_at,u.full_name,u.email,al.action,al.details,al.ip_address,al.user_agent
     FROM activity_logs al
     LEFT JOIN users u ON u.id=al.user_id
     WHERE '.implode(' AND ',$where).'
     ORDER BY al.created_at DESC,al.id DESC'
);
$q->execute($params);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="cepfms_activity_logs_'.date('Ymd_His').'.csv"');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Time','Actor','Email','Action','Details','IP Address','User Agent']);
while($r=$q->fetch()){
    fputcsv($out,[
        $r['created_at'],$r['full_name'],$r['email'],$r['action'],
        $r['details'],$r['ip_address'],$r['user_agent']
    ]);
}
fclose($out);
cepfmsLogActivity(currentUserId(),'CEPFMS Activity Log Export','Activity-log CSV export.');
exit;
