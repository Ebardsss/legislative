<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.proposals.view');
$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);if(!$s||$s['submission_type']!=='Proposal'){setFlash('warning','Proposal not found.');redirect(appUrl('modules/proposals/index.php'));}
$p=cefProposalRow($pdo,$id)?:[];
$ref=$pdo->prepare("SELECT r.*,li.reference_number,li.title legislative_title,u.full_name referred_name FROM cef_legislative_referrals r JOIN legislative_items li ON li.id=r.legislative_item_id LEFT JOIN users u ON u.id=r.referred_by WHERE r.submission_id=:id ORDER BY r.referred_at DESC");$ref->execute([':id'=>$id]);$referrals=$ref->fetchAll();
$docsQ=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM cef_submission_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.submission_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$docsQ->execute([':id'=>$id]);$docs=$docsQ->fetchAll();
$h=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM cef_submission_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.submission_id=:id ORDER BY h.created_at DESC,h.id DESC");$h->execute([':id'=>$id]);$history=$h->fetchAll();
$categories=$pdo->query("SELECT id,name FROM cef_categories WHERE is_active=1 ORDER BY name")->fetchAll();
$items=$pdo->query("SELECT id,reference_number,title,current_status FROM legislative_items WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 300")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();

$convertedLegItem = null;
if (!empty($p['converted_legislative_item_id'])) {
    $cStmt = $pdo->prepare("SELECT id, reference_number, title, current_status FROM legislative_items WHERE id = ?");
    $cStmt->execute([$p['converted_legislative_item_id']]);
    $convertedLegItem = $cStmt->fetch();
}

$pageTitle=$s['reference_number'];$activeMenu='proposals';$extraCss=[appUrl('assets/css/cepfms-operational.css')];include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head">
  <div>
    <a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Proposal Registry</a>
    <div class="cef-eyebrow mt-2"><?= e($s['reference_number']) ?> · Proposal / Suggestion</div>
    <h1><?= e($s['title']) ?></h1>
    <p><?= e($s['category_name']?:'Unclassified') ?> · Received <?= formatDateTime($s['created_at']) ?></p>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <?php if ($convertedLegItem): ?>
      <span class="badge bg-success py-2 px-3"><i class="bi bi-check2-all me-1"></i> Ordinance: <?= e($convertedLegItem['reference_number']) ?></span>
    <?php elseif(cefHasPermission('cepfms.proposals.manage')): ?>
      <button type="button" class="btn btn-warning text-dark fw-semibold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#convertOrdinanceModal">
        <i class="bi bi-file-earmark-plus me-1"></i> Convert to Draft Ordinance
      </button>
    <?php endif; ?>
    <span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a>
  </div>
</div>

<?php if ($convertedLegItem): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center py-2.5 px-3 mb-3" style="border-radius: 10px;">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-bank2 fs-4"></i>
      <div>
        <strong>Formally Converted into Sangguniang Legislative Item: <?= e($convertedLegItem['reference_number']) ?></strong>
        <div class="small text-dark opacity-75"><?= e($convertedLegItem['title']) ?> · Status: <?= e($convertedLegItem['current_status']) ?></div>
      </div>
    </div>
    <span class="badge bg-success text-white">Active in Council Pipeline</span>
  </div>
<?php endif; ?>

<?php if ($s['moderation_status'] !== 'Validated'): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center py-2.5 px-3 mb-3 border-warning shadow-sm" style="border-radius: 10px;">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-shield-exclamation fs-3 text-warning"></i>
      <div>
        <strong class="text-dark">Intake Moderation Required (Current: <?= e($s['moderation_status']) ?>)</strong>
        <div class="small text-muted">Validation, rejection, and duplicate decisions are processed in the Moderation &amp; Validation Desk.</div>
      </div>
    </div>
    <a href="../moderation/view.php?id=<?= $id ?>" class="btn btn-warning text-dark btn-sm fw-bold">
      <i class="bi bi-shield-check me-1"></i> Open Moderation Desk
    </a>
  </div>
<?php endif; ?>

