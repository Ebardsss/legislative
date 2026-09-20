<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT
        li.id,li.reference_number,li.title,li.summary,li.current_status,
        li.priority_level,li.created_at,li.updated_at,
        lit.code type_code,lit.name type_name,
        d.short_title,d.subject,d.legislative_category,d.policy_area,
        d.purpose,d.explanatory_note,d.body_text,d.legal_basis,d.fiscal_impact,
        o.name office_name
     FROM legislative_items li
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     JOIN orlms_publications px
       ON px.legislative_item_id=li.id
      AND px.publication_status="Published"
      AND px.release_classification="Public"
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     LEFT JOIN offices o ON o.id=li.originating_office_id
     WHERE li.id=:id
       AND li.deleted_at IS NULL
       AND li.visibility="Public"
     LIMIT 1'
);
$q->execute([':id'=>$id]);
$item=$q->fetch();

if(!$item){
    http_response_code(404);
    exit('Published legislative record not found.');
}

$pubQ=$pdo->prepare(
    'SELECT id,publication_reference,publication_channel,publication_date,
            public_url,publication_text,remarks
     FROM orlms_publications
     WHERE legislative_item_id=:item
       AND publication_status="Published"
       AND release_classification="Public"
     ORDER BY publication_date DESC,id DESC'
);
$pubQ->execute([':item'=>$id]);
$publications=$pubQ->fetchAll();

$enactQ=$pdo->prepare(
    'SELECT official_number,enactment_type,enactment_status,
            approved_date,signed_date,enactment_date,signing_authority,
            effectivity_type,effectivity_date,remarks
     FROM orlms_enactments
     WHERE legislative_item_id=:item
       AND enactment_status IN ("Approved","Enacted","Finalized")
     ORDER BY COALESCE(enactment_date,approved_date) DESC,id DESC
     LIMIT 1'
);
$enactQ->execute([':item'=>$id]);
$enactment=$enactQ->fetch()?:null;

portalLogPublicView('ORLMS',$item['reference_number']);

$pageTitle=$item['reference_number'];
$activeMenu='ordinances';
$extraCss=[appUrl('assets/css/public-modules.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/ordinances/index.php')) ?>"><i class="bi bi-arrow-left"></i> Ordinances & Resolutions</a>
<h1><?= e($item['title']) ?></h1>
<p><?= e($item['reference_number']) ?> · <?= e($item['type_name']) ?></p>
</div>
<span class="public-status good">Published</span>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card mb-4">
<div class="card-header">Public Legislative Information</div>
<div class="card-body">
<div class="public-detail-grid">
<div><small>Reference</small><strong><?= e($item['reference_number']) ?></strong></div>
<div><small>Type</small><strong><?= e($item['type_name']) ?></strong></div>
<div><small>Originating Office</small><strong><?= e($item['office_name']?:'—') ?></strong></div>
<div><small>Status</small><strong><?= e($item['current_status']) ?></strong></div>
<div><small>Category</small><strong><?= e($item['legislative_category']?:'—') ?></strong></div>
<div><small>Policy Area</small><strong><?= e($item['policy_area']?:'—') ?></strong></div>
</div>

<?php foreach([
    'Summary'=>$item['summary'],
    'Subject'=>$item['subject'],
    'Purpose'=>$item['purpose'],
    'Explanatory Note'=>$item['explanatory_note'],
    'Legal Basis'=>$item['legal_basis'],
    'Fiscal / Resource Impact'=>$item['fiscal_impact'],
] as $label=>$value): if(trim((string)$value)!==''): ?>
<div class="public-text-block"><h3><?= e($label) ?></h3><p><?= nl2br(e($value)) ?></p></div>
<?php endif; endforeach; ?>

<?php if(trim((string)$item['body_text'])!==''): ?>
<div class="public-text-block">
<h3>Legislative Text</h3>
<div class="public-legislative-text"><?= nl2br(e($item['body_text'])) ?></div>
</div>
<?php endif; ?>
</div>
</div>
</div>

<div class="col-xl-4">
<?php if($enactment): ?>
<div class="card portal-card mb-4">
<div class="card-header">Enactment</div>
<div class="card-body">
<div class="public-side-list">
<div><small>Official Number</small><strong><?= e($enactment['official_number']?:'—') ?></strong></div>
<div><small>Enactment Date</small><strong><?= formatDate($enactment['enactment_date']) ?></strong></div>
<div><small>Effectivity</small><strong><?= e($enactment['effectivity_type']?:'—') ?></strong></div>
<div><small>Effectivity Date</small><strong><?= formatDate($enactment['effectivity_date']) ?></strong></div>
<div><small>Signing Authority</small><strong><?= e($enactment['signing_authority']?:'—') ?></strong></div>
</div>
</div>
</div>
<?php endif; ?>

<div class="card portal-card">
<div class="card-header">Official Publication</div>
<div class="card-body">
<?php foreach($publications as $p): ?>
<div class="publication-entry">
<strong><?= e($p['publication_reference']) ?></strong>
<small><?= e($p['publication_channel']) ?> · <?= formatDateTime($p['publication_date']) ?></small>
<?php if($p['publication_text']): ?><p><?= e(portalExcerpt($p['publication_text'],220)) ?></p><?php endif; ?>
<?php $publicUrl=portalPublicUrl($p['public_url']); if($publicUrl): ?>
<a class="btn btn-sm btn-outline-primary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Official Public Link <i class="bi bi-box-arrow-up-right"></i></a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
