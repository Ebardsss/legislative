<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT
        h.*,ht.name hearing_type,c.name committee_name,
        li.reference_number legislative_reference,li.title legislative_title,
        li.visibility legislative_visibility,
        (SELECT COUNT(*) FROM registrations r
         WHERE r.hearing_id=h.id
           AND r.registration_status IN ("Pending","Approved")) registration_count
     FROM hearings h
     LEFT JOIN hearing_types ht ON ht.id=h.hearing_type_id
     LEFT JOIN committees c ON c.id=h.committee_id
     LEFT JOIN legislative_items li ON li.id=h.legislative_item_id
     WHERE h.id=:id
       AND h.visibility="Public"
       AND h.status IN ("Upcoming","Ongoing","Completed")
     LIMIT 1'
);
$q->execute([':id'=>$id]);
$hearing=$q->fetch();

if(!$hearing){
    http_response_code(404);
    exit('Public hearing not found.');
}

$dq=$pdo->prepare(
    'SELECT id,file_name,file_path,document_type,description,version_number,uploaded_at
     FROM hearing_documents
     WHERE hearing_id=:hearing
       AND visibility="Public"
     ORDER BY uploaded_at DESC,id DESC'
);
$dq->execute([':hearing'=>$id]);
$documents=$dq->fetchAll();

$stakeholder=citizenStakeholder($pdo,currentUserId());
$registration=null;
if($stakeholder){
    $rq=$pdo->prepare(
        'SELECT *
         FROM registrations
         WHERE stakeholder_id=:stakeholder
           AND hearing_id=:hearing
         LIMIT 1'
    );
    $rq->execute([
        ':stakeholder'=>(int)$stakeholder['id'],
        ':hearing'=>$id,
    ]);
    $registration=$rq->fetch()?:null;
}

$deadlineOk=!$hearing['registration_deadline']||strtotime($hearing['registration_deadline'])>=time();
$capacityOk=!$hearing['maximum_participants']||(int)$hearing['registration_count']<(int)$hearing['maximum_participants'];
$registrationOpen=$hearing['status']==='Upcoming'&&$deadlineOk&&$capacityOk&&!$registration;

portalLogPublicView('PHCMS',$hearing['reference_number']?:'Hearing #'.$id);

