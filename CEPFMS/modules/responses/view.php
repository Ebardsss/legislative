<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.view');

$pdo=db();$id=(int)($_GET['id']??0);$r=cefResponseRow($pdo,$id);
if(!$r){setFlash('warning','Response not found.');redirect(appUrl('modules/responses/index.php'));}

$historyQ=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name
  FROM cef_response_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.response_id=:id
  ORDER BY h.created_at DESC,h.id DESC"
);
$historyQ->execute([':id'=>$id]);$history=$historyQ->fetchAll();

$docsQ=$pdo->prepare(
 "SELECT d.*,u.full_name uploaded_name
  FROM cef_response_documents d
  LEFT JOIN users u ON u.id=d.uploaded_by
  WHERE d.response_id=:id
  ORDER BY d.uploaded_at DESC,d.id DESC"
);
$docsQ->execute([':id'=>$id]);$docs=$docsQ->fetchAll();

$followQ=$pdo->prepare(
 "SELECT f.*,u.full_name sender_account_name
  FROM cef_followups f
  LEFT JOIN users u ON u.id=f.sender_user_id
  WHERE f.submission_id=:submission
  ORDER BY f.created_at DESC,f.id DESC"
);
$followQ->execute([':submission'=>$r['submission_id']]);$followups=$followQ->fetchAll();

$assignmentQ=$pdo->prepare(
 "SELECT a.*,o.name office_name,c.name committee_name,u.full_name assigned_name
  FROM cef_assignments a
  LEFT JOIN offices o ON o.id=a.office_id
  LEFT JOIN committees c ON c.id=a.committee_id
  LEFT JOIN users u ON u.id=a.assigned_user_id
  WHERE a.submission_id=:submission
    AND a.status<>'Cancelled'
  ORDER BY a.assignment_role='Primary' DESC,a.id DESC"
);
$assignmentQ->execute([':submission'=>$r['submission_id']]);$assignments=$assignmentQ->fetchAll();

$cannedResponses = $pdo->query("SELECT * FROM cef_canned_templates WHERE template_type = 'Response' ORDER BY category ASC, title ASC")->fetchAll();

$pageTitle=$r['response_reference'];$activeMenu='responses';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head">
  <div>
    <a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Response Queue</a>
    <div class="cef-eyebrow mt-2"><?= e($r['response_reference']) ?> · <?= e($r['response_type']) ?></div>
    <h1><?= e($r['subject']) ?></h1>
    <p>For <?= e($r['submission_reference'].' · '.$r['submission_title']) ?></p>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <a class="btn btn-sm btn-outline-primary shadow-sm" target="_blank" href="export_official_response.php?id=<?= $id ?>">
      <i class="bi bi-file-earmark-pdf me-1"></i> Official Letterhead PDF
    </a>
    <span class="cef-status <?= e(cefStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a>
  </div>
</div>

<!-- TWO-TIER APPROVAL & SIGN-OFF PIPELINE -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
  <div class="card-body p-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <span class="small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px;">Multi-Tier Executive Approval &amp; Sign-Off Matrix</span>
      <span class="badge <?= $r['status'] === 'Delivered' ? 'bg-success' : ($r['status'] === 'Approved' ? 'bg-primary' : 'bg-warning text-dark') ?>">
        Stage: <?= e($r['status']) ?>
      </span>
    </div>
    <div class="row g-2 text-center">
      <div class="col-4 p-2 border rounded <?= in_array($r['status'], ['Draft', 'Under Review', 'Approved', 'Delivered'], true) ? 'bg-light border-primary' : 'bg-light' ?>">
        <i class="bi bi-pencil-square text-primary d-block fs-5"></i>
        <strong class="d-block small text-dark mt-1">1. Draft Prepared</strong>
        <small class="text-muted" style="font-size: 0.72rem;"><?= formatDateTime($r['created_at']) ?></small>
      </div>
      <div class="col-4 p-2 border rounded <?= in_array($r['status'], ['Under Review', 'Approved', 'Delivered'], true) ? 'bg-light border-warning' : 'bg-light opacity-50' ?>">
        <i class="bi bi-clipboard-check text-warning d-block fs-5"></i>
        <strong class="d-block small text-dark mt-1">2. Division Review</strong>
        <small class="text-muted" style="font-size: 0.72rem;"><?= !empty($r['reviewed_at']) ? formatDateTime($r['reviewed_at']) : 'Review in progress' ?></small>
      </div>
      <div class="col-4 p-2 border rounded <?= in_array($r['status'], ['Approved', 'Delivered'], true) ? 'bg-light border-success' : 'bg-light opacity-50' ?>">
        <i class="bi bi-patch-check-fill text-success d-block fs-5"></i>
        <strong class="d-block small text-dark mt-1">3. Executive Sign-Off</strong>
        <small class="text-muted" style="font-size: 0.72rem;"><?= !empty($r['approved_at']) ? formatDateTime($r['approved_at']) : 'Pending sign-off' ?></small>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
