<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.activity_logs.view');

$pdo=db();$pageTitle='Activity Logs';$activeMenu='activity_logs';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');
$action=clean($_GET['action']??'');
$userId=(int)($_GET['user_id']??0);
$from=clean($_GET['from']??'');
$to=clean($_GET['to']??'');
$page=max(1,(int)($_GET['page']??1));
$perPage=25;

$where=['al.system_id=:system'];
$params=[':system'=>cepfmsSystemId()];

if($search!==''){
    $where[]='(al.action LIKE :s1 OR al.details LIKE :s2 OR u.full_name LIKE :s3 OR u.email LIKE :s4 OR al.ip_address LIKE :s5)';
    $like='%'.$search.'%';
    foreach(['s1','s2','s3','s4','s5'] as $k)$params[':'.$k]=$like;
}
if($action!==''){$where[]='al.action=:action';$params[':action']=$action;}
if($userId>0){$where[]='al.user_id=:user';$params[':user']=$userId;}
if($from!==''){$where[]='al.created_at>=:from';$params[':from']=$from.' 00:00:00';}
if($to!==''){$where[]='al.created_at<=:to';$params[':to']=$to.' 23:59:59';}

$sqlWhere='WHERE '.implode(' AND ',$where);

$c=$pdo->prepare(
    "SELECT COUNT(*)
     FROM activity_logs al
     LEFT JOIN users u ON u.id=al.user_id
     {$sqlWhere}"
);
$c->execute($params);
$total=(int)$c->fetchColumn();
$pg=paginate($total,$page,$perPage);

$q=$pdo->prepare(
    "SELECT al.*,u.full_name,u.email
     FROM activity_logs al
     LEFT JOIN users u ON u.id=al.user_id
     {$sqlWhere}
     ORDER BY al.created_at DESC,al.id DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}"
);
$q->execute($params);
$rows=$q->fetchAll();

$actions=$pdo->prepare(
    'SELECT DISTINCT action
     FROM activity_logs
     WHERE system_id=:system
     ORDER BY action'
);
$actions->execute([':system'=>cepfmsSystemId()]);
$actionOptions=$actions->fetchAll(PDO::FETCH_COLUMN);

$users=$pdo->query(
    "SELECT id,full_name
     FROM users
     WHERE deleted_at IS NULL
     ORDER BY full_name"
)->fetchAll();

$todayQ=$pdo->prepare(
    'SELECT COUNT(*)
     FROM activity_logs
     WHERE system_id=:system
       AND DATE(created_at)=CURDATE()'
);
$todayQ->execute([':system'=>cepfmsSystemId()]);
$today=(int)$todayQ->fetchColumn();

$deniedQ=$pdo->prepare(
    "SELECT COUNT(*)
     FROM activity_logs
     WHERE system_id=:system
       AND action LIKE '%Denied%'
       AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)"
);
$deniedQ->execute([':system'=>cepfmsSystemId()]);
$denied=(int)$deniedQ->fetchColumn();

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head">
<div><div class="cef-eyebrow"><i class="bi bi-clock-history"></i> Step 9 · Audit Administration</div><h1>Activity Logs & Audit Trail</h1><p>Server-side filtering of the shared activity log restricted to the CEPFMS system ID, including actor, action, details, IP address, browser context and security-denial events.</p></div>
<div class="d-flex gap-2">
<a class="btn btn-outline-secondary" target="_blank" href="activity_logs_print.php?<?= e(http_build_query($_GET)) ?>"><i class="bi bi-printer"></i> Print</a>
<a class="btn btn-primary" href="activity_logs_export.php?<?= e(http_build_query($_GET)) ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
</div>
</div>

<div class="row g-3 mb-3">
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-list-ul"></i><div><strong><?= $total ?></strong><small>Matching Records</small></div></div></div>
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-calendar-check"></i><div><strong><?= $today ?></strong><small>Activity Today</small></div></div></div>
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-shield-exclamation"></i><div><strong><?= $denied ?></strong><small>Access/Permission Denials · 30d</small></div></div></div>
</div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-3"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Actor, action, details, IP"></div>
<div class="col-xl-2"><label class="form-label small">Action</label><select class="form-select form-select-sm" name="action"><option value="">All</option><?php foreach($actionOptions as $x): ?><option <?= $action===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">User</label><select class="form-select form-select-sm" name="user_id"><option value="">All</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $userId===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
<div class="col-xl-2"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card cef-card">
<div class="table-responsive"><table class="table cef-table mb-0">
<thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Details</th><th>IP</th><th>User Agent</th></tr></thead>
<tbody>
<?php if(!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No CEPFMS activity matched the filters.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><?= formatDateTime($r['created_at']) ?></td>
<td><?= e($r['full_name']?:'System / Public') ?><div class="small text-muted"><?= e($r['email']?:'') ?></div></td>
<td><span class="cef-code"><?= e($r['action']) ?></span></td>
<td><?= e($r['details']?:'—') ?></td>
<td><?= e($r['ip_address']?:'—') ?></td>
<td><div class="small text-muted" style="max-width:320px"><?= e(mb_strimwidth((string)$r['user_agent'],0,120,'…')) ?></div></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<div class="card-body border-top"><?= renderPagination($pg,'activity_logs.php',array_filter(['search'=>$search,'action'=>$action,'user_id'=>$userId?:null,'from'=>$from,'to'=>$to])) ?></div>
</div>

</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
