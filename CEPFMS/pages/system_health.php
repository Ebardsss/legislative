<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.system_health.view');

$pdo=db();
$pageTitle='CEPFMS System Health';
$activeMenu='system_health';
$extraCss=[
    appUrl('assets/css/dashboard.css'),
    appUrl('assets/css/cepfms-operational.css'),
];

$checks=[];
$addCheck=static function(
    string $group,
    string $name,
    string $status,
    string $details,
    string $expected=''
) use (&$checks): void {
    $checks[]=[
        'group'=>$group,
        'name'=>$name,
        'status'=>$status,
        'details'=>$details,
        'expected'=>$expected,
    ];
};

$safeCount=static function(string $sql,array $params=[]): ?int {
    try{
        $q=db()->prepare($sql);
        $q->execute($params);
        return (int)$q->fetchColumn();
    }catch(Throwable $e){
        error_log('[CEPFMS health count] '.$e->getMessage());
        return null;
    }
};

$dbVersion='Unknown';
try{
    $dbVersion=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $addCheck('Database','MySQL / MariaDB Connection','PASS','Connected to '.DB_NAME.' · '.$dbVersion,'Database connection available');
}catch(Throwable $e){
    $addCheck('Database','MySQL / MariaDB Connection','FAIL','Database connection failed.','Database connection available');
}

$system=null;
try{
    $q=$pdo->prepare(
        "SELECT id,code,name,base_url,status
         FROM systems
         WHERE code='citizen'
         LIMIT 1"
    );
    $q->execute();
    $system=$q->fetch()?:null;
}catch(Throwable $e){
    error_log('[CEPFMS health system] '.$e->getMessage());
}

$systemPass=$system
    && $system['status']==='Active'
    && rtrim((string)$system['base_url'],'/')==='http://localhost/cepfms';

$addCheck(
    'System Registration',
    'Shared CEPFMS System Row',
    $systemPass?'PASS':'FAIL',
    $system
        ? 'ID '.$system['id'].' · '.$system['status'].' · '.($system['base_url']?:'No base URL')
        : 'systems.code = citizen was not found.',
    'Active · http://localhost/cepfms'
);

$requiredMigrations=[
    '001_cepfms_foundation',
    '002_intake_proposals_complaints',
    '003_moderation_responses_analytics',
    '004_admin_rbac_security',
    '005_final_health_deployment',
    '006_ticket_chat_citizen_attachments',
];

$installedMigrations=[];
if(tableExists('cepfms_schema_migrations')){
    try{
        $installedMigrations=$pdo->query(
            'SELECT migration_key,description,applied_at
             FROM cepfms_schema_migrations
             ORDER BY migration_key'
        )->fetchAll();
    }catch(Throwable $e){
        error_log('[CEPFMS health migrations] '.$e->getMessage());
    }
}
$installedKeys=array_column($installedMigrations,'migration_key');

foreach($requiredMigrations as $migration){
    $ok=in_array($migration,$installedKeys,true);
    $addCheck(
        'Migrations',
        $migration,
        $ok?'PASS':'FAIL',
        $ok?'Migration is installed.':'Migration is missing.',
        'Installed'
    );
}

$expectedCefTables=array_values(array_unique(array_merge(
    cepfmsFoundationTables(),
    [
        'cepfms_sequences',
        'cef_service_levels',
        'cef_notification_delivery_attempts',
        'cef_analytics_alerts',
        'cepfms_login_attempts',
    ]
)));

$missingCefTables=[];
foreach($expectedCefTables as $table){
    if(!tableExists($table))$missingCefTables[]=$table;
}

$addCheck(
    'Database',
    'CEPFMS Operational Tables',
    !$missingCefTables?'PASS':'FAIL',
    !$missingCefTables
        ? count($expectedCefTables).' / '.count($expectedCefTables).' expected CEPFMS tables are present.'
        : 'Missing: '.implode(', ',$missingCefTables),
    count($expectedCefTables).' tables'
);

$requiredShared=[
    'systems','permissions','role_permissions','roles','users','user_roles',
    'user_system_access','offices','departments','committees',
    'legislative_items','notifications','activity_logs'
];
$missingShared=array_values(array_filter(
    $requiredShared,
    static fn(string $table): bool => !tableExists($table)
));

$addCheck(
    'Database',
    'Shared Legislative Tables',
    !$missingShared?'PASS':'FAIL',
    !$missingShared
        ? count($requiredShared).' / '.count($requiredShared).' required shared tables are present.'
        : 'Missing: '.implode(', ',$missingShared),
    count($requiredShared).' shared tables'
);