$hearingSurvey = null;
try {
    $sq = $pdo->prepare('SELECT id, title, description, closes_at FROM surveys WHERE hearing_id = :hid AND status = "Active" LIMIT 1');
    $sq->execute([':hid' => $id]);
    $hearingSurvey = $sq->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$pageTitle=$hearing['title'];
$activeMenu='hearings';
$extraCss=[appUrl('assets/css/public-modules.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/hearings/index.php')) ?>"><i class="bi bi-arrow-left"></i> Hearings & Consultations</a>
<h1><?= e($hearing['title']) ?></h1>
<p><?= e($hearing['reference_number']?:'Public Hearing') ?> · <?= e($hearing['hearing_type']?:'Hearing') ?></p>
</div>
<span class="public-status <?= e(portalStatusClass($hearing['status'])) ?>"><?= e($hearing['status']) ?></span>
</div>

<?php if ($hearingSurvey): ?>
<div class="alert alert-warning border border-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4" style="background: #fffbeb; border-radius: 12px; border-left: 4px solid #a97900 !important;">
    <div>
        <strong class="d-block text-dark"><i class="bi bi-ui-checks-grid me-1" style="color: #a97900;"></i> Public Consultation Survey Open</strong>
        <span class="small text-secondary">The committee is currently collecting citizen feedback on this hearing: <strong><?= e($hearingSurvey['title']) ?></strong></span>
    </div>
    <a href="<?= e(appUrl('modules/surveys/take.php?id=' . (int)$hearingSurvey['id'])) ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-pencil-square me-1"></i> Answer Survey
    </a>
</div>
<?php endif; ?>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card mb-4">
<div class="card-header">Public Hearing Information</div>
<div class="card-body">
<div class="public-detail-grid">
<div><small>Date</small><strong><?= formatDate($hearing['hearing_date']) ?></strong></div>
<div><small>Time</small><strong><?= e(date('h:i A',strtotime($hearing['hearing_time']))) ?><?= $hearing['end_time']?' - '.e(date('h:i A',strtotime($hearing['end_time']))):'' ?></strong></div>
<div><small>Venue</small><strong><?= e($hearing['venue']?:'To be announced') ?></strong></div>
<div><small>Responsible Committee</small><strong><?= e($hearing['committee_name']?:'—') ?></strong></div>
<div><small>Registration Deadline</small><strong><?= formatDateTime($hearing['registration_deadline']) ?></strong></div>
<div><small>Capacity</small><strong><?= $hearing['maximum_participants']?(int)$hearing['registration_count'].' / '.(int)$hearing['maximum_participants']:'No public limit set' ?></strong></div>
</div>

<?php if($hearing['description']): ?><div class="public-text-block"><h3>Description</h3><p><?= nl2br(e($hearing['description'])) ?></p></div><?php endif; ?>

<?php if($hearing['legislative_reference']&&$hearing['legislative_visibility']==='Public'): ?>
<div class="public-link-note"><i class="bi bi-journal-text"></i> Related public legislative item: <strong><?= e($hearing['legislative_reference']) ?></strong> · <?= e($hearing['legislative_title']) ?></div>
<?php endif; ?>

<?php $meetingLink=portalPublicUrl($hearing['meeting_link']); if($meetingLink): ?>
<a class="btn btn-primary mt-3" href="<?= e($meetingLink) ?>" target="_blank" rel="noopener"><i class="bi bi-camera-video"></i> Open Public Meeting Link</a>
<?php endif; ?>
</div>
</div>

<?php if($documents): ?>
<div class="card portal-card">
<div class="card-header">Public Documents</div>
<div class="card-body">
<?php foreach($documents as $d): ?>
<div class="publication-entry">
<strong><?= e($d['file_name']) ?></strong>
<small><?= e($d['document_type']) ?> · <?= formatDateTime($d['uploaded_at']) ?></small>
<?php if($d['description']): ?><p><?= e($d['description']) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<div class="small text-muted mt-2">Public document metadata is shown here. The underlying PHCMS document remains controlled by its Public visibility setting.</div>
</div>
</div>
<?php endif; ?>
</div>

<div class="col-xl-4">
<div class="card portal-card">
<div class="card-header">My Hearing Registration</div>
<div class="card-body">
<?php if($registration): ?>
<div class="safe-note mb-3"><i class="bi bi-person-check"></i><div><strong><?= e($registration['registration_status']) ?></strong><span>Registration code: <?= e($registration['registration_code']?:'Pending assignment') ?></span></div></div>
<div class="public-side-list">
<div><small>Attendance Type</small><strong><?= e($registration['attendance_type']) ?></strong></div>
<div><small>Registered</small><strong><?= formatDateTime($registration['registered_at']) ?></strong></div>
<div><small>Approved</small><strong><?= formatDateTime($registration['approved_at']) ?></strong></div>
</div>
<?php elseif($registrationOpen): ?>
<p class="small text-muted">Register your citizen account for this public hearing. The PHCMS staff can review your stakeholder profile and registration.</p>
<form method="post" action="<?= e(appUrl('modules/hearings/register.php')) ?>">
<?= csrfField() ?>
<input type="hidden" name="hearing_id" value="<?= (int)$hearing['id'] ?>">
<label class="form-label">Attendance Type</label>
<select class="form-select mb-3" name="attendance_type">
<option>On-site</option>
<option>Online</option>
<option>Hybrid</option>
</select>
<button class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> Register for Hearing</button>
</form>
<?php else: ?>
<div class="text-muted small">
<?php if($hearing['status']!=='Upcoming'): ?>Registration is no longer open for this hearing.
<?php elseif(!$deadlineOk): ?>The registration deadline has passed.
<?php elseif(!$capacityOk): ?>The public registration capacity has been reached.
<?php else: ?>Registration is unavailable.<?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
