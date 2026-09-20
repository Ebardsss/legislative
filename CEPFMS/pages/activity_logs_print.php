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
    'SELECT al.*,u.full_name,u.email
     FROM activity_logs al
     LEFT JOIN users u ON u.id=al.user_id
     WHERE '.implode(' AND ',$where).'
     ORDER BY al.created_at DESC,al.id DESC'
);
$q->execute($params);$rows=$q->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>CEPFMS Activity Logs</title>
<style>body{font:10px Arial;margin:20px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:10px;margin-bottom:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:5px;vertical-align:top}th{background:#eee}.details{max-width:300px}</style>
</head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Activity Logs</h2><div><?= date('F j, Y g:i A') ?></div></div>
<table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= e($r['created_at']) ?></td><td><?= e($r['full_name']?:'System / Public') ?><br><?= e($r['email']?:'') ?></td><td><?= e($r['action']) ?></td><td class="details"><?= e($r['details']?:'—') ?></td><td><?= e($r['ip_address']?:'—') ?></td></tr><?php endforeach; ?>
</tbody></table></body></html>
