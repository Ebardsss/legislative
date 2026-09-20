<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/citizen_services.php';
requireLogin();

$pdo=db();
$pageTitle='System Health';
$activeMenu='';
$extraCss=[appUrl('assets/css/account.css')];

$checks=[];

function addHealthCheck(array &$checks,string $name,bool $ok,string $details=''): void
{
    $checks[]=[
        'name'=>$name,
        'ok'=>$ok,
        'details'=>$details,
    ];
}

function healthTableExists(PDO $pdo,string $table): bool
{
    $q=$pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema=:schema
           AND table_name=:table'
    );
    $q->execute([
        ':schema'=>DB_NAME,
        ':table'=>$table,
    ]);

    return (int)$q->fetchColumn()===1;
}

try{
    $pdo->query('SELECT 1')->fetchColumn();
    addHealthCheck($checks,'Shared Database',true,'Connected');
}catch(Throwable $e){
    addHealthCheck($checks,'Shared Database',false,'Unavailable');
}

addHealthCheck(
    $checks,
    'PHP Runtime',
    version_compare(PHP_VERSION,'8.0.0','>='),
    'PHP '.PHP_VERSION
);

addHealthCheck(
    $checks,
    'PDO MySQL',
    extension_loaded('pdo_mysql'),
    extension_loaded('pdo_mysql')?'Available':'Missing'
);

$systemReady=false;
try{
    $q=$pdo->query(
        "SELECT COUNT(*)
         FROM systems
         WHERE code='public_portal'
           AND status='Active'"
    );
    $systemReady=(int)$q->fetchColumn()===1;
}catch(Throwable $e){}
addHealthCheck($checks,'Citizen Portal Registration',$systemReady,$systemReady?'Active':'Missing or inactive');

$permissionReady=false;
try{
    $q=$pdo->query(
        "SELECT COUNT(*)
         FROM permissions
         WHERE code='public_portal.access'"
    );
    $permissionReady=(int)$q->fetchColumn()===1;
}catch(Throwable $e){}
addHealthCheck($checks,'Citizen Portal Permission',$permissionReady,$permissionReady?'Ready':'Missing');

$groups=[
    'Citizen Account'=>[
        'citizen_portal_schema_migrations',
        'citizen_portal_profiles',
        'citizen_portal_login_attempts',
        'citizen_portal_account_security',
    ],
    'ORLMS'=>[
        'legislative_items',
        'legislative_item_types',
        'orlms_publications',
        'orlms_item_details',
    ],
    'LACMS'=>[
        'lacms_agendas',
        'lacms_agenda_items',
        'lacms_calendar_events',
    ],
    'VQDSS'=>[
        'vqd_decisions',
        'vqd_vote_tallies',
        'vqd_result_validations',
    ],
    'PHCMS'=>[
        'hearings',
        'stakeholders',
        'registrations',
        'hearing_documents',
    ],
    'CEPFMS'=>[
        'cef_submissions',
        'cef_responses',
        'cef_response_documents',
        'cef_followups',
        'cef_categories',
    ],
];

foreach($groups as $label=>$tables){
    $missing=[];

    foreach($tables as $table){
        try{
            if(!healthTableExists($pdo,$table))$missing[]=$table;
        }catch(Throwable $e){
            $missing[]=$table;
        }
    }

    addHealthCheck(
        $checks,
        $label.' Connection',
        !$missing,
        !$missing?'Required data source ready':'One or more required tables are missing'
    );
}

$logDir=APP_ROOT.'/logs';
addHealthCheck(
    $checks,
    'Application Log Folder',
    is_dir($logDir)&&is_writable($logDir),
    is_dir($logDir)&&is_writable($logDir)?'Writable':'Check folder permission'
);

$readyCount=count(array_filter($checks,static fn(array $c): bool=>$c['ok']));
$totalChecks=count($checks);
$allReady=$readyCount===$totalChecks;

$counts=[
    'ORLMS'=>0,
    'LACMS'=>0,
    'VQDSS'=>0,
    'PHCMS'=>0,
    'My CEPFMS'=>0,
];