<div class="col-xl-8">

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between"><span>Official Response</span><?php if(cefHasPermission('cepfms.responses.manage')&&in_array($r['status'],['Draft','Returned'],true)): ?><button class="btn btn-sm btn-primary" id="btnEdit"><i class="bi bi-pencil me-1"></i> Edit Draft</button><?php endif; ?></div><div class="card-body">
<div class="cef-grid"><div><small>Submission</small><strong><?= e($r['submission_reference']) ?></strong></div><div><small>Submission Type</small><strong><?= e(cefSubmissionTypeLabel($r['submission_type'])) ?></strong></div><div><small>Submission Status</small><strong><?= e($r['submission_status']) ?></strong></div><div><small>Drafted</small><strong><?= formatDateTime($r['created_at']) ?></strong></div><div><small>Approved</small><strong><?= formatDateTime($r['approved_at']) ?></strong></div><div><small>Delivered</small><strong><?= formatDateTime($r['delivered_at']) ?></strong></div></div>
<div class="cef-alert mt-3"><?= nl2br(e($r['body'])) ?></div>
</div></div>

<div class="card cef-card mb-3"><div class="card-header">Assignments</div><div class="card-body d-grid gap-2"><?php if(!$assignments): ?><div class="text-muted small">No active assignment.</div><?php endif; ?><?php foreach($assignments as $a): ?><div class="cef-person"><div><strong><?= e($a['assigned_name']?:$a['office_name']?:$a['committee_name']?:'Assignment') ?></strong><small><?= e($a['assignment_role'].' · '.$a['status']) ?><?= $a['due_at']?' · Due '.formatDateTime($a['due_at']):'' ?></small></div></div><?php endforeach; ?></div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between"><span>Citizen / Council Follow-Ups</span></div><div class="card-body"><div class="cef-timeline mb-3"><?php if(!$followups): ?><div class="text-muted small">No follow-up communication.</div><?php endif; ?><?php foreach($followups as $f): ?><div><strong><?= e($f['direction']) ?> · <?= e($f['sender_account_name']?:$f['sender_name']?:'Citizen') ?><?= $f['public_visible']?' · Public':'' ?></strong><small><?= nl2br(e($f['message'])) ?><br><?= formatDateTime($f['created_at']) ?></small></div><?php endforeach; ?></div><?php if(cefHasPermission('cepfms.responses.manage')&&!cefTicketChatClosed((string)$r['submission_status'])): ?><form id="followForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= (int)$r['submission_id'] ?>"><input type="hidden" name="response_id" value="<?= $id ?>"><label class="form-label">Council Follow-Up</label><textarea class="form-control mb-2" name="message" rows="3" required></textarea><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="public_visible" value="1" id="publicFollow" checked><label class="form-check-label" for="publicFollow">Visible in citizen tracking</label></div><button class="btn btn-outline-primary btn-sm">Add Follow-Up</button></form><?php elseif(cefTicketChatClosed((string)$r['submission_status'])): ?><div class="alert alert-secondary small mb-0"><i class="bi bi-lock me-1"></i>This ticket is <?= e($r['submission_status']) ?>. The conversation is read-only.</div><?php endif; ?></div></div>

<div class="card cef-card mb-3"><div class="card-header">Response Attachments</div><div class="card-body"><div class="list-group mb-3"><?php if(!$docs): ?><div class="list-group-item text-muted">No response attachments.</div><?php endif; ?><?php foreach($docs as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.($doc['visibility']??'Internal')) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php if(cefHasPermission('cepfms.responses.manage')): ?><div class="d-flex justify-content-end mt-1 mb-2"><button type="button" class="btn btn-link btn-sm p-0 response-doc-visibility-btn" data-id="<?= (int)$doc['id'] ?>" data-next="<?= ($doc['visibility']??'Internal')==='Public'?'Internal':'Public' ?>"><?= ($doc['visibility']??'Internal')==='Public'?'Make Internal Only':'Make Citizen Visible' ?></button></div><?php endif; ?><?php endforeach; ?></div><?php if(cefHasPermission('cepfms.responses.manage')&&$r['status']!=='Cancelled'): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="response_id" value="<?= $id ?>"><div class="col-md-4"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Response Attachment"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option value="Internal">Internal Only</option><option value="Public">Citizen Visible</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>