<div class="row g-3"><div class="col-xl-8">
<div class="card cef-card mb-3"><div class="card-header">Citizen Proposal</div><div class="card-body"><div class="cef-grid"><div><small>Citizen</small><strong><?= $s['anonymous_flag']?'Anonymous':e($s['citizen_name']?:'Not provided') ?></strong></div><div><small>Priority</small><strong><?= e($s['priority_level']) ?></strong></div><div><small>Estimated Scope</small><strong><?= e($p['estimated_scope']??'—') ?></strong></div><div><small>Feasibility</small><strong><?= e($p['feasibility_status']??'Not Reviewed') ?></strong></div><div><small>Disposition</small><strong><?= e($p['disposition']??'—') ?></strong></div><div><small>Moderation</small><strong><?= e($s['moderation_status']) ?></strong></div></div><div class="cef-alert mt-3"><strong>Submission Details</strong><br><?= nl2br(e($s['details'])) ?></div><?php foreach([['Problem / Opportunity',$p['problem_statement']??''],['Proposed Solution',$p['proposed_solution']??''],['Expected Public Benefit',$p['expected_public_benefit']??'']] as [$label,$value]): ?><?php if($value): ?><div class="cef-alert mt-3"><strong><?= e($label) ?></strong><br><?= nl2br(e($value)) ?></div><?php endif; ?><?php endforeach; ?></div></div>

<!-- CITIZEN PETITION ENDORSEMENTS -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-people-fill text-primary me-1"></i> <strong>Citizen Endorsements &amp; Petition Support</strong></span>
    <button type="button" class="btn btn-sm btn-outline-primary" id="btnEndorseProposal" data-id="<?= $id ?>">
      <i class="bi bi-hand-thumbs-up me-1"></i> Endorse Proposal (+1)
    </button>
  </div>
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <span class="small fw-semibold text-secondary">Council Agenda Milestone Progress</span>
      <span class="small fw-bold text-dark"><span id="endorseCountText"><?= (int)($p['endorsement_count'] ?? 0) ?></span> / 100 Citizen Endorsements</span>
    </div>
    <?php $pct = min(100, (int)((($p['endorsement_count'] ?? 0) / 100) * 100)); ?>
    <div class="progress" style="height: 10px; border-radius: 6px;">
      <div class="progress-bar bg-success progress-bar-striped" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <small class="text-muted d-block mt-2" style="font-size: 0.74rem;">Proposals reaching 100+ endorsements are automatically prioritized for review by the Committee on Rules and Legislative Management.</small>
  </div>
</div>


