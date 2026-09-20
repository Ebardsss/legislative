<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.view');

$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);
if(!$s){setFlash('warning','Submission not found.');redirect(appUrl('modules/moderation/index.php'));}

$reviewQ=$pdo->prepare(
 'SELECT r.*,u.full_name reviewer_name
  FROM cef_moderation_reviews r
  LEFT JOIN users u ON u.id=r.reviewer_id
  WHERE r.submission_id=:id
  ORDER BY r.review_round DESC,r.id DESC LIMIT 1'
);
$reviewQ->execute([':id'=>$id]);$review=$reviewQ->fetch()?:null;
if(!$review && cefHasPermission('cepfms.moderation.manage') && !in_array($s['status'],['Rejected','Duplicate','Withdrawn','Closed'],true)){
    $review = cefEnsureModerationReview($pdo, $id);
    if($s['status']==='Submitted'){
        $pdo->prepare('UPDATE cef_submissions SET moderation_status="Under Review",status="Under Moderation",updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        $s['status']='Under Moderation';
        $s['moderation_status']='Under Review';
    }
}

$checklist=$review?cefReviewChecklist($pdo,(int)$review['id']):[];

$aiQ=$pdo->prepare('SELECT * FROM cef_ai_analysis WHERE submission_id=:id ORDER BY id DESC LIMIT 1');
$aiQ->execute([':id'=>$id]);$latestAi=$aiQ->fetch()?:null;
$latestAiData=$latestAi&&!empty($latestAi['result_json'])?json_decode((string)$latestAi['result_json'],true):null;

$dupQ=$pdo->prepare(
 "SELECT dm.*,m.reference_number,m.title,m.status submission_status
  FROM cef_duplicate_matches dm
  JOIN cef_submissions m ON m.id=dm.matched_submission_id
  WHERE dm.submission_id=:id
  ORDER BY dm.status='Confirmed' DESC,dm.similarity_score DESC,dm.created_at DESC"
);
$dupQ->execute([':id'=>$id]);$duplicates=$dupQ->fetchAll();

$candidates=$pdo->prepare(
 "SELECT id,reference_number,title,status
  FROM cef_submissions
  WHERE id<>:id
    AND submission_type=:type
    AND deleted_at IS NULL
  ORDER BY created_at DESC LIMIT 150"
);
$candidates->execute([':id'=>$id,':type'=>$s['submission_type']]);
$duplicateCandidates=$candidates->fetchAll();

$docsQ=$pdo->prepare(
 "SELECT d.*,u.full_name uploaded_name
  FROM cef_submission_documents d
  LEFT JOIN users u ON u.id=d.uploaded_by
  WHERE d.submission_id=:id
  ORDER BY d.uploaded_at DESC,d.id DESC"
);
$docsQ->execute([':id'=>$id]);$docs=$docsQ->fetchAll();

$historyQ=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name
  FROM cef_submission_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.submission_id=:id
  ORDER BY h.created_at DESC,h.id DESC"
);
$historyQ->execute([':id'=>$id]);$history=$historyQ->fetchAll();

$cannedClarifications = $pdo->query("SELECT * FROM cef_canned_templates WHERE template_type = 'Clarification' ORDER BY id ASC")->fetchAll();
$allCategories = $pdo->query("SELECT id, name FROM cef_categories WHERE is_active=1 ORDER BY name ASC")->fetchAll();

$pageTitle=$s['reference_number'].' Moderation';$activeMenu='moderation';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Moderation Desk</a><div class="cef-eyebrow mt-2"><?= e($s['reference_number']) ?> · <?= e($s['submission_type']) ?></div><h1><?= e($s['title']) ?></h1><p><?= e($s['category_name']?:'Unclassified') ?> · Received <?= formatDateTime($s['created_at']) ?></p></div><div class="d-flex gap-2"><span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span><span class="cef-status <?= e(cefStatusClass($s['moderation_status'])) ?>"><?= e($s['moderation_status']) ?></span></div></div>