</div>
<div class="col-xl-4">

<!-- CITIZEN SMS DISPATCH PREVIEW -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-chat-dots-fill text-success me-1"></i> <strong>Citizen SMS Dispatch Preview</strong></span>
    <span class="badge bg-light text-secondary border">GSM 160</span>
  </div>
  <div class="card-body p-3">
    <?php
      $trackUrl = citizenPortalUrl('track.php?ref=' . urlencode($r['submission_reference']));
      $smsPreview = "City of Manila LGU Advisory: Good day. An official response has been issued for your record " . $r['submission_reference'] . " (" . mb_strimwidth($r['subject'], 0, 30, '...') . "). View response: " . $trackUrl;
      $charLen = strlen($smsPreview);
    ?>
    <div class="p-2.5 rounded border bg-light text-dark font-monospace small mb-2" style="font-size: 0.78rem; line-height: 1.4; border-left: 4px solid #10b981 !important;">
      <i class="bi bi-phone text-muted me-1"></i> <?= e($smsPreview) ?>
    </div>
    <div class="d-flex justify-content-between align-items-center small text-muted" style="font-size: 0.72rem;">
      <span>Recipient: <?= e($r['citizen_phone'] ?: 'Registered Mobile Number') ?></span>
      <span>Length: <strong><?= $charLen ?></strong> chars</span>
    </div>
  </div>
</div>

<?php if(cefHasPermission('cepfms.responses.manage')): ?><div class="card cef-card mb-3"><div class="card-header">Response Workflow</div><div class="card-body d-grid gap-2">
<?php if(in_array($r['status'],['Draft','Returned'],true)): ?><button class="btn btn-primary response-action" data-action="submit_review">Submit for Review</button><?php endif; ?>
<?php if($r['status']==='Under Review'): ?><button class="btn btn-outline-warning response-action" data-action="return_draft">Return to Draft</button><?php if(cefHasPermission('cepfms.responses.approve')): ?><button class="btn btn-success response-action" data-action="approve">Approve Response</button><?php endif; ?><?php endif; ?>
<?php if($r['status']==='Approved'): ?><button class="btn btn-success response-action" data-action="deliver">Publish & Notify Citizen</button><?php endif; ?>
<?php if(!in_array($r['status'],['Delivered','Cancelled'],true)): ?><button class="btn btn-outline-danger response-action" data-action="cancel">Cancel Response</button><?php endif; ?>
</div></div><?php endif; ?>