$systemId=cepfmsSystemId();
$permissionCount=$systemId
    ? $safeCount(
        "SELECT COUNT(*)
         FROM permissions
         WHERE system_id=:system
           AND code LIKE 'cepfms.%'",
        [':system'=>$systemId]
    )
    : null;

$addCheck(
    'RBAC',
    'Fine-Grained Permission Definitions',
    $permissionCount===22?'PASS':'FAIL',
    $permissionCount===null?'Unable to count permissions.':$permissionCount.' fine-grained CEPFMS permission(s) found.',
    '22 permissions'
);

$activeAdmins=$safeCount(
    'SELECT COUNT(*)
     FROM users u
     JOIN roles r ON r.id=u.role_id
     JOIN user_system_access usa
       ON usa.user_id=u.id
      AND usa.system_id=:system
      AND usa.status="Active"
     WHERE r.name="Administrator"
       AND u.status="Active"
       AND u.deleted_at IS NULL',
    [':system'=>$systemId]
);

$addCheck(
    'RBAC',
    'Active CEPFMS Administrator',
    ($activeAdmins??0)>=1?'PASS':'FAIL',
    ($activeAdmins??0).' active Administrator account(s) with CEPFMS access.',
    'At least 1'
);

$missingAccess=$safeCount(
    'SELECT COUNT(*)
     FROM users u
     LEFT JOIN user_system_access usa
       ON usa.user_id=u.id
      AND usa.system_id=:system
     WHERE u.deleted_at IS NULL
       AND usa.id IS NULL',
    [':system'=>$systemId]
);
$addCheck(
    'RBAC',
    'Explicit CEPFMS Access Rows',
    $missingAccess===0?'PASS':'FAIL',
    ($missingAccess??0).' shared user(s) missing explicit CEPFMS access rows.',
    '0 missing'
);

$primaryRoleMismatch=$safeCount(
    'SELECT COUNT(*)
     FROM users u
     LEFT JOIN user_roles ur
       ON ur.user_id=u.id
      AND ur.role_id=u.role_id
      AND ur.is_primary=1
     WHERE u.deleted_at IS NULL
       AND ur.user_id IS NULL'
);
$addCheck(
    'RBAC',
    'Shared Primary Role Bridge',
    $primaryRoleMismatch===0?'PASS':'FAIL',
    ($primaryRoleMismatch??0).' user(s) missing a matching primary user_roles record.',
    '0 missing'
);

$weakHashes=$safeCount(
    'SELECT COUNT(*)
     FROM users
     WHERE deleted_at IS NULL
       AND CHAR_LENGTH(password)<50'
);
$addCheck(
    'Security',
    'Password Hash Storage',
    $weakHashes===0?'PASS':'FAIL',
    ($weakHashes??0).' active/non-deleted account(s) have suspiciously short password values.',
    '0 weak/short values'
);

$addCheck(
    'Security',
    'Application Debug Output',
    APP_DEBUG?'WARN':'PASS',
    APP_DEBUG
        ? 'CEPFMS_DEBUG is enabled. Disable it for normal demonstration/deployment.'
        : 'Debug output is disabled; PHP errors are written to logs/php_errors.log.',
    'APP_DEBUG = false'
);

$addCheck(
    'Security',
    'Shared Session Configuration',
    SESSION_NAME==='lph_session'&&SESSION_IDLE_TIMEOUT===(8*60*60)?'PASS':'FAIL',
    'Session '.SESSION_NAME.' · idle timeout '.round(SESSION_IDLE_TIMEOUT/3600,1).' hour(s).',
    'lph_session · 8 hours'
);

$protectedFiles=[
    APP_ROOT.'/.htaccess',
    APP_ROOT.'/config/.htaccess',
    APP_ROOT.'/database/.htaccess',
    APP_ROOT.'/includes/.htaccess',
    APP_ROOT.'/logs/.htaccess',
    APP_ROOT.'/cron/.htaccess',
    APP_ROOT.'/assets/uploads/.htaccess',
];
$missingProtection=array_values(array_filter(
    $protectedFiles,
    static fn(string $file): bool => !is_file($file)
));
$addCheck(
    'Security',
    'Apache Sensitive-Path Protection',
    !$missingProtection?'PASS':'WARN',
    !$missingProtection
        ? 'Root indexing, sensitive folders, logs, cron, and upload script execution are protected by .htaccess.'
        : 'Missing protection file(s): '.implode(', ',array_map('basename',$missingProtection)),
    'Protection files present'
);

