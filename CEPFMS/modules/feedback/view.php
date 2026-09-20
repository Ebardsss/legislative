<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.view');

$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);
if(!$s||$s['submission_type']!=='Feedback'){setFlash('warning','Feedback record not found.');redirect(appUrl('modules/feedback/index.php'));}

$q=$pdo->prepare('SELECT * FROM cef_feedback_submissions WHERE submission_id=:id');$q->execute([':id'=>$id]);$f=$q->fetch()?:[];
$h=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM cef_submission_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.submission_id=:id ORDER BY h.created_at DESC,h.id DESC");$h->execute([':id'=>$id]);$history=$h->fetchAll();
$d=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM cef_submission_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.submission_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$d->execute([':id'=>$id]);$docs=$d->fetchAll();
$categories=$pdo->query("SELECT id,name FROM cef_categories WHERE is_active=1 ORDER BY name")->fetchAll();

$ref=$pdo->prepare("SELECT r.*, li.reference_number, li.title legislative_title, u.full_name referred_name FROM cef_legislative_referrals r JOIN legislative_items li ON li.id=r.legislative_item_id LEFT JOIN users u ON u.id=r.referred_by WHERE r.submission_id=:id ORDER BY r.referred_at DESC");
$ref->execute([':id'=>$id]);
$referrals=$ref->fetchAll();
$legItems=$pdo->query("SELECT id, reference_number, title, current_status FROM legislative_items WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 200")->fetchAll();

$respQ = $pdo->prepare("SELECT r.*, u.full_name drafted_name, au.full_name approved_name FROM cef_responses r LEFT JOIN users u ON u.id=r.drafted_by LEFT JOIN users au ON au.id=r.approved_by WHERE r.submission_id=:id ORDER BY r.created_at DESC");
$respQ->execute([':id'=>$id]);
$responses = $respQ->fetchAll();

$pageTitle=$s['reference_number'];$activeMenu='feedback';$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Feedback Registry</a><div class="cef-eyebrow mt-2"><?= e($s['reference_number']) ?> · Public Feedback</div><h1><?= e($s['title']) ?></h1><p>Received <?= formatDateTime($s['created_at']) ?> · <?= e($s['category_name']?:'Unclassified') ?></p></div><div class="d-flex gap-2"><span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<!-- FEEDBACK LIFECYCLE STEPPER BAR -->
<?php
  $step1 = true; // Submitted
  $step2 = in_array($s['moderation_status'], ['Validated'], true) || in_array($s['status'], ['Validated','Assigned','In Progress','Responded','Resolved','Closed'], true);
  $step3 = in_array($s['status'], ['In Progress','Responded','Resolved','Closed'], true) || count($responses) > 0;
  $step4 = in_array($s['status'], ['Resolved','Closed'], true);
?>
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px; background: #ffffff;">
  <div class="card-body p-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex align-items-center gap-2 <?= $step1 ? 'text-primary fw-bold' : 'text-muted' ?>" style="font-size: 0.85rem;">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= $step1 ? 'bg-primary text-white' : 'bg-light border text-muted' ?>" style="width: 26px; height: 26px; font-size: 0.8rem;">1</span>
        <span>Intake Received</span>
      </div>
      <i class="bi bi-chevron-right text-muted" style="font-size: 0.75rem;"></i>
      <div class="d-flex align-items-center gap-2 <?= $step2 ? 'text-primary fw-bold' : 'text-muted' ?>" style="font-size: 0.85rem;">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= $step2 ? 'bg-primary text-white' : 'bg-light border text-muted' ?>" style="width: 26px; height: 26px; font-size: 0.8rem;">2</span>
        <span>Moderation <?= $step2 ? '(&check;)' : '' ?></span>
      </div>
      <i class="bi bi-chevron-right text-muted" style="font-size: 0.75rem;"></i>
      <div class="d-flex align-items-center gap-2 <?= $step3 ? 'text-primary fw-bold' : 'text-muted' ?>" style="font-size: 0.85rem;">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= $step3 ? 'bg-primary text-white' : 'bg-light border text-muted' ?>" style="width: 26px; height: 26px; font-size: 0.8rem;">3</span>
        <span>Response / Discussion</span>
      </div>
      <i class="bi bi-chevron-right text-muted" style="font-size: 0.75rem;"></i>
      <div class="d-flex align-items-center gap-2 <?= $step4 ? 'text-success fw-bold' : 'text-muted' ?>" style="font-size: 0.85rem;">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= $step4 ? 'bg-success text-white' : 'bg-light border text-muted' ?>" style="width: 26px; height: 26px; font-size: 0.8rem;">4</span>
        <span>Resolved &amp; Chat Closed</span>
      </div>
    </div>
  </div>