<?php 
  $targetDeskUrl = match($s['submission_type']) {
    'Complaint' => appUrl('modules/complaints/view.php?id=' . $id),
    'Proposal' => appUrl('modules/proposals/view.php?id=' . $id),
    default => appUrl('modules/feedback/view.php?id=' . $id),
  };
  $targetDeskName = match($s['submission_type']) {
    'Complaint' => 'Complaint Action Desk',
    'Proposal' => 'Proposal Review Desk',
    default => 'Feedback Management Desk',
  };
?>
<?php if ($s['moderation_status'] === 'Validated'): ?>
  <div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px; border-left: 5px solid #16a34a !important; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 1.25rem;">
          <i class="bi bi-check2-all"></i>
        </div>
        <div>
          <div class="d-flex align-items-center gap-2">
            <strong class="text-dark" style="font-size: 0.95rem;">Intake Moderation Completed · Validated</strong>
            <span class="badge bg-success" style="font-size: 0.72rem;">Passed Triage</span>
            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">Status: <?= e($s['status']) ?></span>
          </div>
          <small class="text-muted">
            This <?= strtolower(e($s['submission_type'])) ?> has passed intake moderation. Go to the <strong><?= e($targetDeskName) ?></strong> to handle citizen discussions, link legislative items, and officially conclude/resolve the ticket.
          </small>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="<?= e($targetDeskUrl) ?>" class="btn btn-success btn-sm fw-bold px-3">
          <i class="bi bi-arrow-right-circle me-1"></i> Open <?= e($targetDeskName) ?>
        </a>
        <?php if(!in_array($s['status'], ['Resolved', 'Closed', 'Rejected', 'Withdrawn'], true)): ?>
          <button type="button" class="btn btn-outline-danger btn-sm btn-quick-close" data-id="<?= $id ?>" data-ref="<?= e($s['reference_number']) ?>">
            <i class="bi bi-lock-fill me-1"></i> Close Ticket &amp; Chat
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
<div class="col-xl-8">
<div class="card cef-card mb-3"><div class="card-header">Submission Record</div><div class="card-body">
<div class="cef-grid">
<div><small>Citizen</small><strong><?= $s['anonymous_flag']?'Anonymous':e($s['citizen_name']?:'Not provided') ?></strong></div>
<div><small>Contact</small><strong><?= $s['anonymous_flag']?'Protected':e($s['citizen_email']?:$s['citizen_phone']?:'Not provided') ?></strong></div>
<div><small>Type</small><strong><?= e($s['submission_type']) ?></strong></div>
<div><small>Priority</small><strong><?= e($s['priority_level']) ?></strong></div>
<div><small>Channel</small><strong><?= e($s['source_channel']) ?></strong></div>
<div><small>Moderation Status</small><strong><?= e($s['moderation_status']) ?></strong></div>
</div>
<div class="cef-alert mt-3" id="submissionDetailsBox"><strong>Details</strong><br><span id="submissionDetailsText"><?= nl2br(e($s['details'])) ?></span></div>
<?php if(cefHasPermission('cepfms.moderation.manage')): ?>
  <div class="d-flex justify-content-end mt-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRedactPii">
      <i class="bi bi-shield-lock me-1"></i> Redact PII &amp; Profanity (Data Privacy Act)
    </button>
  </div>
<?php endif; ?>
</div></div>

