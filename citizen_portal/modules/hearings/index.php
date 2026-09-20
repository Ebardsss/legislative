<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$pageTitle='Hearings & Consultations';
$activeMenu='hearings';
$extraCss=[appUrl('assets/css/public-modules.css')];

$search=clean($_GET['search']??'');
$status=clean($_GET['status']??'');
$page=max(1,(int)($_GET['page']??1));

$where=[
    'h.visibility="Public"',
    'h.status IN ("Upcoming","Ongoing","Completed")'
];
$params=[];

if($search!==''){
    $where[]='(h.reference_number LIKE :s1 OR h.title LIKE :s2 OR h.description LIKE :s3 OR c.name LIKE :s4 OR ht.name LIKE :s5)';
    $like='%'.$search.'%';
    $params=[':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like,':s5'=>$like];
}
if(in_array($status,['Upcoming','Ongoing','Completed'],true)){
    $where[]='h.status=:status';
    $params[':status']=$status;
}

$whereSql='WHERE '.implode(' AND ',$where);

$c=$pdo->prepare(
    'SELECT COUNT(*)
     FROM hearings h
     LEFT JOIN hearing_types ht ON ht.id=h.hearing_type_id
     LEFT JOIN committees c ON c.id=h.committee_id
     '.$whereSql
);
$c->execute($params);
$total=(int)$c->fetchColumn();
$pg=publicModulePagination($total,$page,12);

$q=$pdo->prepare(
    'SELECT
        h.id,h.reference_number,h.title,h.description,h.venue,
        h.hearing_date,h.end_date,h.hearing_time,h.end_time,h.status,
        h.registration_deadline,h.maximum_participants,h.meeting_link,
        ht.name hearing_type,c.name committee_name,
        (SELECT COUNT(*) FROM registrations r
         WHERE r.hearing_id=h.id
           AND r.registration_status IN ("Pending","Approved")) registration_count
     FROM hearings h
     LEFT JOIN hearing_types ht ON ht.id=h.hearing_type_id
     LEFT JOIN committees c ON c.id=h.committee_id
     '.$whereSql.'
     ORDER BY
       CASE h.status WHEN "Ongoing" THEN 1 WHEN "Upcoming" THEN 2 ELSE 3 END,
       h.hearing_date ASC,h.hearing_time ASC,h.id DESC
     LIMIT '.$pg['perPage'].' OFFSET '.$pg['offset']
);
$q->execute($params);
$rows=$q->fetchAll();

$stakeholder=citizenStakeholder($pdo,currentUserId());
$registrations=[];
if($stakeholder){
    $rq=$pdo->prepare(
        'SELECT r.hearing_id,r.registration_status,r.registration_code
         FROM registrations r
         WHERE r.stakeholder_id=:stakeholder'
    );
    $rq->execute([':stakeholder'=>(int)$stakeholder['id']]);
    foreach($rq->fetchAll() as $r)$registrations[(int)$r['hearing_id']]=$r;
}

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<span class="eyebrow">PHCMS PUBLIC HEARINGS</span>
<h1>Hearings & Consultations</h1>
<p>View public hearing schedules and register your citizen account for an upcoming consultation.</p>
</div>
<a class="btn btn-outline-secondary" href="<?= e(appUrl('dashboard.php')) ?>"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>

<div class="public-filter-card mb-4">
<form class="row g-2 align-items-end">
<div class="col-lg-7"><label class="form-label">Search</label><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Hearing, committee, type or keyword"></div>
<div class="col-lg-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All Public Statuses</option><?php foreach(['Upcoming','Ongoing','Completed'] as $x): ?><option <?= $status===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
</form>
</div>

<div class="small text-muted mb-3"><strong><?= $total ?></strong> public hearing(s)</div>

<div class="row g-3">
<?php if(!$rows): ?><div class="col-12"><div class="public-empty"><i class="bi bi-megaphone"></i><strong>No public hearing found.</strong><span>Try another search or filter.</span></div></div><?php endif; ?>
<?php foreach($rows as $h):
    $reg=$registrations[(int)$h['id']]??null;
    $deadlineOk=!$h['registration_deadline']||strtotime($h['registration_deadline'])>=time();
    $capacityOk=!$h['maximum_participants']||(int)$h['registration_count']<(int)$h['maximum_participants'];
?>
<div class="col-lg-6">
<a class="public-record-card" href="<?= e(appUrl('modules/hearings/view.php?id='.(int)$h['id'])) ?>">
<div class="public-record-top">
<span class="public-code"><?= e($h['reference_number']?:'HEARING-'.$h['id']) ?></span>
<span class="public-status <?= e(portalStatusClass($h['status'])) ?>"><?= e($h['status']) ?></span>
</div>
<h2><?= e($h['title']) ?></h2>
<p><?= e(portalExcerpt($h['description'])) ?></p>
<div class="public-meta">
<span><i class="bi bi-calendar3"></i><?= formatDate($h['hearing_date']) ?> <?= e(date('h:i A',strtotime($h['hearing_time']))) ?></span>
<span><i class="bi bi-geo-alt"></i><?= e($h['venue']?:'To be announced') ?></span>
</div>
<div class="public-tags">
<?php if($h['hearing_type']): ?><span><?= e($h['hearing_type']) ?></span><?php endif; ?>
<?php if($h['committee_name']): ?><span><?= e($h['committee_name']) ?></span><?php endif; ?>
<?php if($reg): ?><span>My Registration: <?= e($reg['registration_status']) ?></span>
<?php elseif($h['status']==='Upcoming'&&$deadlineOk&&$capacityOk): ?><span>Registration Open</span>
<?php endif; ?>
</div>
</a>
</div>
<?php endforeach; ?>
</div>

<div class="public-pagination mt-4">
<?= publicModulePaginationHtml($pg,'modules/hearings/index.php',['search'=>$search,'status'=>$status]) ?>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
