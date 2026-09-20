<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$pageTitle='Feedback & Engagement';
$activeMenu='engagement';
$extraCss=[appUrl('assets/css/public-modules.css')];

$type=clean($_GET['type']??'');
$status=clean($_GET['status']??'');
$page=max(1,(int)($_GET['page']??1));

$where=['s.citizen_user_id=:user','s.deleted_at IS NULL'];
$params=[':user'=>currentUserId()];

if(in_array($type,['Feedback','Proposal','Complaint'],true)){
    $where[]='s.submission_type=:type';
    $params[':type']=$type;
}
if($status!==''){
    $where[]='s.status=:status';
    $params[':status']=$status;
}

$whereSql='WHERE '.implode(' AND ',$where);

$c=$pdo->prepare('SELECT COUNT(*) FROM cef_submissions s '.$whereSql);
$c->execute($params);
$total=(int)$c->fetchColumn();
$pg=publicModulePagination($total,$page,12);

$q=$pdo->prepare(
    'SELECT s.id,s.reference_number,s.submission_type,s.title,s.summary,
            s.status,s.moderation_status,s.priority_level,s.created_at,s.updated_at,
            c.name category_name,
            (SELECT COUNT(*) FROM cef_responses r
             WHERE r.submission_id=s.id AND r.status="Delivered") delivered_responses
     FROM cef_submissions s
     LEFT JOIN cef_categories c ON c.id=s.category_id
     '.$whereSql.'
     ORDER BY s.created_at DESC,s.id DESC
     LIMIT '.$pg['perPage'].' OFFSET '.$pg['offset']
);
$q->execute($params);
$rows=$q->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<span class="eyebrow">CEPFMS CITIZEN SERVICES</span>
<h1>My Feedback & Engagement</h1>
<p>Submit feedback, proposals or complaints and follow the status using your signed-in citizen account.</p>
</div>
<a class="btn btn-primary" href="<?= e(appUrl('modules/engagement/create.php')) ?>"><i class="bi bi-plus-lg"></i> New Submission</a>
</div>

<div class="public-filter-card mb-4">
<form class="row g-2 align-items-end">
<div class="col-lg-5"><label class="form-label">Type</label><select class="form-select" name="type"><option value="">All Types</option><?php foreach(['Feedback','Proposal','Complaint'] as $x): ?><option <?= $type===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-5"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All Statuses</option><?php foreach(['Submitted','Under Moderation','Validated','Assigned','In Progress','Awaiting Citizen','Responded','Resolved','Closed','Rejected','Duplicate','Withdrawn'] as $x): ?><option <?= $status===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
</div>

<div class="small text-muted mb-3"><strong><?= $total ?></strong> submission(s) linked to your citizen account</div>

<div class="row g-3">
<?php if(!$rows): ?><div class="col-12"><div class="public-empty"><i class="bi bi-chat-square-heart"></i><strong>No citizen submissions yet.</strong><span>Create your first feedback, proposal or complaint.</span></div></div><?php endif; ?>
<?php foreach($rows as $r): ?>
<div class="col-lg-6">
<a class="public-record-card" href="<?= e(appUrl('modules/engagement/view.php?id='.(int)$r['id'])) ?>">
<div class="public-record-top">
<span class="public-code"><?= e($r['reference_number']) ?></span>
<span class="public-status <?= e(portalStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span>
</div>
<h2><?= e($r['title']) ?></h2>
<p><?= e(portalExcerpt($r['summary'])) ?></p>
<div class="public-meta">
<span><i class="bi bi-tag"></i><?= e($r['submission_type']) ?></span>
<span><i class="bi bi-calendar3"></i><?= formatDateTime($r['created_at']) ?></span>
<?php if((int)$r['delivered_responses']>0): ?><span><i class="bi bi-reply-fill"></i> Official response available</span><?php endif; ?>
</div>
</a>
</div>
<?php endforeach; ?>
</div>

<div class="public-pagination mt-4">
<?= publicModulePaginationHtml($pg,'modules/engagement/index.php',['type'=>$type,'status'=>$status]) ?>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
