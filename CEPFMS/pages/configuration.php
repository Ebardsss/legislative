<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.configuration.manage');

$pdo=db();
$pageTitle='CEPFMS Configuration';
$activeMenu='configuration';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$categories=$pdo->query(
    "SELECT c.*,o.name office_name,cm.name committee_name,
            (SELECT COUNT(*) FROM cef_submissions s
             WHERE s.category_id=c.id AND s.deleted_at IS NULL) usage_count
     FROM cef_categories c
     LEFT JOIN offices o ON o.id=c.default_office_id
     LEFT JOIN committees cm ON cm.id=c.default_committee_id
     ORDER BY c.is_active DESC,c.category_type,c.name"
)->fetchAll();

$serviceLevels=$pdo->query(
    "SELECT *
     FROM cef_service_levels
     ORDER BY FIELD(urgency_level,'Urgent','High','Normal','Low'),submission_type"
)->fetchAll();

$offices=$pdo->query(
    "SELECT id,name
     FROM offices
     WHERE status='Active'
     ORDER BY name"
)->fetchAll();

$committees=$pdo->query(
    "SELECT id,name
     FROM committees
     WHERE status='Active'
     ORDER BY name"
)->fetchAll();

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="cef-head">
<div>
<div class="cef-eyebrow"><i class="bi bi-sliders"></i> Step 8 · Administrative Configuration</div>
<h1>CEPFMS Configuration</h1>
<p>Manage citizen-engagement categories, default routing targets, and complaint service-level targets without editing SQL manually.</p>
</div>
<button class="btn btn-primary" id="btnNewCategory"><i class="bi bi-plus-lg"></i> New Category</button>
</div>

<div class="row g-3 mb-4">
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-tags"></i><div><strong><?= count($categories) ?></strong><small>Categories</small></div></div></div>
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-check-circle"></i><div><strong><?= count(array_filter($categories,fn($x)=>(int)$x['is_active']===1)) ?></strong><small>Active Categories</small></div></div></div>
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-stopwatch"></i><div><strong><?= count($serviceLevels) ?></strong><small>Complaint SLA Rules</small></div></div></div>
</div>

<div class="card cef-card mb-4">
<div class="card-header">Citizen Engagement Categories</div>
<div class="table-responsive">
<table class="table cef-table mb-0">
<thead><tr><th>Category</th><th>Type</th><th>Default Office</th><th>Default Committee</th><th>Usage</th><th>Status</th><th class="text-end">Action</th></tr></thead>
<tbody>
<?php foreach($categories as $c): ?>
<tr>
<td><strong><?= e($c['name']) ?></strong><div class="small text-muted"><?= e($c['description']?:'No description') ?></div></td>
<td><?= e($c['category_type']) ?></td>
<td><?= e($c['office_name']?:'None') ?></td>
<td><?= e($c['committee_name']?:'None') ?></td>
<td><?= (int)$c['usage_count'] ?> record(s)</td>
<td><span class="cef-status <?= $c['is_active']?'good':'bad' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span></td>
<td class="text-end">
<div class="btn-group btn-group-sm">
<button class="btn btn-outline-primary edit-category"
    data-id="<?= (int)$c['id'] ?>"
    data-name="<?= e($c['name']) ?>"
    data-type="<?= e($c['category_type']) ?>"
    data-description="<?= e($c['description']?:'') ?>"
    data-parent="<?= (int)($c['parent_id']??0) ?>"
    data-office="<?= (int)($c['default_office_id']??0) ?>"
    data-committee="<?= (int)($c['default_committee_id']??0) ?>"
    data-active="<?= (int)$c['is_active'] ?>">
<i class="bi bi-pencil"></i>
</button>
<button class="btn <?= $c['is_active']?'btn-outline-danger':'btn-outline-success' ?> category-status"
    data-id="<?= (int)$c['id'] ?>"
    data-active="<?= $c['is_active']?0:1 ?>">
<i class="bi <?= $c['is_active']?'bi-pause-circle':'bi-play-circle' ?>"></i>
</button>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="card cef-card">
<div class="card-header">Complaint Service-Level Targets</div>
<div class="card-body">
<div class="cef-alert mb-3">
These values determine the acknowledgement, response, and resolution target timestamps created for new complaints. Existing complaints keep the SLA targets they received when submitted.
</div>
<div class="table-responsive">
<table class="table cef-table mb-0">
<thead><tr><th>Urgency</th><th>Acknowledgement Hours</th><th>Response Hours</th><th>Resolution Hours</th><th>Status</th><th class="text-end">Edit</th></tr></thead>
<tbody>
<?php foreach($serviceLevels as $s): ?>
<tr>
<td><strong><?= e($s['urgency_level']) ?></strong></td>
<td><?= (int)$s['acknowledgement_hours'] ?></td>
<td><?= (int)$s['response_hours'] ?></td>
<td><?= (int)$s['resolution_hours'] ?></td>
<td><span class="cef-status <?= $s['is_active']?'good':'bad' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
<td class="text-end"><button class="btn btn-sm btn-outline-primary edit-sla"
 data-id="<?= (int)$s['id'] ?>"
 data-urgency="<?= e($s['urgency_level']) ?>"
 data-ack="<?= (int)$s['acknowledgement_hours'] ?>"
 data-response="<?= (int)$s['response_hours'] ?>"
 data-resolution="<?= (int)$s['resolution_hours'] ?>"
 data-active="<?= (int)$s['is_active'] ?>"><i class="bi bi-pencil"></i></button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