$dirs=[
    'Uploads'=>UPLOAD_DIR,
    'Logs'=>APP_ROOT.'/logs',
];
foreach($dirs as $label=>$dir){
    $exists=is_dir($dir);
    $writable=$exists&&is_writable($dir);
    $addCheck(
        'Filesystem',
        $label.' Directory',
        $writable?'PASS':'FAIL',
        ($exists?'Exists':'Missing').' · '.($writable?'Writable':'Not writable').' · '.$dir,
        'Exists and writable'
    );
}

$aiHealth = cepfmsAiService()->healthCheck();
$addCheck(
    'AI Engine',
    'Ollama Local Daemon',
    $aiHealth['available']?'PASS':'WARN',
    $aiHealth['message'],
    'Ollama online at '.OLLAMA_BASE_URL
);
$addCheck(
    'AI Engine',
    'Configured AI Model ('.OLLAMA_MODEL.')',
    !empty($aiHealth['model_installed'])?'PASS':'WARN',
    !empty($aiHealth['model_installed'])
        ? 'Model '.OLLAMA_MODEL.' is ready.'
        : 'Model '.OLLAMA_MODEL.' not yet pulled. (Run: ollama pull '.OLLAMA_MODEL.')',
    OLLAMA_MODEL.' installed'
);


$invalidSla=$safeCount(
    'SELECT COUNT(*)
     FROM cef_service_levels
     WHERE acknowledgement_hours<1
        OR response_hours<acknowledgement_hours
        OR resolution_hours<response_hours'
);
$addCheck(
    'Data Integrity',
    'Complaint SLA Ordering',
    $invalidSla===0?'PASS':'FAIL',
    ($invalidSla??0).' invalid SLA rule(s).',
    '0 invalid'
);

$validatedNoCategory=$safeCount(
    'SELECT COUNT(*)
     FROM cef_submissions
     WHERE moderation_status="Validated"
       AND category_id IS NULL
       AND deleted_at IS NULL'
);
$addCheck(
    'Data Integrity',
    'Validated Submission Classification',
    $validatedNoCategory===0?'PASS':'FAIL',
    ($validatedNoCategory??0).' validated citizen record(s) have no category.',
    '0 unclassified validated records'
);

$responseProblems=$safeCount(
    'SELECT COUNT(*)
     FROM cef_responses r
     JOIN cef_submissions s ON s.id=r.submission_id
     WHERE
       (r.status IN ("Approved","Delivered")
        AND (r.approved_by IS NULL OR r.approved_at IS NULL))
       OR (r.status="Delivered" AND r.delivered_at IS NULL)
       OR (s.moderation_status<>"Validated")'
);
$addCheck(
    'Data Integrity',
    'Official Response Integrity',
    $responseProblems===0?'PASS':'FAIL',
    ($responseProblems??0).' response integrity problem(s) detected.',
    '0 problems'
);

$selfDuplicates=$safeCount(
    'SELECT COUNT(*)
     FROM cef_duplicate_matches
     WHERE submission_id=matched_submission_id'
);
$addCheck(
    'Data Integrity',
    'Duplicate-Match Integrity',
    $selfDuplicates===0?'PASS':'FAIL',
    ($selfDuplicates??0).' self-referencing duplicate match(es).',
    '0 self-references'
);

$externalPending=$safeCount(
    "SELECT COUNT(*)
     FROM cef_notification_recipients
     WHERE delivery_channel IN ('Email','Phone')
       AND delivery_status='Pending'"
);
$addCheck(
    'Operations',
    'External Notification Queue',
    ($externalPending??0)>0?'WARN':'PASS',
    ($externalPending??0).' Email/Phone recipient(s) are Pending. This is expected until an external provider is configured.',
    '0 pending for fully configured external delivery'
);

$overdueComplaints=$safeCount(
    'SELECT COUNT(*)
     FROM cef_submissions s
     JOIN cef_complaints c ON c.submission_id=s.id
     WHERE s.status NOT IN ("Resolved","Closed","Rejected","Withdrawn")
       AND c.resolution_target_at<NOW()
       AND s.deleted_at IS NULL'
);
$addCheck(
    'Operations',
    'Complaint SLA Watch',
    ($overdueComplaints??0)>0?'WARN':'PASS',
    ($overdueComplaints??0).' open complaint(s) are past their resolution target.',
    '0 overdue'
);

