<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$pageTitle='Ordinances & Resolutions';
$activeMenu='ordinances';
$extraCss=[appUrl('assets/css/public-modules.css')];

$search=clean($_GET['search']??'');
$type=clean($_GET['type']??'');
$page=max(1,(int)($_GET['page']??1));

$where=[
    'li.deleted_at IS NULL',
    'li.visibility="Public"',
    'p.publication_status="Published"',
    'p.release_classification="Public"',
];
$params=[];

if($search!==''){
    $where[]='(li.reference_number LIKE :s1 OR li.title LIKE :s2 OR li.summary LIKE :s3 OR d.subject LIKE :s4)';
    $like='%'.$search.'%';
    $params[':s1']=$like;
    $params[':s2']=$like;
    $params[':s3']=$like;
    $params[':s4']=$like;
}
if(in_array($type,['ORDINANCE','RESOLUTION'],true)){
    $where[]='lit.code=:type';
    $params[':type']=$type;
}

$whereSql='WHERE '.implode(' AND ',$where);

$countSql=
    'SELECT COUNT(DISTINCT li.id)
     FROM legislative_items li
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     JOIN orlms_publications p ON p.legislative_item_id=li.id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     '.$whereSql;

$count=$pdo->prepare($countSql);
$count->execute($params);
$total=(int)$count->fetchColumn();
$pg=publicModulePagination($total,$page,12);

$sql=
    'SELECT
        li.id,li.reference_number,li.title,li.summary,li.current_status,
        li.priority_level,li.updated_at,
        lit.code type_code,lit.name type_name,
        d.short_title,d.subject,d.legislative_category,d.policy_area,
        o.name office_name,
        MAX(p.publication_date) publication_date
     FROM legislative_items li
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     JOIN orlms_publications p ON p.legislative_item_id=li.id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     LEFT JOIN offices o ON o.id=li.originating_office_id
     '.$whereSql.'
     GROUP BY
        li.id,li.reference_number,li.title,li.summary,li.current_status,
        li.priority_level,li.updated_at,
        lit.code,lit.name,
        d.short_title,d.subject,d.legislative_category,d.policy_area,
        o.name
     ORDER BY MAX(p.publication_date) DESC,li.updated_at DESC,li.id DESC
     LIMIT '.$pg['perPage'].' OFFSET '.$pg['offset'];

$q=$pdo->prepare($sql);
$q->execute($params);
$rows=$q->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head mb-4">
<div>
<span class="eyebrow">ORLMS PUBLIC RECORDS</span>
<h1>Ordinances & Resolutions</h1>
<p>Browse legislative measures that have been officially published for public viewing.</p>
</div>
<div class="mt-2 mt-md-0">
<a class="btn btn-outline-secondary" href="<?= e(appUrl('dashboard.php')) ?>"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>
</div>

<div class="public-filter-card mb-4">
<form class="row g-2 align-items-end">
<div class="col-lg-7">
<label class="form-label">Search</label>
<input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Reference number, title, subject or keyword">
</div>
<div class="col-lg-3">
<label class="form-label">Type</label>
<select class="form-select" name="type">
<option value="">All Types</option>
<option value="ORDINANCE" <?= $type==='ORDINANCE'?'selected':'' ?>>Ordinance</option>
<option value="RESOLUTION" <?= $type==='RESOLUTION'?'selected':'' ?>>Resolution</option>
</select>
</div>
<div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
</form>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
<div class="small text-muted"><strong><?= $total ?></strong> published public record(s)</div>
<a href="<?= e(appUrl('modules/ordinances/index.php')) ?>" class="small text-decoration-none">Clear filters</a>
</div>

<div class="row g-3">
<?php if(!$rows): ?>
<div class="col-12"><div class="public-empty"><i class="bi bi-journal-x"></i><strong>No published legislation found.</strong><span>Try another search or filter.</span></div></div>
<?php endif; ?>

<?php foreach($rows as $r): ?>
<div class="col-lg-6">
<a class="public-record-card" href="<?= e(appUrl('modules/ordinances/view.php?id='.(int)$r['id'])) ?>">
<div class="public-record-top">
<span class="public-code"><?= e($r['reference_number']) ?></span>
<span class="public-type"><?= e($r['type_name']) ?></span>
</div>
<h2><?= e($r['title']) ?></h2>
<p><?= e(portalExcerpt($r['summary']?:$r['subject'])) ?></p>
<div class="public-meta">
<span><i class="bi bi-building"></i><?= e($r['office_name']?:'Legislative Office') ?></span>
<span><i class="bi bi-calendar-check"></i><?= formatDate($r['publication_date']) ?></span>
</div>
<div class="public-tags">
<?php if($r['legislative_category']): ?><span><?= e($r['legislative_category']) ?></span><?php endif; ?>
<?php if($r['policy_area']): ?><span><?= e($r['policy_area']) ?></span><?php endif; ?>
</div>
</a>
</div>
<?php endforeach; ?>
</div>

<div class="public-pagination mt-4">
<?= publicModulePaginationHtml($pg,'modules/ordinances/index.php',['search'=>$search,'type'=>$type]) ?>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
