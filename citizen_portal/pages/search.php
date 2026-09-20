<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/public_data.php';
requireLogin();

$pdo=db();
$pageTitle='Search Public Records';
$activeMenu='search';
$extraCss=[appUrl('assets/css/public-modules.css')];

$term=clean($_GET['q']??'');
$system=clean($_GET['system']??'');
$results=[];

if(mb_strlen($term)>=2){
    $like='%'.$term.'%';

    if($system===''||$system==='ORLMS'){
        $q=$pdo->prepare(
            'SELECT DISTINCT li.id,li.reference_number reference,li.title,
                    COALESCE(li.summary,d.subject) summary,
                    "ORLMS" source_system,
                    "Ordinance / Resolution" source_type
             FROM legislative_items li
             JOIN orlms_publications p
               ON p.legislative_item_id=li.id
              AND p.publication_status="Published"
              AND p.release_classification="Public"
             LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
             WHERE li.deleted_at IS NULL
               AND li.visibility="Public"
               AND (li.reference_number LIKE :s1 OR li.title LIKE :s2 OR li.summary LIKE :s3 OR d.subject LIKE :s4)
             ORDER BY li.updated_at DESC
             LIMIT 20'
        );
        $q->execute([':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like]);
        foreach($q->fetchAll() as $r){
            $r['url']=appUrl('modules/ordinances/view.php?id='.(int)$r['id']);
            $results[]=$r;
        }
    }

    if($system===''||$system==='LACMS'){
        $q=$pdo->prepare(
            'SELECT a.id,a.agenda_reference reference,a.title,a.description summary,
                    "LACMS" source_system,"Finalized Agenda" source_type
             FROM lacms_agendas a
             WHERE a.status IN ("Finalized","Archived")
               AND (a.agenda_reference LIKE :s1 OR a.title LIKE :s2 OR a.description LIKE :s3)
             ORDER BY a.agenda_date DESC
             LIMIT 20'
        );
        $q->execute([':s1'=>$like,':s2'=>$like,':s3'=>$like]);
        foreach($q->fetchAll() as $r){
            $r['url']=appUrl('modules/calendar/agenda.php?id='.(int)$r['id']);
            $results[]=$r;
        }
    }

    if($system===''||$system==='VQDSS'){
        $q=$pdo->prepare(
            'SELECT d.id,COALESCE(d.decision_record_number,d.reference_number) reference,
                    COALESCE(li.title,d.decision_statement) title,
                    d.decision_statement summary,
                    "VQDSS" source_system,"Voting Result" source_type
             FROM vqd_decisions d
             LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
             WHERE d.record_status="Published"
               AND d.release_classification="Public"
               AND (d.reference_number LIKE :s1 OR d.decision_record_number LIKE :s2 OR d.decision_statement LIKE :s3 OR li.title LIKE :s4)
             ORDER BY COALESCE(d.published_at,d.decision_datetime) DESC
             LIMIT 20'
        );
        $q->execute([':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like]);
        foreach($q->fetchAll() as $r){
            $r['url']=appUrl('modules/voting/view.php?id='.(int)$r['id']);
            $results[]=$r;
        }
    }

    if($system===''||$system==='PHCMS'){
        $q=$pdo->prepare(
            'SELECT h.id,COALESCE(h.reference_number,CONCAT("HEARING-",h.id)) reference,
                    h.title,h.description summary,
                    "PHCMS" source_system,"Public Hearing" source_type
             FROM hearings h
             WHERE h.visibility="Public"
               AND h.status IN ("Upcoming","Ongoing","Completed")
               AND (h.reference_number LIKE :s1 OR h.title LIKE :s2 OR h.description LIKE :s3)
             ORDER BY h.hearing_date DESC,h.hearing_time DESC
             LIMIT 20'
        );
        $q->execute([':s1'=>$like,':s2'=>$like,':s3'=>$like]);
        foreach($q->fetchAll() as $r){
            $r['url']=appUrl('modules/hearings/view.php?id='.(int)$r['id']);
            $results[]=$r;
        }
    }

    if($system===''||$system==='CEPFMS'){
        $q=$pdo->prepare(
            'SELECT s.id,s.reference_number reference,s.title,
                    COALESCE(s.summary,s.details) summary,
                    "CEPFMS" source_system,CONCAT("My ",s.submission_type) source_type
             FROM cef_submissions s
             WHERE s.citizen_user_id=:user
               AND s.deleted_at IS NULL
               AND (s.reference_number LIKE :s1 OR s.title LIKE :s2 OR s.summary LIKE :s3 OR s.details LIKE :s4)
             ORDER BY s.updated_at DESC
             LIMIT 20'
        );
        $q->execute([
            ':user'=>currentUserId(),
            ':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like
        ]);
        foreach($q->fetchAll() as $r){
            $r['url']=appUrl('modules/engagement/view.php?id='.(int)$r['id']);
            $results[]=$r;
        }
    }
}

include __DIR__.'/../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div><span class="eyebrow">UNIFIED PUBLIC SEARCH</span><h1>Search Legislative Records</h1><p>Search public records from ORLMS, LACMS, VQDSS and PHCMS together with your own CEPFMS submissions.</p></div>
</div>

<div class="public-filter-card mb-4">
<form class="row g-2 align-items-end">
<div class="col-lg-8"><label class="form-label">Search</label><input class="form-control" name="q" value="<?= e($term) ?>" placeholder="Reference number, title or keyword"></div>
<div class="col-lg-2"><label class="form-label">System</label><select class="form-select" name="system"><option value="">All Systems</option><?php foreach(['ORLMS','LACMS','VQDSS','PHCMS','CEPFMS'] as $x): ?><option <?= $system===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
</form>
</div>

<?php if($term!==''&&mb_strlen($term)<2): ?><div class="alert alert-warning">Enter at least 2 characters.</div><?php endif; ?>

<div class="small text-muted mb-3"><strong><?= count($results) ?></strong> result(s)</div>

<div class="row g-3">
<?php if($term!==''&&!$results): ?><div class="col-12"><div class="public-empty"><i class="bi bi-search"></i><strong>No matching public records.</strong><span>Try another keyword.</span></div></div><?php endif; ?>
<?php foreach($results as $r): ?>
<div class="col-lg-6">
<a class="public-record-card" href="<?= e($r['url']) ?>">
<div class="public-record-top"><span class="public-code"><?= e($r['reference']) ?></span><span class="public-type"><?= e($r['source_system']) ?></span></div>
<h2><?= e($r['title']) ?></h2>
<p><?= e(portalExcerpt($r['summary'])) ?></p>
<div class="public-meta"><span><i class="bi bi-folder2-open"></i><?= e($r['source_type']) ?></span></div>
</a>
</div>
<?php endforeach; ?>
</div>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