</main>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 560px;"><div class="modal-content border-0 shadow-lg rounded-3">
<form id="categoryForm">
<?= csrfField() ?>
<input type="hidden" name="category_id" id="categoryId" value="0">
<div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #0f172a, #1e293b);"><div class="d-flex align-items-center gap-2"><div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-10 text-white rounded-circle" style="width: 28px; height: 28px;"><i class="bi bi-tag fs-6"></i></div><h6 class="modal-title fw-bold mb-0 text-white">Citizen Engagement Category</h6></div><button class="btn-close btn-close-white btn-sm" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3">
<div class="row g-2">
<div class="col-md-8"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Category Name *</label><input class="form-control form-control-sm" name="name" id="categoryName" maxlength="180" required></div>
<div class="col-md-4"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Category Type</label><select class="form-select form-select-sm" name="category_type" id="categoryType"><option>All</option><option>Feedback</option><option>Proposal</option><option>Complaint</option></select></div>
<div class="col-12"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Description</label><textarea class="form-control form-control-sm" name="description" id="categoryDescription" rows="2" style="min-height: 50px; max-height: 100px; font-size: 0.82rem;"></textarea></div>
<div class="col-md-6"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Default Office</label><select class="form-select form-select-sm" name="default_office_id" id="categoryOffice"><option value="">None</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Default Committee</label><select class="form-select form-select-sm" name="default_committee_id" id="categoryCommittee"><option value="">None</option><?php foreach($committees as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="categoryActive" checked><label class="form-check-label small" for="categoryActive">Active category</label></div></div>
</div>
</div>
<div class="modal-footer py-2 px-3 bg-light border-top"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary px-3 fw-semibold">Save Category</button></div>
</form>
</div></div>
</div>

<div class="modal fade" id="slaModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered" style="max-width: 480px;"><div class="modal-content border-0 shadow-lg rounded-3">
<form id="slaForm">
<?= csrfField() ?>
<input type="hidden" name="service_level_id" id="slaId">
<div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #0f172a, #1e293b);"><div class="d-flex align-items-center gap-2"><div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-10 text-white rounded-circle" style="width: 28px; height: 28px;"><i class="bi bi-clock-history fs-6"></i></div><h6 class="modal-title fw-bold mb-0 text-white">Complaint SLA Rule</h6></div><button class="btn-close btn-close-white btn-sm" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3">
<div class="mb-2"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Urgency Level</label><input class="form-control form-control-sm bg-light" id="slaUrgency" disabled></div>
<div class="mb-2"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Acknowledgement Hours *</label><input type="number" min="1" max="8760" class="form-control form-control-sm" name="acknowledgement_hours" id="slaAck" required></div>
<div class="mb-2"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Response Hours *</label><input type="number" min="1" max="8760" class="form-control form-control-sm" name="response_hours" id="slaResponse" required></div>
<div class="mb-2"><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Resolution Hours *</label><input type="number" min="1" max="8760" class="form-control form-control-sm" name="resolution_hours" id="slaResolution" required></div>
<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="slaActive"><label class="form-check-label small" for="slaActive">Active SLA rule</label></div>
</div>
<div class="modal-footer py-2 px-3 bg-light border-top"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary px-3 fw-semibold">Save SLA Rule</button></div>
</form>
</div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const categoryModal=new bootstrap.Modal(document.getElementById('categoryModal'));
 const slaModal=new bootstrap.Modal(document.getElementById('slaModal'));
 const categoryForm=document.getElementById('categoryForm');
 const slaForm=document.getElementById('slaForm');

 document.getElementById('btnNewCategory').onclick=()=>{
   categoryForm.reset();
   categoryId.value='0';
   categoryActive.checked=true;
   categoryModal.show();
 };

 document.querySelectorAll('.edit-category').forEach(b=>b.onclick=function(){
   categoryId.value=this.dataset.id;
   categoryName.value=this.dataset.name;
   categoryType.value=this.dataset.type;
   categoryDescription.value=this.dataset.description;
   categoryOffice.value=this.dataset.office==='0'?'':this.dataset.office;
   categoryCommittee.value=this.dataset.committee==='0'?'':this.dataset.committee;
   categoryActive.checked=this.dataset.active==='1';
   categoryModal.show();
 });

 categoryForm.onsubmit=async e=>{
   e.preventDefault();
   const r=await fetch('ajax_category_save.php',{
     method:'POST',
     headers:{'X-Requested-With':'XMLHttpRequest'},
     body:new FormData(categoryForm)
   }).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('Category',r.message,'error');
 };

 document.querySelectorAll('.category-status').forEach(b=>b.onclick=async function(){
   const c=await Swal.fire({
     title:(this.dataset.active==='1'?'Activate':'Deactivate')+' this category?',
     text:'Existing citizen records will retain their category.',
     showCancelButton:true
   });
   if(!c.isConfirmed)return;
   const fd=new FormData();
   fd.append('csrf_token','<?= e(csrfToken()) ?>');
   fd.append('category_id',this.dataset.id);
   fd.append('is_active',this.dataset.active);
   const r=await fetch('ajax_category_status.php',{
     method:'POST',
     headers:{'X-Requested-With':'XMLHttpRequest'},
     body:fd
   }).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('Category',r.message,'error');
 });

 document.querySelectorAll('.edit-sla').forEach(b=>b.onclick=function(){
   slaId.value=this.dataset.id;
   slaUrgency.value=this.dataset.urgency;
   slaAck.value=this.dataset.ack;
   slaResponse.value=this.dataset.response;
   slaResolution.value=this.dataset.resolution;
   slaActive.checked=this.dataset.active==='1';
   slaModal.show();
 });

 slaForm.onsubmit=async e=>{
   e.preventDefault();
   const r=await fetch('ajax_sla_save.php',{
     method:'POST',
     headers:{'X-Requested-With':'XMLHttpRequest'},
     body:new FormData(slaForm)
   }).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('SLA Configuration',r.message,'error');
 };
});
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
