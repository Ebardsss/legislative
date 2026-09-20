<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT
        e.id,e.event_reference,e.event_type,e.title,e.description,
        e.start_datetime,e.end_datetime,e.all_day,e.venue,e.meeting_link,e.status,
        a.id agenda_id,a.agenda_reference,a.title agenda_title,a.status agenda_status,
        li.reference_number legislative_reference,li.title legislative_title,
        c.name committee_name,o.name office_name
     FROM lacms_calendar_events e
     LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
     LEFT JOIN legislative_items li ON li.id=e.legislative_item_id
     LEFT JOIN committees c ON c.id=e.committee_id
     LEFT JOIN offices o ON o.id=e.office_id
     WHERE e.id=:id
       AND e.status IN ("Confirmed","In Progress","Completed")
       AND (
          (e.agenda_id IS NOT NULL AND a.status IN ("Finalized","Archived"))
          OR
          (e.legislative_item_id IS NOT NULL AND li.visibility="Public" AND li.deleted_at IS NULL)
       )
     LIMIT 1'
);
$q->execute([':id'=>$id]);
$event=$q->fetch();

if(!$event){
    http_response_code(404);
    exit('Public calendar event not found.');
}

portalLogPublicView('LACMS Calendar',$event['event_reference']);

$pageTitle=$event['event_reference'];
$activeMenu='calendar';
$extraCss=[appUrl('assets/css/public-modules.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/calendar/index.php')) ?>"><i class="bi bi-arrow-left"></i> Agenda & Calendar</a>
<h1><?= e($event['title']) ?></h1>
<p><?= e($event['event_reference']) ?> · <?= e($event['event_type']) ?></p>
</div>
<span class="public-status <?= e(portalStatusClass($event['status'])) ?>"><?= e($event['status']) ?></span>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card">
<div class="card-header">Schedule Information</div>
<div class="card-body">
<div class="public-detail-grid">
<div><small>Start</small><strong><?= formatDateTime($event['start_datetime']) ?></strong></div>
<div><small>End</small><strong><?= formatDateTime($event['end_datetime']) ?></strong></div>
<div><small>Venue</small><strong><?= e($event['venue']?:'To be announced') ?></strong></div>
<div><small>Committee</small><strong><?= e($event['committee_name']?:'—') ?></strong></div>
<div><small>Office</small><strong><?= e($event['office_name']?:'—') ?></strong></div>
<div><small>Status</small><strong><?= e($event['status']) ?></strong></div>
</div>
<?php if($event['description']): ?><div class="public-text-block"><h3>Description</h3><p><?= nl2br(e($event['description'])) ?></p></div><?php endif; ?>
<?php $meetingLink=portalPublicUrl($event['meeting_link']); if($meetingLink): ?>
<a class="btn btn-primary mt-3" href="<?= e($meetingLink) ?>" target="_blank" rel="noopener"><i class="bi bi-camera-video"></i> Open Public Meeting Link</a>
<?php endif; ?>
</div>
</div>
</div>
<div class="col-xl-4">
<div class="card portal-card">
<div class="card-header">Related Public Information</div>
<div class="card-body">
<?php if($event['agenda_id'] && in_array($event['agenda_status'],['Finalized','Archived'],true)): ?>
<a class="related-public-link" href="<?= e(appUrl('modules/calendar/agenda.php?id='.(int)$event['agenda_id'])) ?>">
<i class="bi bi-list-check"></i><div><small>Finalized Agenda</small><strong><?= e($event['agenda_reference']) ?></strong><span><?= e($event['agenda_title']) ?></span></div>
</a>
<?php endif; ?>
<?php if($event['legislative_reference']): ?>
<div class="related-public-link static">
<i class="bi bi-journal-text"></i><div><small>Related Legislative Item</small><strong><?= e($event['legislative_reference']) ?></strong><span><?= e($event['legislative_title']) ?></span></div>
</div>
<?php endif; ?>
<?php if(!$event['agenda_id']&&!$event['legislative_reference']): ?><div class="text-muted small">No public related record is available.</div><?php endif; ?>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