<div class="card cef-card"><div class="card-header">Response History</div><div class="card-body cef-timeline"><?php foreach($history as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>

</div></div>
</main></div>

<?php if(cefHasPermission('cepfms.responses.manage')): ?>
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 620px;"><div class="modal-content shadow"><form id="editForm"><?= csrfField() ?><input type="hidden" name="response_id" value="<?= $id ?>"><input type="hidden" name="submission_id" value="<?= (int)$r['submission_id'] ?>"><input type="hidden" name="assignment_id" value="<?= (int)($r['assignment_id']??0) ?>"><div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-pencil-square me-1"></i> Edit Response Draft</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body p-3">
<div class="row g-2 mb-2"><div class="col-md-7"><label class="form-label small fw-bold mb-1">Response Type</label><select class="form-select form-select-sm" name="response_type"><?php foreach(['Acknowledgement','Progress Update','Clarification Request','Official Response','Resolution Notice','Final Response'] as $x): ?><option <?= $r['response_type']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><div class="col-md-5 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-warning w-100" id="btnAiDraftEdit"><i class="bi bi-stars"></i> Draft with Ollama</button></div></div>

<!-- PRE-APPROVED CANNED LEGAL TEMPLATES SELECTOR -->
<div class="mb-2">
  <label class="form-label small fw-bold mb-1 text-secondary"><i class="bi bi-journal-bookmark me-1"></i> Pre-Approved Legal / Policy Template</label>
  <select class="form-select form-select-sm" id="cannedResponseSelect">
    <option value="">-- Choose Standard LGU Template --</option>
    <?php foreach($cannedResponses as $cr): ?>
      <option value="<?= e($cr['content']) ?>" data-title="<?= e($cr['title']) ?>"><?= e($cr['category'].' · '.$cr['title']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<label class="form-label small fw-bold mb-1">Subject</label><input class="form-control form-control-sm mb-2" name="subject" value="<?= e($r['subject']) ?>" required><label class="form-label small fw-bold mb-1">Body</label><textarea class="form-control form-control-sm" name="body" id="responseBodyArea" rows="6" style="resize: vertical; min-height: 120px; max-height: 280px;" required><?= e($r['body']) ?></textarea></div><div class="modal-footer py-2 px-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save Draft</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const edit=document.getElementById('editForm'),follow=document.getElementById('followForm'),doc=document.getElementById('docForm');
 const modal=document.getElementById('editModal')?new bootstrap.Modal(document.getElementById('editModal')):null;
 document.getElementById('btnEdit')?.addEventListener('click',()=>modal.show());
 if(edit)edit.onsubmit=async e=>{e.preventDefault();const x=await fetch('ajax_draft.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(edit)}).then(z=>z.json());if(x.success)location.reload();else Swal.fire('Response Draft',x.message,'error');};
 if(follow)follow.onsubmit=async e=>{e.preventDefault();const x=await fetch('ajax_followup.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(follow)}).then(z=>z.json());if(x.success)location.reload();else Swal.fire('Follow-Up',x.message,'error');};
 if(doc)doc.onsubmit=async e=>{e.preventDefault();const x=await fetch('ajax_upload.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(doc)}).then(z=>z.json());if(x.success)location.reload();else Swal.fire('Upload',x.message,'error');};
 document.querySelectorAll('.response-action').forEach(b=>b.onclick=async function(){let notes='';if(['return_draft','cancel'].includes(this.dataset.action)){const c=await Swal.fire({title:this.dataset.action==='return_draft'?'Return response to draft?':'Cancel response?',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:v=>!v?'Reason is required':undefined});if(!c.isConfirmed)return;notes=c.value;}else{const c=await Swal.fire({title:this.dataset.action==='deliver'?'Publish this official response to citizen tracking?':'Continue response workflow?',showCancelButton:true});if(!c.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('response_id','<?= $id ?>');fd.append('action',this.dataset.action);fd.append('notes',notes);const x=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(z=>z.json());if(x.success){await Swal.fire('Response',x.message,'success');location.reload();}else Swal.fire('Response',x.message,'error');});

 // Canned Legal / Policy Template Handler
 const cannedSelect = document.getElementById('cannedResponseSelect');
 if (cannedSelect && edit) {
   cannedSelect.addEventListener('change', function() {
     if (!this.value) return;
     const selectedOption = this.options[this.selectedIndex];
     const templateTitle = selectedOption.getAttribute('data-title') || '';
     const templateBody = this.value;
     const bodyEl = document.getElementById('responseBodyArea') || edit.querySelector('textarea[name=body]');
     const subjectEl = edit.querySelector('input[name=subject]');
     if (bodyEl) {
       bodyEl.value = templateBody;
     }
     if (subjectEl && (!subjectEl.value || subjectEl.value.trim() === '')) {
       subjectEl.value = 'Re: ' + templateTitle;
     }
     Swal.fire({toast: true, position: 'top-end', icon: 'info', title: 'Template inserted: ' + templateTitle, timer: 1500, showConfirmButton: false});
   });
 }

 document.querySelectorAll('.response-doc-visibility-btn').forEach(btn=>btn.addEventListener('click',async()=>{
   const fd=new FormData();fd.append('csrf_token',document.querySelector('input[name=csrf_token]').value);fd.append('scope','response');fd.append('document_id',btn.dataset.id);fd.append('visibility',btn.dataset.next);
   const x=await fetch('../shared/ajax_document_visibility.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(z=>z.json());
   if(x.success)location.reload();else Swal.fire('Attachment Visibility',x.message,'error');
 }));

 // AI Response Drafting Handler
 const btnAiEdit = document.getElementById('btnAiDraftEdit');
 if (btnAiEdit && edit) {
   btnAiEdit.addEventListener('click', async () => {
     const rType = edit.querySelector('select[name=response_type]').value;
     const subId = edit.querySelector('input[name=submission_id]').value;
     btnAiEdit.disabled = true;
     const origHtml = btnAiEdit.innerHTML;
     btnAiEdit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Drafting...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', subId);
       fd.append('response_type', rType);
       const res = await fetch('ajax_ai_draft.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Failed to generate draft. Make sure Ollama is running.', 'error');
         return;
       }
       edit.querySelector('input[name=subject]').value = res.subject;
       edit.querySelector('textarea[name=body]').value = res.body;
       Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Official draft generated by Ollama', timer: 1500, showConfirmButton: false});
     } catch (err) {
       Swal.fire('Ollama AI', 'Failed to connect: ' + err.message, 'error');
     } finally {
       btnAiEdit.disabled = false;
       btnAiEdit.innerHTML = origHtml;
     }
   });
 }
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
