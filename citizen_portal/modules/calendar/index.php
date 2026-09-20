<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$pageTitle='Agenda & Calendar';
$activeMenu='calendar';
$extraCss=[appUrl('assets/css/public-modules.css')];

$tab=clean($_GET['tab']??'events');
if(!in_array($tab,['events','agendas'],true))$tab='events';
$search=clean($_GET['search']??'');
$page=max(1,(int)($_GET['page']??1));

$params=[];
if($tab==='events'){
    $where=[
        'e.status IN ("Confirmed","In Progress","Completed")',
        '(
            (e.agenda_id IS NOT NULL AND a.status IN ("Finalized","Archived"))
            OR
            (e.legislative_item_id IS NOT NULL AND li.visibility="Public" AND li.deleted_at IS NULL)
        )',
    ];

    if($search!==''){
        $where[]='(e.event_reference LIKE :s1 OR e.title LIKE :s2 OR e.event_type LIKE :s3 OR e.venue LIKE :s4)';
        $like='%'.$search.'%';
        $params=[':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like];
    }

    $whereSql='WHERE '.implode(' AND ',$where);

    $count=$pdo->prepare(
        'SELECT COUNT(*)
         FROM lacms_calendar_events e
         LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
         LEFT JOIN legislative_items li ON li.id=e.legislative_item_id
         '.$whereSql
    );
    $count->execute($params);
    $total=(int)$count->fetchColumn();
    $pg=publicModulePagination($total,$page,12);

    $q=$pdo->prepare(
        'SELECT
            e.id,e.event_reference,e.event_type,e.title,e.description,
            e.start_datetime,e.end_datetime,e.all_day,e.venue,e.meeting_link,e.status,
            a.agenda_reference,a.title agenda_title,
            c.name committee_name,o.name office_name
         FROM lacms_calendar_events e
         LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
         LEFT JOIN legislative_items li ON li.id=e.legislative_item_id
         LEFT JOIN committees c ON c.id=e.committee_id
         LEFT JOIN offices o ON o.id=e.office_id
         '.$whereSql.'
         ORDER BY e.start_datetime DESC,e.id DESC
         LIMIT '.$pg['perPage'].' OFFSET '.$pg['offset']
    );
    $q->execute($params);
    $rows=$q->fetchAll();
}else{
    $where=['a.status IN ("Finalized","Archived")'];

    if($search!==''){
        $where[]='(a.agenda_reference LIKE :s1 OR a.title LIKE :s2 OR a.agenda_type LIKE :s3 OR a.venue LIKE :s4)';
        $like='%'.$search.'%';
        $params=[':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like];
    }

    $whereSql='WHERE '.implode(' AND ',$where);

    $count=$pdo->prepare(
        'SELECT COUNT(*) FROM lacms_agendas a '.$whereSql
    );
    $count->execute($params);
    $total=(int)$count->fetchColumn();
    $pg=publicModulePagination($total,$page,12);

    $q=$pdo->prepare(
        'SELECT
            a.id,a.agenda_reference,a.title,a.agenda_type,a.agenda_date,
            a.start_time,a.end_time,a.venue,a.description,a.status,
            c.name committee_name,o.name office_name,
            (SELECT COUNT(*) FROM lacms_agenda_items ai WHERE ai.agenda_id=a.id AND ai.item_status<>"Removed") item_count
         FROM lacms_agendas a
         LEFT JOIN committees c ON c.id=a.committee_id
         LEFT JOIN offices o ON o.id=a.office_id
         '.$whereSql.'
         ORDER BY a.agenda_date DESC,a.id DESC
         LIMIT '.$pg['perPage'].' OFFSET '.$pg['offset']
    );
    $q->execute($params);
    $rows=$q->fetchAll();
}

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<span class="eyebrow">LACMS PUBLIC SCHEDULE</span>
<h1>Legislative Agenda & Calendar</h1>
<p>View finalized legislative agendas and confirmed public-facing legislative schedules.</p>
</div>
<a class="btn btn-outline-secondary" href="<?= e(appUrl('dashboard.php')) ?>"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>

<div class="public-tabs mb-3">
<a class="<?= $tab==='events'?'active':'' ?>" href="<?= e(appUrl('modules/calendar/index.php?tab=events')) ?>"><i class="bi bi-calendar-event"></i> Calendar Events</a>
<a class="<?= $tab==='agendas'?'active':'' ?>" href="<?= e(appUrl('modules/calendar/index.php?tab=agendas')) ?>"><i class="bi bi-list-check"></i> Finalized Agendas</a>
</div>

<div class="public-filter-card mb-4">
<form class="row g-2 align-items-end">
<input type="hidden" name="tab" value="<?= e($tab) ?>">
<div class="col-lg-10">
<label class="form-label">Search</label>
<input class="form-control" name="search" value="<?= e($search) ?>" placeholder="<?= $tab==='events'?'Reference, title, event type or venue':'Reference, agenda title, type or venue' ?>">
</div>
<div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
</form>
</div>

<div class="small text-muted mb-3"><strong><?= $total ?></strong> public <?= $tab==='events'?'calendar event(s)':'agenda(s)' ?></div>

<div class="row g-3">
<?php if(!$rows): ?><div class="col-12"><div class="public-empty"><i class="bi bi-calendar-x"></i><strong>No public schedule found.</strong><span>Try another search.</span></div></div><?php endif; ?>

<?php foreach($rows as $r): ?>
<div class="col-lg-6">
<?php if($tab==='events'): ?>
<a class="public-record-card" href="<?= e(appUrl('modules/calendar/view.php?id='.(int)$r['id'])) ?>">
<div class="public-record-top"><span class="public-code"><?= e($r['event_reference']) ?></span><span class="public-type"><?= e($r['event_type']) ?></span></div>
<h2><?= e($r['title']) ?></h2>
<p><?= e(portalExcerpt($r['description'])) ?></p>
<div class="public-meta">
<span><i class="bi bi-calendar3"></i><?= formatDateTime($r['start_datetime']) ?></span>
<span><i class="bi bi-geo-alt"></i><?= e($r['venue']?:'Venue to be announced') ?></span>
</div>
<?php if($r['agenda_reference']): ?><div class="public-link-note"><i class="bi bi-list-check"></i> Agenda <?= e($r['agenda_reference']) ?></div><?php endif; ?>
</a>
<?php else: ?>
<a class="public-record-card" href="<?= e(appUrl('modules/calendar/agenda.php?id='.(int)$r['id'])) ?>">
<div class="public-record-top"><span class="public-code"><?= e($r['agenda_reference']) ?></span><span class="public-type"><?= e($r['agenda_type']) ?></span></div>
<h2><?= e($r['title']) ?></h2>
<p><?= e(portalExcerpt($r['description'])) ?></p>
<div class="public-meta">
<span><i class="bi bi-calendar3"></i><?= formatDate($r['agenda_date']) ?></span>
<span><i class="bi bi-list-ol"></i><?= (int)$r['item_count'] ?> agenda item(s)</span>
</div>
</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<div class="public-pagination mt-4">
<?= publicModulePaginationHtml($pg,'modules/calendar/index.php',['tab'=>$tab,'search'=>$search]) ?>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
