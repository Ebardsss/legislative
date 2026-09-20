<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT s.*,c.name category_name
     FROM cef_submissions s
     LEFT JOIN cef_categories c ON c.id=s.category_id
     WHERE s.id=:id
       AND s.citizen_user_id=:user
       AND s.deleted_at IS NULL
     LIMIT 1'
);
$q->execute([
    ':id'=>$id,
    ':user'=>currentUserId(),
]);
$s=$q->fetch();

if(!$s){
    http_response_code(404);
    exit('Citizen submission not found.');
}

$rq=$pdo->prepare(
    'SELECT id,response_reference,response_type,subject,body,status,delivered_at,created_at
     FROM cef_responses
     WHERE submission_id=:submission
       AND status="Delivered"
     ORDER BY delivered_at DESC,id DESC'
);
$rq->execute([':submission'=>$id]);
$responses=$rq->fetchAll();

$hq=$pdo->prepare(
    'SELECT action,previous_status,new_status,details,created_at
     FROM cef_submission_history
     WHERE submission_id=:submission
       AND public_visible=1
     ORDER BY created_at,id'
);
$hq->execute([':submission'=>$id]);
$history=$hq->fetchAll();

$fq=$pdo->prepare(
    'SELECT f.id,f.response_id,f.direction,f.sender_name,f.sender_user_id,
            f.message,f.created_at,u.full_name sender_account_name
     FROM cef_followups f
     LEFT JOIN users u ON u.id=f.sender_user_id
     WHERE f.submission_id=:submission
       AND f.public_visible=1
     ORDER BY f.created_at,f.id'
);
$fq->execute([':submission'=>$id]);
$followups=$fq->fetchAll();

$sdq=$pdo->prepare(
    'SELECT id,file_name,mime_type,file_size,document_type,uploaded_at
     FROM cef_submission_documents
     WHERE submission_id=:submission
       AND visibility="Public"
     ORDER BY uploaded_at DESC,id DESC'
);
$sdq->execute([':submission'=>$id]);
$submissionDocs=$sdq->fetchAll();

$rdq=$pdo->prepare(
    'SELECT d.id,d.file_name,d.mime_type,d.file_size,d.document_type,d.uploaded_at,
            r.response_reference,r.subject response_subject
     FROM cef_response_documents d
     JOIN cef_responses r ON r.id=d.response_id
     WHERE r.submission_id=:submission
       AND r.status="Delivered"
       AND d.visibility="Public"
     ORDER BY d.uploaded_at DESC,d.id DESC'
);
$rdq->execute([':submission'=>$id]);
$responseDocs=$rdq->fetchAll();

$chatClosed=citizenTicketChatClosed((string)$s['status']);

portalLogPublicView('CEPFMS',$s['reference_number']);