</div>

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

<div class="row g-3">
<div class="col-xl-8">
<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Citizen Feedback Record</span><button type="button" class="btn btn-sm btn-outline-primary" id="btnUpvoteFeedback" data-id="<?= $id ?>"><i class="bi bi-hand-thumbs-up me-1"></i> <span id="upvoteCountText"><?= (int)($f['upvote_count'] ?? 0) ?></span> Citizens Agree (+1)</button></div><div class="card-body"><div class="cef-grid"><div><small>Citizen</small><strong><?= $s['anonymous_flag']?'Anonymous':e($s['citizen_name']?:'Not provided') ?></strong></div><div><small>Contact</small><strong><?= $s['anonymous_flag']?'Not retained':e($s['citizen_email']?:$s['citizen_phone']?:'Not provided') ?></strong></div><div><small>Priority</small><strong><?= e($s['priority_level']) ?></strong></div><div><small>Feedback Kind</small><strong><?= e($f['feedback_kind']??'General Feedback') ?></strong></div><div><small>Service Area</small><strong><?= e($f['service_area']??'—') ?></strong></div><div><small>Overall Rating</small><strong class="text-warning"><i class="bi bi-star-fill"></i> <?= !empty($f['citizen_rating'])?(int)$f['citizen_rating'].' / 5':'5 / 5' ?></strong></div></div><div class="cef-alert mt-3"><strong>Details</strong><br><?= nl2br(e($s['details'])) ?></div><?php if(!empty($f['desired_outcome'])): ?><div class="cef-alert mt-3"><strong>Desired Outcome</strong><br><?= nl2br(e($f['desired_outcome'])) ?></div><?php endif; ?></div></div>