$failedLogins=$safeCount(
    'SELECT COUNT(*)
     FROM cepfms_login_attempts
     WHERE was_successful=0
       AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)'
);
$addCheck(
    'Operations',
    'Failed Login Watch',
    ($failedLogins??0)>=5?'WARN':'PASS',
    ($failedLogins??0).' failed login attempt(s) recorded during the last 24 hours.',
    'Monitor for unusual volume'
);

$passCount=count(array_filter($checks,fn($x)=>$x['status']==='PASS'));
$warnCount=count(array_filter($checks,fn($x)=>$x['status']==='WARN'));
$failCount=count(array_filter($checks,fn($x)=>$x['status']==='FAIL'));
$totalChecks=count($checks);
$score=$totalChecks>0?(int)round(($passCount/$totalChecks)*100):0;
$overall=$failCount>0?'ACTION REQUIRED':($warnCount>0?'READY WITH WARNINGS':'READY');

$groups=[];
foreach($checks as $check)$groups[$check['group']][]=$check;

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="cef-head">
<div>
<div class="cef-eyebrow"><i class="bi bi-heart-pulse"></i> Step 11 · Final System Health</div>
<h1>CEPFMS System Health</h1>
<p>Final deployment readiness covering database migrations, operational tables, RBAC, filesystem protection, security configuration, shared account integrity, workflow integrity, and operational watch items.</p>
</div>
<div class="d-flex gap-2">
<a class="btn btn-outline-secondary" href="<?= e(appUrl('database/validation_005_final.sql')) ?>" onclick="return false;" title="Run this SQL from phpMyAdmin"><i class="bi bi-database-check"></i> Final SQL: validation_005_final.sql</a>
<a class="btn btn-primary" href="<?= e(appUrl('dashboard.php')) ?>">Dashboard</a>
</div>
</div>

<div class="row g-3 mb-4">
<div class="col-6 col-xl-3"><div class="cef-stat"><i class="bi bi-activity"></i><div><strong><?= e($overall) ?></strong><small>Overall Health</small></div></div></div>
<div class="col-6 col-xl-3"><div class="cef-stat"><i class="bi bi-check-circle"></i><div><strong><?= $passCount ?></strong><small>Passed Checks</small></div></div></div>
<div class="col-6 col-xl-3"><div class="cef-stat"><i class="bi bi-exclamation-triangle"></i><div><strong><?= $warnCount ?></strong><small>Warnings</small></div></div></div>
<div class="col-6 col-xl-3"><div class="cef-stat"><i class="bi bi-x-octagon"></i><div><strong><?= $failCount ?></strong><small>Failed Checks</small></div></div></div>
</div>

<div class="card cef-card mb-4">
<div class="card-body">
<div class="d-flex justify-content-between mb-2"><strong>Deployment Health Score</strong><span><?= $score ?>%</span></div>
<div class="progress" style="height:14px"><div class="progress-bar <?= $failCount?'bg-danger':($warnCount?'bg-warning text-dark':'bg-success') ?>" style="width:<?= $score ?>%"></div></div>
<div class="small text-muted mt-2"><?= $totalChecks ?> runtime readiness checks were evaluated. WARN items are operational/watch conditions and do not necessarily mean the application is broken.</div>
</div>
</div>

<?php foreach($groups as $group=>$items): ?>
<div class="card cef-card mb-4">
<div class="card-header"><?= e($group) ?></div>
<div class="table-responsive">
<table class="table cef-table mb-0">
<thead><tr><th style="width:26%">Check</th><th style="width:12%">Status</th><th>Current Result</th><th style="width:22%">Expected</th></tr></thead>
<tbody>
<?php foreach($items as $item): ?>
<tr>
<td><strong><?= e($item['name']) ?></strong></td>
<td>
<span class="cef-status <?= $item['status']==='PASS'?'good':($item['status']==='WARN'?'warn':'bad') ?>">
<?= e($item['status']) ?>
</span>
</td>
<td><?= e($item['details']) ?></td>
<td><?= e($item['expected']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endforeach; ?>

<div class="alert alert-info">
<strong>Final database verification:</strong>
after installing Migration 006, run <code>database/validation_006_ticket_chat_citizen_attachments.sql</code> from phpMyAdmin. Every row under <strong>FINAL INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS</strong> should normally return <code>problem_rows = 0</code>.
</div>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