try{
    $counts['ORLMS']=(int)$pdo->query(
        'SELECT COUNT(DISTINCT li.id)
         FROM legislative_items li
         JOIN orlms_publications p ON p.legislative_item_id=li.id
         WHERE li.deleted_at IS NULL
           AND li.visibility="Public"
           AND p.publication_status="Published"
           AND p.release_classification="Public"'
    )->fetchColumn();

    $counts['LACMS']=(int)$pdo->query(
        'SELECT COUNT(*)
         FROM lacms_agendas
         WHERE status IN ("Finalized","Archived")'
    )->fetchColumn();

    $counts['VQDSS']=(int)$pdo->query(
        'SELECT COUNT(*)
         FROM vqd_decisions
         WHERE record_status="Published"
           AND release_classification="Public"'
    )->fetchColumn();

    $counts['PHCMS']=(int)$pdo->query(
        'SELECT COUNT(*)
         FROM hearings
         WHERE visibility="Public"
           AND status IN ("Upcoming","Ongoing","Completed")'
    )->fetchColumn();

    $q=$pdo->prepare(
        'SELECT COUNT(*)
         FROM cef_submissions
         WHERE citizen_user_id=:user
           AND deleted_at IS NULL'
    );
    $q->execute([':user'=>currentUserId()]);
    $counts['My CEPFMS']=(int)$q->fetchColumn();
}catch(Throwable $e){
    error_log('[Citizen Portal health counts] '.$e->getMessage());
}


try{
    $q=$pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema=:schema
           AND table_name="cef_response_documents"
           AND column_name="visibility"'
    );
    $q->execute([':schema'=>DB_NAME]);
    $visibilityReady=(int)$q->fetchColumn()===1;
    addHealthCheck(
        $checks,
        'CEPFMS Citizen Attachment Visibility',
        $visibilityReady,
        $visibilityReady?'Ready':'Run CEPFMS Migration 006'
    );
}catch(Throwable $e){
    addHealthCheck($checks,'CEPFMS Citizen Attachment Visibility',false,'Run CEPFMS Migration 006');
}

$uploadRoot=citizenCefUploadRoot();
$uploadReadable=is_dir($uploadRoot)&&is_readable($uploadRoot);
addHealthCheck(
    $checks,
    'CEPFMS Shared Upload Folder',
    $uploadReadable,
    $uploadReadable?'Readable':'Expected sibling folder: cepfms/assets/uploads'
);

$readyCount=count(array_filter($checks,static fn(array $c): bool=>$c['ok']));
$totalChecks=count($checks);
$allReady=$readyCount===$totalChecks;

include __DIR__.'/../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<span class="eyebrow">FINAL SYSTEM CHECK</span>
<h1>Citizen Portal System Health</h1>
<p>Checks the portal connection to the shared database and the five legislative systems without exposing internal records.</p>
</div>
<span class="public-status <?= $allReady?'good':'bad' ?>"><?= $allReady?'READY':'CHECK REQUIRED' ?></span>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card">
<div class="card-header">Readiness Checks</div>
<div class="table-responsive">
<table class="table public-table mb-0">
<thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>
<tbody>
<?php foreach($checks as $c): ?>
<tr>
<td><?= e($c['name']) ?></td>
<td><span class="public-status <?= $c['ok']?'good':'bad' ?>"><?= $c['ok']?'READY':'CHECK' ?></span></td>
<td><?= e($c['details']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<div class="col-xl-4">
<div class="card portal-card mb-4">
<div class="card-header">Overall</div>
<div class="card-body text-center">
<div class="health-score"><?= $readyCount ?><small>/<?= $totalChecks ?></small></div>
<div class="text-muted small">readiness checks passed</div>
</div>
</div>

<div class="card portal-card">
<div class="card-header">Citizen-Visible Records</div>
<div class="card-body public-side-list">
<?php foreach($counts as $label=>$count): ?>
<div><small><?= e($label) ?></small><strong><?= (int)$count ?></strong></div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