$pageTitle=$s['reference_number'];
$activeMenu='engagement';
$extraCss=[appUrl('assets/css/public-modules.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/engagement/index.php')) ?>"><i class="bi bi-arrow-left"></i> My Engagement</a>
<h1><?= e($s['title']) ?></h1>
<p><?= e($s['reference_number']) ?> · <?= e($s['submission_type']) ?></p>
</div>
<span class="public-status <?= e(portalStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card mb-4">
<div class="card-header">My Submission</div>
<div class="card-body">
<div class="public-detail-grid">
<div><small>Type</small><strong><?= e($s['submission_type']) ?></strong></div>
<div><small>Category</small><strong><?= e($s['category_name']?:'—') ?></strong></div>
<div><small>Priority</small><strong><?= e($s['priority_level']) ?></strong></div>
<div><small>Moderation</small><strong><?= e($s['moderation_status']) ?></strong></div>
<div><small>Submitted</small><strong><?= formatDateTime($s['created_at']) ?></strong></div>
<div><small>Last Updated</small><strong><?= formatDateTime($s['updated_at']) ?></strong></div>
<?php if(!empty($s['location_text'])): ?>
<div style="grid-column: span 2;"><small>Incident Location</small><strong class="text-danger"><i class="bi bi-geo-alt-fill me-1"></i><?= e($s['location_text']) ?><?= !empty($s['barangay'])?' · Brgy '.e($s['barangay']):'' ?><?= !empty($s['district'])?' · '.e($s['district']):'' ?></strong></div>
<?php endif; ?>
</div>
<?php if($s['summary']): ?><div class="public-text-block"><h3>Summary</h3><p><?= nl2br(e($s['summary'])) ?></p></div><?php endif; ?>
<div class="public-text-block"><h3>Details</h3><p><?= nl2br(e($s['details'])) ?></p></div>
<?php
$evidenceImgDocs = array_filter($submissionDocs, function($d) {
    return str_starts_with($d['mime_type'] ?? '', 'image/') || in_array(strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif'], true);
});
?>
<?php if(!empty($evidenceImgDocs)): ?>
<div class="public-text-block mt-3">
  <h3><i class="bi bi-camera-fill me-1 text-primary"></i> Incident Photo Evidence</h3>
  <div class="d-flex flex-wrap gap-3 mt-2">
    <?php foreach($evidenceImgDocs as $imgDoc): ?>
      <div class="p-2 rounded bg-light border text-center shadow-sm" style="max-width: 380px;">
        <a href="<?= e(appUrl('modules/engagement/file.php?kind=submission&id='.(int)$imgDoc['id'].'&mode=view')) ?>" target="_blank" title="Click to view full image">
          <img src="<?= e(appUrl('modules/engagement/file.php?kind=submission&id='.(int)$imgDoc['id'].'&mode=view')) ?>" alt="Incident Evidence" class="img-fluid rounded border" style="max-height: 240px; width: 100%; object-fit: contain;">
        </a>
        <div class="small text-muted mt-1 d-flex justify-content-between align-items-center">
          <span class="text-truncate" style="max-width: 200px;"><?= e($imgDoc['file_name']) ?></span>
          <span class="badge bg-secondary"><?= citizenHumanFileSize((int)($imgDoc['file_size']??0)) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
</div>
</div>

<div class="card portal-card mb-4">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="bi bi-chat-dots me-2"></i>Ticket Conversation</span>
<span class="public-status <?= $chatClosed?'neutral':'good' ?>"><?= $chatClosed?'Chat Closed':'Chat Open' ?></span>
</div>
<div class="card-body">
<div class="citizen-ticket-chat" id="citizenTicketChat">
<?php if(!$followups): ?><div class="citizen-chat-empty">No messages yet.</div><?php endif; ?>
<?php foreach($followups as $f):
    $mine=$f['direction']==='Citizen to Council';
    $sender=$mine?'You':($f['sender_account_name']?:$f['sender_name']?:'CEPFMS Staff');
?>
<div class="citizen-chat-row <?= $mine?'mine':'staff' ?>">
<div class="citizen-chat-bubble">
<div class="citizen-chat-sender"><?= e($sender) ?><?= !$mine?' · CEPFMS Staff':'' ?></div>
<div class="citizen-chat-message"><?= nl2br(e($f['message'])) ?></div>
<div class="citizen-chat-time"><?= formatDateTime($f['created_at']) ?></div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if($chatClosed): ?>
<div class="alert alert-secondary small mt-3 mb-0"><i class="bi bi-lock me-1"></i>This conversation is disabled because the ticket is <?= e($s['status']) ?>. Previous messages remain available for reference.</div>
<?php else: ?>
<form method="post" action="<?= e(appUrl('modules/engagement/followup.php')) ?>" class="mt-3">
<?= csrfField() ?>
<input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
<label class="form-label">Send Message</label>
<div class="input-group">
<textarea class="form-control" name="message" rows="2" maxlength="5000" required placeholder="Type a message about this ticket..."></textarea>
<button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Send</button>
</div>
</form>
<?php endif; ?>
</div>
</div>

<div class="card portal-card">
<div class="card-header">Attachments Shared With Me</div>
<div class="card-body">
<?php if(!$submissionDocs&&!$responseDocs): ?><div class="text-muted small">No citizen-visible attachment has been shared for this ticket.</div><?php endif; ?>

<?php foreach($submissionDocs as $doc): ?>
<div class="citizen-file-row">
<div class="citizen-file-icon"><i class="bi bi-paperclip"></i></div>
<div class="citizen-file-info">
<strong><?= e($doc['file_name']) ?></strong>
<small><?= e($doc['document_type']) ?> · Ticket Attachment · <?= citizenHumanFileSize((int)($doc['file_size']??0)) ?> · <?= formatDateTime($doc['uploaded_at']) ?></small>
</div>
<div class="citizen-file-actions">
<a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(appUrl('modules/engagement/file.php?kind=submission&id='.(int)$doc['id'].'&mode=view')) ?>"><i class="bi bi-eye"></i> View</a>
<a class="btn btn-sm btn-primary" href="<?= e(appUrl('modules/engagement/file.php?kind=submission&id='.(int)$doc['id'].'&mode=download')) ?>"><i class="bi bi-download"></i> Download</a>
</div>
</div>
<?php endforeach; ?>

<?php foreach($responseDocs as $doc): ?>
<div class="citizen-file-row">
<div class="citizen-file-icon response"><i class="bi bi-file-earmark-check"></i></div>
<div class="citizen-file-info">
<strong><?= e($doc['file_name']) ?></strong>
<small><?= e($doc['document_type']) ?> · Official Response <?= e($doc['response_reference']) ?> · <?= citizenHumanFileSize((int)($doc['file_size']??0)) ?> · <?= formatDateTime($doc['uploaded_at']) ?></small>
</div>
<div class="citizen-file-actions">
<a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(appUrl('modules/engagement/file.php?kind=response&id='.(int)$doc['id'].'&mode=view')) ?>"><i class="bi bi-eye"></i> View</a>
<a class="btn btn-sm btn-primary" href="<?= e(appUrl('modules/engagement/file.php?kind=response&id='.(int)$doc['id'].'&mode=download')) ?>"><i class="bi bi-download"></i> Download</a>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>

<div class="col-xl-4">
<div class="card portal-card mb-4">
<div class="card-header">Official Response</div>
<div class="card-body">
<?php if(!$responses): ?><div class="small text-muted">No Delivered official response is available yet.</div><?php endif; ?>
<?php foreach($responses as $r): ?>
<div class="publication-entry">
<strong><?= e($r['subject']) ?></strong>
<small><?= e($r['response_reference']) ?> · <?= formatDateTime($r['delivered_at']) ?></small>
<p><?= nl2br(e($r['body'])) ?></p>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="card portal-card">
<div class="card-header">Public Status History</div>
<div class="card-body">
<?php if(!$history): ?><div class="small text-muted">No public history yet.</div><?php endif; ?>
<?php foreach($history as $h): ?>
<div class="publication-entry">
<strong><?= e($h['action']) ?></strong>
<small><?= formatDateTime($h['created_at']) ?><?= $h['new_status']?' · '.e($h['new_status']):'' ?></small>
<?php if($h['details']): ?><p><?= e($h['details']) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const box=document.getElementById('citizenTicketChat');
 if(box)box.scrollTop=box.scrollHeight;
});
</script>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