<div class="card cef-card mb-3" id="aiAssistantCard" style="border-left: 4px solid #a97900;">
  <div class="card-header d-flex justify-content-between align-items-center bg-light">
    <span><i class="bi bi-robot text-warning me-1"></i> <strong>Ollama AI Moderation Assistant</strong></span>
    <button type="button" class="btn btn-sm btn-outline-warning" id="btnAiAnalyze">
      <i class="bi bi-stars"></i> <span id="aiBtnText">Analyze with Ollama</span>
    </button>
  </div>
  <div class="card-body" id="aiResultArea">
    <?php if ($latestAiData): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-<?= in_array($latestAiData['urgency_level'] ?? '', ['High', 'Urgent']) ? 'danger' : 'warning text-dark' ?>">
          <i class="bi bi-alarm me-1"></i>Urgency: <?= e($latestAiData['urgency_level'] ?? 'Normal') ?> (<?= (int)($latestAiData['urgency_score'] ?? 0) ?>%)
        </span>
        <span class="badge bg-<?= ($latestAiData['sentiment'] ?? '') === 'Negative' ? 'danger' : (($latestAiData['sentiment'] ?? '') === 'Positive' ? 'success' : 'secondary') ?>">
          Sentiment: <?= e($latestAiData['sentiment'] ?? 'Neutral') ?>
        </span>
        <span class="badge bg-<?= ($latestAiData['content_safety'] ?? '') === 'Safe' ? 'success' : 'danger' ?>">
          Safety: <?= e($latestAiData['content_safety'] ?? 'Safe') ?>
        </span>
        <span class="badge bg-info text-dark">
          Actionability: <?= e($latestAiData['actionability'] ?? 'Complete') ?>
        </span>
      </div>
      <div class="mb-2"><strong>Executive Summary:</strong> <?= e($latestAiData['executive_summary'] ?? '') ?></div>
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <small class="text-muted d-block">Suggested Category</small>
          <strong><?= e($latestAiData['recommended_category'] ?? 'General') ?></strong>
        </div>
        <div class="col-md-6">
          <small class="text-muted d-block">Recommended Office</small>
          <strong><?= e($latestAiData['recommended_office'] ?? 'Public Assistance Desk') ?></strong>
        </div>
      </div>
      <?php if (!empty($latestAiData['risk_keywords']) || !empty($latestAiData['key_topics'])): ?>
        <div class="small mb-2">
          <span class="text-muted me-1">Keywords:</span>
          <?php foreach(($latestAiData['risk_keywords'] ?? []) as $kw): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1"><?= e($kw) ?></span>
          <?php endforeach; ?>
          <?php foreach(($latestAiData['key_topics'] ?? []) as $kw): ?>
            <span class="badge bg-light text-dark border me-1"><?= e($kw) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (cefHasPermission('cepfms.moderation.manage')): ?>
        <div class="d-flex gap-2 mt-3 pt-2 border-top">
          <button type="button" class="btn btn-sm btn-success" id="btnApplyAi" data-cat="<?= e($latestAiData['recommended_category'] ?? '') ?>" data-prio="<?= e($latestAiData['urgency_level'] ?? 'Normal') ?>">
            <i class="bi bi-check2-circle"></i> Apply AI Category &amp; Priority
          </button>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i> No AI triage record yet. Click <strong>"Analyze with Ollama"</strong> to detect urgency, sentiment, civic category, and safety flags.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card cef-card mb-3"><div class="card-header">Supporting Evidence</div><div class="list-group list-group-flush">
