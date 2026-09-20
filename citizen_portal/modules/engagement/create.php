<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$pageTitle='New Citizen Submission';
$activeMenu='engagement';
$extraCss=[appUrl('assets/css/public-modules.css')];

$categories=$pdo->query(
    'SELECT id,name,category_type
     FROM cef_categories
     WHERE is_active=1
     ORDER BY category_type,name'
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/engagement/index.php')) ?>"><i class="bi bi-arrow-left"></i> My Engagement</a>
<h1>New Citizen Submission</h1>
<p>Send public feedback, a proposal/suggestion or a complaint using your signed-in account.</p>
</div>
</div>

<div class="card portal-card">
<div class="card-header">Submission Form</div>
<div class="card-body">
<form method="post" action="<?= e(appUrl('modules/engagement/save.php')) ?>" id="engagementForm" enctype="multipart/form-data">
<?= csrfField() ?>
<div class="row g-3">
<div class="col-md-4">
<label class="form-label">Submission Type</label>
<select class="form-select" name="submission_type" id="submissionType" required>
<option>Feedback</option><option>Proposal</option><option selected>Complaint</option>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Category</label>
<select class="form-select" name="category_id" id="categorySelect">
<option value="">Select category</option>
<?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" data-type="<?= e($c['category_type']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Priority</label>
<select class="form-select" name="priority_level"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select>
</div>
<div class="col-12"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="255" required placeholder="e.g. Uncollected Garbage / Broken Streetlight"></div>
<div class="col-12"><label class="form-label">Short Summary</label><textarea class="form-control" name="summary" rows="2" placeholder="Brief summary of the issue"></textarea></div>
<div class="col-12"><label class="form-label">Full Details</label><textarea class="form-control" name="details" rows="6" required placeholder="Provide complete details, date/time, and specific concern..."></textarea></div>

<div class="col-12 type-fields feedback-fields d-none">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Feedback Kind</label><input class="form-control" name="feedback_kind" value="General Feedback"></div>
<div class="col-md-6"><label class="form-label">Service Area</label><input class="form-control" name="service_area"></div>
<div class="col-md-8"><label class="form-label">Desired Outcome</label><textarea class="form-control" name="desired_outcome" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Rating (optional)</label><select class="form-select" name="citizen_rating"><option value="">No rating</option><?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
</div>
</div>

<div class="col-12 type-fields proposal-fields d-none">
<div class="row g-3">
<div class="col-12"><label class="form-label">Problem / Opportunity</label><textarea class="form-control" name="problem_statement" rows="2"></textarea></div>
<div class="col-12"><label class="form-label">Proposed Solution</label><textarea class="form-control" name="proposed_solution" rows="3"></textarea></div>
<div class="col-md-8"><label class="form-label">Expected Public Benefit</label><textarea class="form-control" name="expected_public_benefit" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Estimated Scope</label><input class="form-control" name="estimated_scope"></div>
</div>
</div>

<div class="col-12 type-fields complaint-fields">
  <div class="card p-3 border-0 bg-light rounded-3 mb-2" style="border-left: 4px solid #dc3545 !important;">
    <h6 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-octagon me-1"></i> Complaint Details &amp; Verification Proof</h6>
    <div class="row g-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold">Affected Service / Office</label>
        <input class="form-control" name="affected_service" placeholder="e.g. Waste Management, Road Works, Traffic Management">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Incident Date/Time</label>
        <input type="datetime-local" class="form-control" name="incident_datetime">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Urgency Level</label>
        <select class="form-select" name="urgency_level"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select>
      </div>

      <!-- FULL LOCATION ADDRESS (CRITICAL) -->
      <div class="col-12 mt-3 pt-2 border-top">
        <label class="form-label fw-bold text-dark mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Exact Location &amp; Full Address <span class="text-danger">*</span></label>
        <div class="small text-muted mb-2">Siguraduhing kumpleto ang address upang mabilis itong mapuntahan at maaksyunan ng dispatch team.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Full Street Address / Landmark <span class="text-danger">*</span></label>
        <input class="form-control" name="location_text" id="complaintLocationText" placeholder="e.g. Corner Taft Ave & Pedro Gil St, in front of PGH Gate 2" required>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Barangay</label>
        <input class="form-control" name="barangay" id="complaintBarangay" placeholder="e.g. Barangay 660-A">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">District / Landmark Zone</label>
        <input class="form-control" name="district" id="complaintDistrict" placeholder="e.g. District 5 / Ermita">
      </div>

      <!-- PHOTO / EVIDENCE UPLOAD (CRITICAL) -->
      <div class="col-12 mt-3 pt-2 border-top">
        <label class="form-label fw-bold text-dark mb-1"><i class="bi bi-camera-fill text-primary me-1"></i> Upload Incident Photo / Image Evidence</label>
        <div class="small text-muted mb-2">Mag-upload ng larawan bilang patunay ng inirereklamong insidente (JPG, PNG, WEBP).</div>
        <input type="file" class="form-control" name="evidence" id="evidenceFile" accept="image/jpeg,image/png,image/jpg,image/webp">
        
        <!-- Live Image Preview -->
        <div id="imagePreviewContainer" class="mt-2 d-none">
          <div class="d-flex align-items-center gap-3 p-2 border rounded bg-white shadow-sm" style="max-width: 420px;">
            <img id="evidenceImagePreview" src="#" alt="Evidence Preview" style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6;">
            <div class="overflow-hidden">
              <span class="d-block small fw-bold text-truncate" id="previewFileName">photo.jpg</span>
              <span class="badge bg-success-subtle text-success mt-1"><i class="bi bi-check-circle-fill me-1"></i> Image Attached Successfully</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="col-12">
<div class="safe-note"><i class="bi bi-shield-check"></i><div><strong>Privacy consent</strong><span>Your registered citizen account is linked to this submission so you can follow its status securely. Staff-only moderation notes are not displayed here.</span></div></div>
<input type="hidden" name="privacy_consent" value="1">
</div>

<div class="col-12 d-flex justify-content-end gap-2">
<a class="btn btn-outline-secondary" href="<?= e(appUrl('modules/engagement/index.php')) ?>">Cancel</a>
<button class="btn btn-primary"><i class="bi bi-send"></i> Submit Complaint</button>
</div>
</div>
</form>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const type=document.getElementById('submissionType');
 const category=document.getElementById('categorySelect');
 const locationInput=document.getElementById('complaintLocationText');
 const sync=()=>{
   const value=type.value;
   document.querySelector('.feedback-fields').classList.toggle('d-none',value!=='Feedback');
   document.querySelector('.proposal-fields').classList.toggle('d-none',value!=='Proposal');
   document.querySelector('.complaint-fields').classList.toggle('d-none',value!=='Complaint');
   if(locationInput) {
     locationInput.required = (value === 'Complaint');
   }
   [...category.options].forEach((o,i)=>{
     if(i===0)return;
     const t=o.dataset.type||'All';
     o.hidden=t!=='All'&&t!==value;
     o.disabled=o.hidden;
   });
   if(category.selectedOptions[0]?.disabled)category.value='';
 };
 type.addEventListener('change',sync);sync();

 // Live image preview handler
 const evidenceFile = document.getElementById('evidenceFile');
 const previewContainer = document.getElementById('imagePreviewContainer');
 const previewImg = document.getElementById('evidenceImagePreview');
 const previewName = document.getElementById('previewFileName');
 if(evidenceFile) {
   evidenceFile.addEventListener('change', () => {
     const file = evidenceFile.files[0];
     if (file && file.type.startsWith('image/')) {
       const reader = new FileReader();
       reader.onload = (e) => {
         previewImg.src = e.target.result;
         previewName.textContent = file.name;
         previewContainer.classList.remove('d-none');
       };
       reader.readAsDataURL(file);
     } else {
       previewContainer.classList.add('d-none');
     }
   });
 }
});
</script>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