<?php if(!empty($responses)): ?>
<!-- OFFICIAL DELIVERED COUNCIL RESPONSES -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #198754 !important;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-check2-circle text-success me-1"></i> <strong>Official Council Responses (<?= count($responses) ?>)</strong></span>
    <span class="badge bg-success">Published to Citizen</span>
  </div>
  <div class="card-body p-3">
    <?php foreach($responses as $resp): ?>
      <div class="p-3 bg-light rounded border mb-2">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <strong class="text-dark"><?= e($resp['subject']) ?></strong>
          <span class="badge bg-light text-secondary border font-monospace"><?= e($resp['response_reference']) ?></span>
        </div>
        <div class="small text-muted mb-2">
          Delivered: <?= formatDateTime($resp['delivered_at']) ?> · Drafted by: <?= e($resp['drafted_name'] ?? 'Staff') ?>
        </div>
        <div class="p-2 bg-white rounded border small text-dark" style="white-space: pre-wrap;"><?= nl2br(e($resp['body'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- LEGISLATIVE PROCEEDING CROSS-LINKING -->
<div class="card cef-card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-link-45deg text-primary me-1"></i> <strong>Linked Legislative Proceedings &amp; Ordinances</strong></span>
  </div>
  <div class="card-body">
    <div class="d-grid gap-2 mb-3">
      <?php if(!$referrals): ?>
        <div class="text-muted small p-2 bg-light rounded text-center">No legislative proceedings linked yet. You can link this feedback to an ordinance below so councilors can review community sentiment during sessions.</div>
      <?php endif; ?>
      <?php foreach($referrals as $r): ?>
        <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
          <div>
            <strong><?= e($r['reference_number'].' · '.$r['legislative_title']) ?></strong>
            <div class="small text-muted"><?= e($r['referral_type'].' · '.$r['notes']) ?> · <?= formatDateTime($r['referred_at']) ?></div>
          </div>
          <span class="badge bg-primary-subtle text-primary">Linked</span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if(cefHasPermission('cepfms.feedback.manage')): ?>
      <form id="linkProceedingForm" class="row g-2 align-items-end">
        <?= csrfField() ?>
        <input type="hidden" name="submission_id" value="<?= $id ?>">
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Select Ordinance / Legislative Proceeding</label>
          <select class="form-select form-select-sm" name="legislative_item_id" required>
            <option value="">-- Choose Legislative Item --</option>
            <?php foreach($legItems as $item): ?>
              <option value="<?= (int)$item['id'] ?>"><?= e($item['reference_number'].' · '.$item['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Context Note</label>
          <input type="text" class="form-control form-control-sm" name="notes" placeholder="e.g. Public sentiment on proposed curfew">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-link me-1"></i> Link Item</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card cef-card mb-3"><div class="card-header">Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$docs): ?><div class="list-group-item text-muted">No documents.</div><?php endif; ?><?php foreach($docs as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php if(cefHasPermission('cepfms.feedback.manage')): ?><div class="d-flex justify-content-end mt-1 mb-2"><button type="button" class="btn btn-link btn-sm p-0 doc-visibility-btn" data-id="<?= (int)$doc['id'] ?>" data-scope="submission" data-next="<?= $doc['visibility']==='Public'?'Internal':'Public' ?>"><?= $doc['visibility']==='Public'?'Make Internal Only':'Make Citizen Visible' ?></button></div><?php endif; ?><?php endforeach; ?></div><?php if(cefHasPermission('cepfms.feedback.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><div class="col-md-4"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Internal Feedback Document"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option value="Internal">Internal Only</option><option value="Public">Citizen Visible</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
<?php $ticketChatPermission='cepfms.feedback.manage'; include __DIR__.'/../../includes/cepfms_ticket_chat.php'; ?>
</div>

<div class="col-xl-4">
<?php if(cefHasPermission('cepfms.feedback.manage')): ?>
<!-- FEEDBACK WORKFLOW & RESOLUTION ACTIONS -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #0f2137 !important;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-gear-wide-connected text-primary me-1"></i> <strong>Workflow Actions</strong></span>
    <span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span>
  </div>
  <div class="card-body p-3 d-grid gap-2">
    <?php if(in_array($s['status'], ['Submitted', 'Under Moderation'], true)): ?>
      <div class="p-2 bg-light rounded border small mb-1">
        <i class="bi bi-shield-exclamation text-warning me-1"></i> Awaiting Intake Moderation.
      </div>
      <a class="btn btn-warning btn-sm fw-bold" href="../moderation/view.php?id=<?= $id ?>">
        <i class="bi bi-shield-check me-1"></i> Open Moderation Desk
      </a>
    <?php endif; ?>

    <?php if($s['status'] === 'Validated'): ?>
      <button type="button" class="btn btn-primary btn-sm feedback-action-btn" data-action="start">
        <i class="bi bi-play-circle me-1"></i> Start Processing
      </button>
    <?php endif; ?>

    <?php if(in_array($s['status'], ['Validated', 'In Progress'], true)): ?>
      <a class="btn btn-outline-primary btn-sm" href="../responses/create.php?submission_id=<?= $id ?>">
        <i class="bi bi-reply-fill me-1"></i> Create Official Response
      </a>
    <?php endif; ?>

    <?php if(in_array($s['status'], ['Validated', 'In Progress', 'Responded', 'Awaiting Citizen'], true)): ?>
      <div class="p-2 bg-light rounded border small mb-1 text-muted">
        <i class="bi bi-info-circle text-primary me-1"></i> Resolving or closing this feedback concludes the ticket and closes the conversation with the citizen.
      </div>
      <button type="button" class="btn btn-success btn-sm feedback-action-btn" data-action="resolve">
        <i class="bi bi-check-circle-fill me-1"></i> Mark Feedback as Resolved
      </button>
      <button type="button" class="btn btn-dark btn-sm feedback-action-btn" data-action="close">
        <i class="bi bi-lock-fill me-1"></i> Close Feedback &amp; End Chat
      </button>
    <?php endif; ?>

    <?php if($s['status'] === 'Resolved'): ?>
      <div class="alert alert-success small mb-1 p-2">
        <i class="bi bi-check2-circle me-1"></i> Feedback is resolved. Citizen conversation is closed.
      </div>
      <button type="button" class="btn btn-dark btn-sm feedback-action-btn" data-action="close">
        <i class="bi bi-archive-fill me-1"></i> Close Ticket Permanently
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm feedback-action-btn" data-action="reopen">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reopen Feedback
      </button>
    <?php endif; ?>

    <?php if($s['status'] === 'Closed'): ?>
      <div class="alert alert-secondary small mb-1 p-2">
        <i class="bi bi-lock-fill me-1"></i> Feedback ticket is closed. Ticket conversation is concluded.
      </div>
      <button type="button" class="btn btn-outline-warning btn-sm feedback-action-btn" data-action="reopen">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reopen Feedback &amp; Chat
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="card cef-card mb-3"><div class="card-header">Intake / Classification</div><div class="card-body"><form id="updateForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><label class="form-label">Category</label><select class="form-select mb-2" name="category_id"><option value="">Unclassified</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$s['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><label class="form-label">Priority</label><select class="form-select mb-2" name="priority_level"><?php foreach(cefPriorityLevels() as $x): ?><option <?= $s['priority_level']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select><label class="form-label">Status</label><select class="form-select mb-2" name="status"><?php 
  $availableStatuses = ($s['moderation_status'] === 'Validated')
    ? ['Validated', 'In Progress', 'Responded', 'Resolved', 'Closed']
    : ['Submitted', 'Under Moderation'];
  if (!in_array($s['status'], $availableStatuses, true)) {
    $availableStatuses[] = $s['status'];
  }
  foreach($availableStatuses as $st): ?><option value="<?= e($st) ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select><label class="form-label">Update Note</label><textarea class="form-control mb-2" name="note" rows="3"></textarea><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="public_visible" value="1" id="pub"><label class="form-check-label" for="pub">Show this update in citizen tracking</label></div><button class="btn btn-primary w-100">Save Intake Update</button></form></div></div>
<?php endif; ?>

<div class="card cef-card"><div class="card-header">Public / Internal History</div><div class="card-body cef-timeline"><?php foreach($history as $x): ?><div><strong><?= e($x['action']) ?> · <?= e($x['changed_name']?:'Public/System') ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?= $x['details']?'<br>'.e($x['details']):'' ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>
<?php if(cefHasPermission('cepfms.feedback.manage')): ?><script>
document.addEventListener('DOMContentLoaded',()=>{
 const update=document.getElementById('updateForm'),doc=document.getElementById('docForm'),linkForm=document.getElementById('linkProceedingForm');
 if(update) update.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_update.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(update)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Feedback Error',r.message,'error');};
 if(doc) doc.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(doc)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
 if(linkForm) linkForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_link_proceeding.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(linkForm)}).then(x=>x.json());if(r.success){Swal.fire('Linked!',r.message,'success').then(()=>location.reload());}else Swal.fire('Link Error',r.message,'error');};

 const btnUpvote=document.getElementById('btnUpvoteFeedback');
 if(btnUpvote) btnUpvote.addEventListener('click',async()=>{
   const fd=new FormData();fd.append('csrf_token',document.querySelector('input[name=csrf_token]').value);fd.append('submission_id',btnUpvote.dataset.id);
   const r=await fetch('ajax_feedback_upvote.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
   if(r.success){document.getElementById('upvoteCountText').textContent=r.new_count;Swal.fire('Thank you!',r.message,'success');}
 });

 document.querySelectorAll('.doc-visibility-btn').forEach(btn=>btn.addEventListener('click',async()=>{
   const fd=new FormData();fd.append('csrf_token',document.querySelector('input[name=csrf_token]').value);fd.append('scope',btn.dataset.scope);fd.append('document_id',btn.dataset.id);fd.append('visibility',btn.dataset.next);
   const x=await fetch('../shared/ajax_document_visibility.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(z=>z.json());
   if(x.success)location.reload();else Swal.fire('Attachment Visibility',x.message,'error');
 }));

 // Workflow Transition Handlers
 document.querySelectorAll('.feedback-action-btn').forEach(btn => {
   btn.addEventListener('click', async function() {
     const action = this.dataset.action;
     let notes = '';
     let promptTitle = 'Confirm Action?';
     let inputType = undefined;
     let confirmButtonColor = '#0d6efd';

     if (action === 'resolve') {
       promptTitle = 'Resolve this Feedback?';
       inputType = 'textarea';
       confirmButtonColor = '#198754';
     } else if (action === 'close') {
       promptTitle = 'Close Feedback & End Citizen Chat?';
       inputType = 'textarea';
       confirmButtonColor = '#212529';
     } else if (action === 'reopen') {
       promptTitle = 'Reopen this Feedback?';
       inputType = 'text';
       confirmButtonColor = '#ffc107';
     } else if (action === 'start') {
       promptTitle = 'Start Processing Feedback?';
     }

     const conf = await Swal.fire({
       title: promptTitle,
       text: (action === 'close' || action === 'resolve') ? 'This will conclude the ticket and automatically close the conversation with the citizen.' : undefined,
       input: inputType,
       inputLabel: inputType ? 'Resolution / Concluding remarks (optional):' : undefined,
       inputPlaceholder: 'Enter notes or conclusion for record...',
       showCancelButton: true,
       confirmButtonColor: confirmButtonColor,
       confirmButtonText: 'Yes, proceed'
     });

     if (!conf.isConfirmed) return;
     notes = conf.value || '';

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('submission_id', '<?= $id ?>');
     fd.append('action', action);
     fd.append('notes', notes);

     const r = await fetch('ajax_transition.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     }).then(x => x.json());

     if (r.success) {
       await Swal.fire({
         icon: 'success',
         title: 'Feedback Workflow Updated',
         text: r.message,
         timer: 1500,
         showConfirmButton: false
       });
       location.reload();
     } else {
       Swal.fire('Workflow Error', r.message, 'error');
     }
   });
 });
});
</script><?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