<?php if(!$docs): ?><div class="list-group-item text-muted">No supporting evidence.</div><?php endif; ?>
<?php foreach($docs as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php endforeach; ?>
</div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Moderation Checklist</span><?php if(cefHasPermission('cepfms.moderation.manage')): ?><?php if(!$review): ?><button class="btn btn-sm btn-warning" id="btnStartReview"><i class="bi bi-play-circle me-1"></i> Start Review</button><?php else: ?><button class="btn btn-sm btn-outline-success" id="btnPassAllChecklist" type="button"><i class="bi bi-check-all me-1"></i> Pass All (6/6)</button><?php endif; ?><?php endif; ?></div><div class="card-body">
<?php if(!$review): ?><div class="cef-alert">Click <strong>"Start Review"</strong> to generate the 6-point statutory moderation checklist.</div><?php else: ?>
<div class="d-grid gap-2">
<?php foreach($checklist as $c): ?>
<form class="p-2 border rounded moderation-check-form">
<?= csrfField() ?><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="item_code" value="<?= e($c['item_code']) ?>">
<div class="d-flex justify-content-between align-items-center mb-1"><strong><?= e($c['item_label']) ?></strong><select class="form-select form-select-sm w-auto" name="status"><option <?= $c['status']==='Pending'?'selected':'' ?>>Pending</option><option <?= $c['status']==='Pass'?'selected':'' ?>>Pass</option><option <?= $c['status']==='Fail'?'selected':'' ?>>Fail</option><option <?= $c['status']==='Not Applicable'?'selected':'' ?>>Not Applicable</option></select></div>
<div class="small text-muted mb-2"><?= e(cefModerationChecklistDefaults()[$c['item_code']] ?? '') ?></div>
<div class="input-group input-group-sm"><input class="form-control" name="notes" value="<?= e($c['notes']?:'') ?>" placeholder="Checklist notes"><button class="btn btn-outline-primary">Save Item</button></div>
</form>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between"><span>Potential Duplicate Matches &amp; Merging</span><?php if(cefHasPermission('cepfms.moderation.manage')): ?><div class="d-flex gap-2"><button class="btn btn-sm btn-primary" id="btnDuplicateScan"><i class="bi bi-files"></i> Scan Recent</button><button class="btn btn-sm btn-outline-primary" id="btnAiDuplicateScan" title="Scan using Ollama AI semantic understanding"><i class="bi bi-robot"></i> AI Scan</button></div><?php endif; ?></div><div class="card-body d-grid gap-2" id="duplicateList">
<?php if(!$duplicates): ?><div class="text-muted small">No duplicate suggestion has been recorded.</div><?php endif; ?>
<?php foreach($duplicates as $d): ?>
  <div class="cef-person d-flex justify-content-between align-items-center">
    <div>
      <strong><?= e($d['reference_number'].' · '.$d['title']) ?></strong>
      <div class="small text-muted"><?= e($d['status'].' · '.$d['submission_status']) ?><?= $d['similarity_score']!==null?' · Similarity '.number_format((float)$d['similarity_score'],1).'%':'' ?> · <em><?= e($d['match_source']??'Text Similarity') ?></em></div>
    </div>
    <?php if(cefHasPermission('cepfms.moderation.manage') && $d['status'] !== 'Confirmed'): ?>
      <button type="button" class="btn btn-sm btn-outline-danger btn-merge-case" data-child="<?= $id ?>" data-master="<?= (int)$d['matched_submission_id'] ?>" data-ref="<?= e($d['reference_number']) ?>">
        <i class="bi bi-intersect me-1"></i> Merge into Master
      </button>
    <?php else: ?>
      <span class="badge bg-secondary">Merged / Linked</span>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div></div>

</div>

<div class="col-xl-4">
<?php if(cefHasPermission('cepfms.moderation.manage')&&$review): ?>
<?php if($s['moderation_status'] === 'Validated'): ?>
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #16a34a !important;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-shield-check text-success me-1"></i> <strong>Intake Triage Complete</strong></span>
    <span class="badge bg-success">Validated</span>
  </div>
  <div class="card-body p-3">
    <div class="p-2 bg-light rounded border mb-3">
      <div class="small text-muted">Reviewed By:</div>
      <strong class="text-dark d-block"><?= e($review['reviewer_name'] ?? 'Authorized Reviewer') ?></strong>
      <small class="text-muted" style="font-size: 0.75rem;">Timestamp: <?= formatDateTime($review['reviewed_at'] ?? null) ?></small>
    </div>

    <div class="small mb-3">
      <strong>Category:</strong> <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($s['category_name'] ?: 'General') ?></span><br>
      <strong>Current Status:</strong> <span class="badge <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span>
    </div>

    <div class="p-2.5 rounded bg-light border mb-3 small">
      <strong class="text-dark d-block mb-1"><i class="bi bi-diagram-3 text-primary me-1"></i> Feedback Lifecycle:</strong>
      <div class="text-muted" style="line-height: 1.5; font-size: 0.8rem;">
        1. <strong>Intake &amp; Triage:</strong> Completed &check;<br>
        2. <strong>Desk Processing:</strong> In <?= e($targetDeskName) ?><br>
        3. <strong>Citizen Chat:</strong> Handled in Desk<br>
        4. <strong>Resolve &amp; Close Chat:</strong> Concludes Ticket
      </div>
    </div>

    <a href="<?= e($targetDeskUrl) ?>" class="btn btn-primary w-100 mb-2 fw-semibold">
      <i class="bi bi-box-arrow-up-right me-1"></i> Manage in <?= e($targetDeskName) ?>
    </a>

    <?php if(!in_array($s['status'], ['Resolved', 'Closed', 'Rejected', 'Withdrawn'], true)): ?>
      <button type="button" class="btn btn-outline-danger btn-sm w-100 mb-2 btn-quick-close" data-id="<?= $id ?>" data-ref="<?= e($s['reference_number']) ?>">
        <i class="bi bi-lock-fill me-1"></i> Close Ticket &amp; End Chat
      </button>
    <?php endif; ?>

    <button type="button" class="btn btn-link btn-sm text-secondary w-100 p-0 text-decoration-none" data-bs-toggle="collapse" data-bs-target="#editDecisionCollapse">
      <i class="bi bi-pencil me-1"></i> Re-evaluate / Override Moderation
    </button>

    <div class="collapse mt-3 pt-3 border-top" id="editDecisionCollapse">
      <form id="decisionForm">
      <?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
      <label class="form-label small fw-bold">Override Decision</label>
      <select class="form-select form-select-sm mb-2" name="decision" id="decisionSelect">
      <option value="Validate" selected>Validate</option>
      <option value="Needs Clarification">Needs Clarification</option>
      <option value="Reject">Reject</option>
      <option value="Duplicate">Duplicate</option>
      </select>
      <label class="form-label small fw-bold">Classification Category</label>
      <select class="form-select form-select-sm mb-2" name="category_id">
        <option value="">-- Choose Category --</option>
        <?php foreach($allCategories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= ((int)($s['category_id']??0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div id="duplicateTargetWrap" class="d-none">
      <label class="form-label small">Duplicate Of</label>
      <select class="form-select form-select-sm mb-2" name="duplicate_of_submission_id"><option value="">Choose original submission</option><?php foreach($duplicateCandidates as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title'].' · '.$x['status']) ?></option><?php endforeach; ?></select>
      </div>
      <label class="form-label small">Decision Notes</label>
      <textarea class="form-control form-control-sm mb-2" name="notes" id="decisionNotesArea" rows="3"><?= e($review['notes'] ?? '') ?></textarea>
      <button class="btn btn-sm btn-outline-primary w-100">Save Decision Override</button>
      </form>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card cef-card mb-3"><div class="card-header">Moderation Decision</div><div class="card-body">
<form id="decisionForm">
<?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
<label class="form-label">Decision</label>
<select class="form-select mb-2" name="decision" id="decisionSelect">
<option value="Validate">Validate</option>
<option value="Needs Clarification">Needs Clarification</option>
<option value="Reject">Reject</option>
<option value="Duplicate">Duplicate</option>
</select>
<label class="form-label small fw-bold">Classification Category</label>
<select class="form-select form-select-sm mb-2" name="category_id">
  <option value="">-- Choose Category --</option>
  <?php foreach($allCategories as $cat): ?>
    <option value="<?= (int)$cat['id'] ?>" <?= ((int)($s['category_id']??0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
  <?php endforeach; ?>
</select>
<div id="duplicateTargetWrap" class="d-none">
<label class="form-label">Duplicate Of</label>
<select class="form-select mb-2" name="duplicate_of_submission_id"><option value="">Choose original submission</option><?php foreach($duplicateCandidates as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title'].' · '.$x['status']) ?></option><?php endforeach; ?></select>
</div>

<!-- CANNED CLARIFICATION TEMPLATES -->
<div class="mb-2">
  <label class="form-label small fw-semibold text-secondary"><i class="bi bi-chat-left-quote me-1"></i> Quick Canned Template</label>
  <select class="form-select form-select-sm" id="cannedTemplateSelect">
    <option value="">-- Choose Standard Template --</option>
    <?php foreach($cannedClarifications as $ct): ?>
      <option value="<?= e($ct['content']) ?>"><?= e($ct['category'].' · '.$ct['title']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<label class="form-label">Decision Notes</label>
<textarea class="form-control mb-3" name="notes" id="decisionNotesArea" rows="5" placeholder="Required for clarification, rejection, and duplicate decisions"></textarea>
<button class="btn btn-primary w-100">Save Moderation Decision</button>
</form>
</div></div>
<?php endif; ?>
<?php endif; ?>

<div class="card cef-card"><div class="card-header">Submission History</div><div class="card-body cef-timeline">
<?php foreach($history as $x): ?><div><strong><?= e($x['action']) ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?= $x['details']?'<br>'.e($x['details']):'' ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?>
</div></div>
</div>
</div>

</main></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const start=document.getElementById('btnStartReview');
 if(start)start.onclick=async()=>{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('submission_id','<?= $id ?>');const r=await fetch('ajax_start.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Moderation',r.message,'error');};

 document.querySelectorAll('.moderation-check-form').forEach(form=>form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_checklist.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)Swal.fire({toast:true,position:'top-end',icon:'success',title:'Checklist saved',showConfirmButton:false,timer:1200});else Swal.fire('Checklist',r.message,'error');});

 document.getElementById('btnPassAllChecklist')?.addEventListener('click', async () => {
   const forms = document.querySelectorAll('.moderation-check-form');
   if (!forms.length) return;
   const conf = await Swal.fire({
     title: 'Pass all 6 criteria?',
     text: 'This will mark all statutory moderation criteria as Pass.',
     icon: 'question',
     showCancelButton: true,
     confirmButtonText: 'Yes, Pass All'
   });
   if (!conf.isConfirmed) return;
   Swal.fire({title: 'Updating checklist...', didOpen: () => Swal.showLoading(), allowOutsideClick: false});
   for (const form of forms) {
     form.querySelector('select[name=status]').value = 'Pass';
     const fd = new FormData(form);
     await fetch('ajax_checklist.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     });
   }
   await Swal.fire({icon: 'success', title: 'All 6 checklist criteria passed!', timer: 1400, showConfirmButton: false});
   location.reload();
 });

 const runDupScan = async (useAi = false) => {
   const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('submission_id','<?= $id ?>');
   if(useAi) fd.append('ai_scan', '1');
   Swal.fire({title: useAi ? 'Running AI Semantic Scan...' : 'Scanning Records...', didOpen: () => Swal.showLoading(), allowOutsideClick: false});
   const r=await fetch('ajax_duplicate_scan.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
   if(!r.success){Swal.fire('Duplicate Scan',r.message,'error');return;}
   await Swal.fire('Duplicate Scan',`${r.matches.length} potential match(es) found.`,'info');location.reload();
 };
 document.getElementById('btnDuplicateScan')?.addEventListener('click',()=>runDupScan(false));
 document.getElementById('btnAiDuplicateScan')?.addEventListener('click',()=>runDupScan(true));

 const decision=document.getElementById('decisionSelect'),wrap=document.getElementById('duplicateTargetWrap'),form=document.getElementById('decisionForm');
 if(decision)decision.onchange=()=>wrap.classList.toggle('d-none',decision.value!=='Duplicate');
 if(form)form.onsubmit=async e=>{e.preventDefault();const confirm=await Swal.fire({title:'Save moderation decision?',showCancelButton:true});if(!confirm.isConfirmed)return;const r=await fetch('ajax_decision.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success){await Swal.fire('Moderation',r.message,'success');location.reload();}else Swal.fire('Moderation',r.message,'error');};

 // Canned Template Populator
 const cannedSel = document.getElementById('cannedTemplateSelect');
 const notesArea = document.getElementById('decisionNotesArea');
 if(cannedSel && notesArea) {
   cannedSel.addEventListener('change', () => {
     if(cannedSel.value) {
       notesArea.value = cannedSel.value;
     }
   });
 }

 // Duplicate Case Merging Trigger
 document.querySelectorAll('.btn-merge-case').forEach(btn => {
   btn.addEventListener('click', async () => {
     const child = btn.dataset.child;
     const master = btn.dataset.master;
     const ref = btn.dataset.ref;
     const conf = await Swal.fire({
       title: 'Merge into Master Case?',
       html: `Are you sure you want to mark this ticket as a duplicate and merge it into <strong>${ref}</strong>?<br><small class="text-muted">Status updates to the master ticket will be reflected on this case.</small>`,
       icon: 'warning',
       showCancelButton: true,
       confirmButtonText: 'Yes, Merge Case',
       confirmButtonColor: '#dc2626'
     });
     if(!conf.isConfirmed) return;

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('child_submission_id', child);
     fd.append('master_submission_id', master);

     const res = await fetch('ajax_merge_duplicate.php', { method: 'POST', body: fd }).then(z => z.json());
     if (res.success) {
       await Swal.fire('Merged!', res.message, 'success');
       location.reload();
     } else {
       Swal.fire('Merge Error', res.message, 'error');
     }
   });
 });

 // PII & Profanity Redaction Trigger
 const btnRedact = document.getElementById('btnRedactPii');
 if(btnRedact) {
   btnRedact.addEventListener('click', async () => {
     const conf = await Swal.fire({
       title: 'Apply Privacy & Content Redaction?',
       text: 'This will automatically mask phone numbers, emails, and profanities according to the Data Privacy Act.',
       icon: 'question',
       showCancelButton: true,
       confirmButtonText: 'Yes, Redact'
     });
     if(!conf.isConfirmed) return;

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('submission_id', '<?= $id ?>');

     const res = await fetch('ajax_redact_pii.php', { method: 'POST', body: fd }).then(z => z.json());
     if (res.success) {
       document.getElementById('submissionDetailsText').innerHTML = res.sanitized_text.replace(/\n/g, '<br>');
       Swal.fire('Redacted!', res.message, 'success');
     } else {
       Swal.fire('Error', res.message, 'error');
     }
   });
 }

 // AI Analyze Handler
 const btnAi = document.getElementById('btnAiAnalyze');
 if (btnAi) {
   btnAi.addEventListener('click', async () => {
     btnAi.disabled = true;
     const origHtml = btnAi.innerHTML;
     btnAi.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyzing with Ollama...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', '<?= $id ?>');
       const res = await fetch('ajax_ai_analyze.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Analysis failed. Make sure Ollama is running.', 'error');
         return;
       }
       Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Ollama Analysis Complete', timer: 1500, showConfirmButton: false});
       location.reload();
     } catch (err) {
       Swal.fire('Ollama AI', 'Failed to connect to server: ' + err.message, 'error');
     } finally {
       btnAi.disabled = false;
       btnAi.innerHTML = origHtml;
     }
   });
 }

 // Apply AI Recommendations Handler
 const btnApply = document.getElementById('btnApplyAi');
 if (btnApply) {
   btnApply.addEventListener('click', async () => {
     const cat = btnApply.dataset.cat;
     const prio = btnApply.dataset.prio;
     const conf = await Swal.fire({
       title: 'Apply AI Recommendations?',
       text: `Set category to "${cat}" and priority to "${prio}"?`,
       icon: 'question',
       showCancelButton: true,
       confirmButtonText: 'Yes, apply'
     });
     if (!conf.isConfirmed) return;

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('submission_id', '<?= $id ?>');
     fd.append('action', 'apply');
     fd.append('category', cat);
     fd.append('priority', prio);

     const res = await fetch('ajax_ai_analyze.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     }).then(x => x.json());

     if (res.success) {
       await Swal.fire('Applied', res.message, 'success');
       location.reload();
     } else {
       Swal.fire('Error', res.message, 'error');
     }
   });
 }

  // Quick-close handler from moderation desk banner
  document.querySelectorAll('.btn-quick-close').forEach(btn => {
    btn.addEventListener('click', async () => {
      const subId = btn.dataset.id;
      const ref = btn.dataset.ref;
      const conf = await Swal.fire({
        title: 'Close Ticket & End Chat?',
        html: `Are you sure you want to officially conclude and close ticket <strong>${ref}</strong>?<br><small class="text-muted">This will lock the ticket chat for citizen and staff, and mark the status as Closed.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Close Ticket & Chat'
      });
      if (!conf.isConfirmed) return;

      try {
        const fd = new FormData();
        fd.append('csrf_token', '<?= e(csrfToken()) ?>');
        fd.append('submission_id', subId);
        fd.append('target_status', 'Closed');

        const res = await fetch('../shared/ajax_end_ticket_chat.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: fd
        }).then(x => x.json());

        if (res.success) {
          await Swal.fire('Closed', res.message, 'success');
          location.reload();
        } else {
          Swal.fire('Error', res.message || 'Could not close ticket', 'error');
        }
      } catch (err) {
        Swal.fire('Error', 'Request failed: ' + err.message, 'error');
      }
    });
  });
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