<div class="card cef-card mb-3"><div class="card-header">Legislative Referrals</div><div class="card-body d-grid gap-2"><?php if(!$referrals): ?><div class="text-muted small">No legislative referral yet.</div><?php endif; ?><?php foreach($referrals as $r): ?><div class="cef-person"><div><strong><?= e($r['reference_number'].' · '.$r['legislative_title']) ?></strong><small><?= e($r['referral_type'].' · '.$r['status']) ?> · <?= formatDateTime($r['referred_at']) ?> · <?= e($r['referred_name']?:'System') ?></small></div></div><?php endforeach; ?></div></div>
<div class="card cef-card mb-3"><div class="card-header">Proposal Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$docs): ?><div class="list-group-item text-muted">No supporting documents.</div><?php endif; ?><?php foreach($docs as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php if(cefHasPermission('cepfms.proposals.manage')): ?><div class="d-flex justify-content-end mt-1 mb-2"><button type="button" class="btn btn-link btn-sm p-0 doc-visibility-btn" data-id="<?= (int)$doc['id'] ?>" data-scope="submission" data-next="<?= $doc['visibility']==='Public'?'Internal':'Public' ?>"><?= $doc['visibility']==='Public'?'Make Internal Only':'Make Citizen Visible' ?></button></div><?php endif; ?><?php endforeach; ?></div><?php if(cefHasPermission('cepfms.proposals.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><div class="col-md-4"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Proposal Study Document"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option value="Internal">Internal Only</option><option value="Public">Citizen Visible</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
<?php $ticketChatPermission='cepfms.proposals.manage'; include __DIR__.'/../../includes/cepfms_ticket_chat.php'; ?>
</div>
<div class="col-xl-4">
<?php if(cefHasPermission('cepfms.proposals.manage')): ?><div class="card cef-card mb-3"><div class="card-header">Assessment</div><div class="card-body"><form id="updateForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><label class="form-label">Category</label><select class="form-select mb-2" name="category_id"><option value="">Unclassified</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$s['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><label class="form-label">Priority</label><select class="form-select mb-2" name="priority_level"><?php foreach(cefPriorityLevels() as $x): ?><option <?= $s['priority_level']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select><label class="form-label">Feasibility</label><select class="form-select mb-2" name="feasibility_status"><?php foreach(['Not Reviewed','For Study','Feasible','Needs Revision','Not Feasible','Referred'] as $x): ?><option <?= ($p['feasibility_status']??'Not Reviewed')===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select><label class="form-label">Disposition</label><input class="form-control mb-2" name="disposition" value="<?= e($p['disposition']??'') ?>"><?php $proposalStatuses=$s['moderation_status']==='Validated'
    ? ['Validated','Assigned','In Progress','Responded','Resolved','Closed','Withdrawn']
    : ['Submitted','Under Moderation','Awaiting Citizen','Withdrawn']; ?>
<label class="form-label">Status</label><select class="form-select mb-2" name="status"><?php foreach($proposalStatuses as $x): ?><option <?= $s['status']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select><label class="form-label">Note</label><textarea class="form-control mb-2" name="note" rows="2"></textarea><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="public_visible" value="1" id="pub"><label class="form-check-label" for="pub">Show update in citizen tracking</label></div><button type="submit" class="btn btn-primary w-100" id="btnSaveAssessment"><i class="bi bi-check2-circle me-1"></i> Save Assessment</button></form></div></div>
<div class="card cef-card mb-3"><div class="card-header">Refer to Existing Legislative Item</div><div class="card-body"><form id="referralForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><label class="form-label">Legislative Item</label><select class="form-select mb-2" name="legislative_item_id" required><option value="">Select item</option><?php foreach($items as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title'].' · '.$x['current_status']) ?></option><?php endforeach; ?></select><label class="form-label">Referral Note</label><textarea class="form-control mb-2" name="notes" rows="2"></textarea><button class="btn btn-outline-primary w-100">Create / Update Referral</button></form></div></div><?php endif; ?>
<div class="card cef-card"><div class="card-header">Proposal History</div><div class="card-body cef-timeline"><?php foreach($history as $x): ?><div><strong><?= e($x['action']) ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?= $x['details']?'<br>'.e($x['details']):'' ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div></div>
</main></div>

<!-- CONVERT TO DRAFT ORDINANCE MODAL -->
<div class="modal fade" id="convertOrdinanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="convertOrdinanceForm">
        <?= csrfField() ?>
        <input type="hidden" name="submission_id" value="<?= $id ?>">
        <div class="modal-header py-2.5 px-3.5" style="background: #0F2137; border-bottom: 2px solid #a97900; color: #ffffff;">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-bank2 text-warning fs-5"></i>
            <div>
              <h6 class="modal-title fw-bold mb-0 text-white" style="font-size: 0.95rem;">Convert Proposal to Draft Ordinance</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Legislation Bridge · City Council of Manila</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="alert alert-info py-2 px-3 small mb-3">
            <i class="bi bi-info-circle-fill me-1"></i> This action will create a formal legislative draft item in the Sanggunian Legislative Pipeline (LACMS / ORLMS) based on this citizen's proposal.
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Legislation Type</label>
            <select class="form-select form-select-sm" name="item_type_id">
              <option value="1" selected>Ordinance (City Law / Mandate)</option>
              <option value="2">Resolution (Formal Declaration / Request)</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Draft Ordinance Title *</label>
            <input type="text" class="form-control form-control-sm" name="title" value="An Ordinance <?= e($s['title']) ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Referred Committee</label>
            <select class="form-select form-select-sm" name="committee_id">
              <option value="">-- Select Committee --</option>
              <?php foreach($committees as $cm): ?>
                <option value="<?= (int)$cm['id'] ?>"><?= e($cm['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="p-2 border rounded bg-light small">
            <strong class="d-block text-secondary mb-1">Pre-filled Rationale &amp; Benefits:</strong>
            <div class="text-muted" style="max-height: 80px; overflow-y: auto;">
              <?= nl2br(e($p['problem_statement'] ?: $s['details'])) ?>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold" id="btnSubmitConvert">
            <i class="bi bi-check2-circle me-1"></i> Generate Draft Ordinance
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if(cefHasPermission('cepfms.proposals.manage')): ?><script>
document.addEventListener('DOMContentLoaded',()=>{
 const u=document.getElementById('updateForm'),r=document.getElementById('referralForm'),d=document.getElementById('docForm');
 if(u) u.onsubmit=async e=>{
   e.preventDefault();
   const btn = u.querySelector('button[type=submit]') || u.querySelector('#btnSaveAssessment');
   const origHtml = btn ? btn.innerHTML : '';
   if(btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...'; }
   try {
     const res = await fetch('ajax_update.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(u)}).then(z=>z.json());
     if(res.success) {
       await Swal.fire({icon: 'success', title: 'Assessment Saved', text: res.message, timer: 1200, showConfirmButton: false});
       location.reload();
     } else {
       Swal.fire('Assessment Error', res.message, 'error');
     }
   } catch(err) {
     Swal.fire('Assessment Error', 'Request failed: ' + err.message, 'error');
   } finally {
     if(btn) { btn.disabled = false; btn.innerHTML = origHtml; }
   }
 };
 if(r) r.onsubmit=async e=>{e.preventDefault();const x=await fetch('ajax_referral.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(r)}).then(z=>z.json());if(x.success)location.reload();else Swal.fire('Referral Error',x.message,'error');};
 if(d) d.onsubmit=async e=>{e.preventDefault();const x=await fetch('ajax_upload.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(d)}).then(z=>z.json());if(x.success)location.reload();else Swal.fire('Upload Error',x.message,'error');};

 const convertForm = document.getElementById('convertOrdinanceForm');
 if(convertForm) {
   convertForm.onsubmit = async e => {
     e.preventDefault();
     const btn = document.getElementById('btnSubmitConvert');
     btn.disabled = true;
     btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
     const res = await fetch('ajax_convert_ordinance.php', { method: 'POST', body: new FormData(convertForm) }).then(z => z.json());
     btn.disabled = false;
     btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Generate Draft Ordinance';
     if (res.success) {
       await Swal.fire('Converted!', res.message, 'success');
       location.reload();
     } else {
       Swal.fire('Conversion Error', res.message, 'error');
     }
   };
 }

 const btnEndorse = document.getElementById('btnEndorseProposal');
 if(btnEndorse) {
   btnEndorse.addEventListener('click', async () => {
     const fd = new FormData();
     fd.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
     fd.append('submission_id', btnEndorse.dataset.id);
     const res = await fetch('ajax_proposal_endorse.php', { method: 'POST', body: fd }).then(z => z.json());
     if(res.success) {
       document.getElementById('endorseCountText').textContent = res.new_count;
       Swal.fire('Endorsement Added', res.message, 'success');
     }
   });
 }

 document.querySelectorAll('.doc-visibility-btn').forEach(btn=>btn.addEventListener('click',async()=>{
   const fd=new FormData();fd.append('csrf_token',document.querySelector('input[name=csrf_token]').value);fd.append('scope',btn.dataset.scope);fd.append('document_id',btn.dataset.id);fd.append('visibility',btn.dataset.next);
   const x=await fetch('../shared/ajax_document_visibility.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(z=>z.json());
   if(x.success)location.reload();else Swal.fire('Attachment Visibility',x.message,'error');
 }));
});
</script><?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
