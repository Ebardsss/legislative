<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$pageTitle='Voting Results';
$activeMenu='voting';
$extraCss=[appUrl('assets/css/public-modules.css')];

$search=clean($_GET['search']??'');
$outcome=clean($_GET['outcome']??'');
$page=max(1,(int)($_GET['page']??1));

$where=[
    'd.record_status="Published"',
    'd.release_classification="Public"',
];
$params=[];

if($search!==''){
    $where[]='(d.reference_number LIKE :s1 OR d.decision_record_number LIKE :s2 OR d.decision_statement LIKE :s3 OR li.title LIKE :s4 OR li.reference_number LIKE :s5)';
    $like='%'.$search.'%';
    $params=[':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like,':s5'=>$like];
}
if(in_array($outcome,['Passed','Approved','Adopted','Rejected','Failed','Vetoed'],true)){
    $where[]='d.official_outcome=:outcome';
    $params[':outcome']=$outcome;
}

$whereSql='WHERE '.implode(' AND ',$where);

$c=$pdo->prepare(
    'SELECT COUNT(*)
     FROM vqd_decisions d
     LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
     '.$whereSql
);
$c->execute($params);
$total=(int)$c->fetchColumn();
$pg=publicModulePagination($total,$page,12);

$q=$pdo->prepare(
    'SELECT
        d.id,d.reference_number,d.decision_record_number,d.decision_datetime,
        d.official_outcome,d.decision_classification,d.required_votes,
        d.affirmative_votes,d.negative_votes,d.abstain_votes,
        d.decision_statement,d.effectivity_date,d.published_at,
        li.reference_number legislative_reference,li.title legislative_title
     FROM vqd_decisions d
     LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
     '.$whereSql.'
     ORDER BY COALESCE(d.published_at,d.decision_datetime) DESC,d.id DESC
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
<span class="eyebrow">VQDSS PUBLIC RESULTS</span>
<h1>Published Voting Results</h1>
<p>View official voting decisions that have been released for public access.</p>
</div>
<a class="btn btn-outline-secondary" href="<?= e(appUrl('dashboard.php')) ?>"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>

<div class="public-filter-card mb-4">
<form class="row g-2 align-items-end">
<div class="col-lg-7">
<label class="form-label">Search</label>
<input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Decision reference, legislative item or statement">
</div>
<div class="col-lg-3">
<label class="form-label">Outcome</label>
<select class="form-select" name="outcome">
<option value="">All Outcomes</option>
<?php foreach(['Passed','Approved','Adopted','Rejected','Failed','Vetoed'] as $x): ?><option <?= $outcome===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?>
</select>
</div>
<div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
</form>
</div>

<div class="small text-muted mb-3"><strong><?= $total ?></strong> published result(s)</div>

<div class="row g-3">
<?php if(!$rows): ?><div class="col-12"><div class="public-empty"><i class="bi bi-bar-chart"></i><strong>No published voting results found.</strong><span>Try another search or outcome filter.</span></div></div><?php endif; ?>

<?php foreach($rows as $r): ?>
<div class="col-lg-6">
<a class="public-record-card" href="<?= e(appUrl('modules/voting/view.php?id='.(int)$r['id'])) ?>">
<div class="public-record-top">
<span class="public-code"><?= e($r['decision_record_number']?:$r['reference_number']) ?></span>
<span class="public-status <?= e(portalStatusClass($r['official_outcome'])) ?>"><?= e($r['official_outcome']) ?></span>
</div>
<h2><?= e($r['legislative_title']?:$r['decision_statement']) ?></h2>
<p><?= e(portalExcerpt($r['decision_statement'])) ?></p>
<div class="vote-summary">
<div><strong><?= (int)$r['affirmative_votes'] ?></strong><span>Yes</span></div>
<div><strong><?= (int)$r['negative_votes'] ?></strong><span>No</span></div>
<div><strong><?= (int)$r['abstain_votes'] ?></strong><span>Abstain</span></div>
</div>
<div class="public-meta">
<span><i class="bi bi-calendar-check"></i><?= formatDateTime($r['decision_datetime']) ?></span>
<?php if($r['legislative_reference']): ?><span><i class="bi bi-journal-text"></i><?= e($r['legislative_reference']) ?></span><?php endif; ?>
</div>
</a>
</div>
<?php endforeach; ?>
</div>

<div class="public-pagination mt-4">
<?= publicModulePaginationHtml($pg,'modules/voting/index.php',['search'=>$search,'outcome'=>$outcome]) ?>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
