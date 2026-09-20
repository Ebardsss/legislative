<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT
        a.id,a.agenda_reference,a.title,a.agenda_type,a.agenda_date,
        a.start_time,a.end_time,a.venue,a.meeting_link,a.description,a.status,
        c.name committee_name,o.name office_name
     FROM lacms_agendas a
     LEFT JOIN committees c ON c.id=a.committee_id
     LEFT JOIN offices o ON o.id=a.office_id
     WHERE a.id=:id
       AND a.status IN ("Finalized","Archived")
     LIMIT 1'
);
$q->execute([':id'=>$id]);
$agenda=$q->fetch();

if(!$agenda){
    http_response_code(404);
    exit('Finalized public agenda not found.');
}

$items=$pdo->prepare(
    'SELECT
        ai.item_number,ai.sequence_number,ai.agenda_section,ai.title,ai.description,
        ai.priority_level,ai.item_status,ai.disposition,
        li.reference_number legislative_reference,li.title legislative_title
     FROM lacms_agenda_items ai
     LEFT JOIN legislative_items li ON li.id=ai.legislative_item_id
     WHERE ai.agenda_id=:agenda
       AND ai.item_status<>"Removed"
     ORDER BY ai.sequence_number,ai.id'
);
$items->execute([':agenda'=>$id]);
$agendaItems=$items->fetchAll();

portalLogPublicView('LACMS Agenda',$agenda['agenda_reference']);

$pageTitle=$agenda['agenda_reference'];
$activeMenu='calendar';
$extraCss=[appUrl('assets/css/public-modules.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/calendar/index.php?tab=agendas')) ?>"><i class="bi bi-arrow-left"></i> Finalized Agendas</a>
<h1><?= e($agenda['title']) ?></h1>
<p><?= e($agenda['agenda_reference']) ?> · <?= e($agenda['agenda_type']) ?></p>
</div>
<span class="public-status good"><?= e($agenda['status']) ?></span>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card">
<div class="card-header">Agenda Items</div>
<div class="card-body p-0">
<?php if(!$agendaItems): ?><div class="p-5 text-center text-muted">No active agenda items.</div><?php endif; ?>
<?php foreach($agendaItems as $i=>$item): ?>
<div class="agenda-public-item">
<div class="agenda-number"><?= e($item['item_number']?:str_pad((string)($i+1),2,'0',STR_PAD_LEFT)) ?></div>
<div class="flex-grow-1">
<div class="d-flex justify-content-between gap-3"><strong><?= e($item['title']) ?></strong><span class="public-status <?= e(portalStatusClass($item['item_status'])) ?>"><?= e($item['item_status']) ?></span></div>
<small><?= e($item['agenda_section']) ?><?= $item['legislative_reference']?' · '.e($item['legislative_reference']):'' ?></small>
<?php if($item['description']): ?><p><?= e($item['description']) ?></p><?php endif; ?>
<?php if($item['disposition']): ?><div class="public-link-note"><i class="bi bi-check2-circle"></i> Disposition: <?= e($item['disposition']) ?></div><?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>

<div class="col-xl-4">
<div class="card portal-card">
<div class="card-header">Agenda Information</div>
<div class="card-body">
<div class="public-side-list">
<div><small>Date</small><strong><?= formatDate($agenda['agenda_date']) ?></strong></div>
<div><small>Time</small><strong><?= e($agenda['start_time']?date('h:i A',strtotime($agenda['start_time'])):'—') ?></strong></div>
<div><small>Venue</small><strong><?= e($agenda['venue']?:'To be announced') ?></strong></div>
<div><small>Committee</small><strong><?= e($agenda['committee_name']?:'—') ?></strong></div>
<div><small>Office</small><strong><?= e($agenda['office_name']?:'—') ?></strong></div>
</div>
<?php if($agenda['description']): ?><div class="public-text-block mt-3"><h3>Description</h3><p><?= nl2br(e($agenda['description'])) ?></p></div><?php endif; ?>
<?php $meetingLink=portalPublicUrl($agenda['meeting_link']); if($meetingLink): ?>
<a class="btn btn-primary w-100 mt-3" href="<?= e($meetingLink) ?>" target="_blank" rel="noopener">Public Meeting Link</a>
<?php endif; ?>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
