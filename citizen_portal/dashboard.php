<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/public_data.php';
requireLogin();

$pdo = db();
$pageTitle = 'Citizen Dashboard';
$activeMenu = 'dashboard';
$extraCss = [appUrl('assets/css/public-modules.css')];

$counts = ['ordinances' => 0, 'calendar' => 0, 'voting' => 0, 'hearings' => 0, 'engagement' => 0, 'surveys' => 0];
$upcoming = [];
$recentLegislation = [];
$activeSurveys = [];

try {
    $counts['ordinances'] = (int)$pdo->query(
        'SELECT COUNT(DISTINCT li.id)
         FROM legislative_items li
         JOIN orlms_publications p ON p.legislative_item_id=li.id
         WHERE li.deleted_at IS NULL
           AND li.visibility="Public"
           AND p.publication_status="Published"
           AND p.release_classification="Public"'
    )->fetchColumn();

    $counts['calendar'] = (int)$pdo->query(
        'SELECT COUNT(*)
         FROM lacms_calendar_events e
         LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
         LEFT JOIN legislative_items li ON li.id=e.legislative_item_id
         WHERE e.status IN ("Confirmed","In Progress","Completed")
           AND (
             (e.agenda_id IS NOT NULL AND a.status IN ("Finalized","Archived"))
             OR
             (e.legislative_item_id IS NOT NULL AND li.visibility="Public" AND li.deleted_at IS NULL)
           )'
    )->fetchColumn();

    $counts['voting'] = (int)$pdo->query(
        'SELECT COUNT(*) FROM vqd_decisions
         WHERE record_status="Published"
           AND release_classification="Public"'
    )->fetchColumn();

    $counts['hearings'] = (int)$pdo->query(
        'SELECT COUNT(*) FROM hearings
         WHERE visibility="Public"
           AND status IN ("Upcoming","Ongoing","Completed")'
    )->fetchColumn();

    $q = $pdo->prepare(
        'SELECT COUNT(*) FROM cef_submissions
         WHERE citizen_user_id=:user
           AND deleted_at IS NULL'
    );
    $q->execute([':user' => currentUserId()]);
    $counts['engagement'] = (int)$q->fetchColumn();

    $counts['surveys'] = (int)$pdo->query(
        'SELECT COUNT(*) FROM surveys
         WHERE status="Active"
           AND (opens_at IS NULL OR opens_at <= NOW())
           AND (closes_at IS NULL OR closes_at >= NOW())'
    )->fetchColumn();

    $survStmt = $pdo->prepare(
        'SELECT s.id, s.title, s.description, s.closes_at,
                h.reference_number hearing_reference, h.title hearing_title,
                li.reference_number legislative_reference,
                (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) question_count,
                (SELECT COUNT(*) FROM survey_submissions ss WHERE ss.survey_id = s.id AND ss.user_id = :user) answered
         FROM surveys s
         LEFT JOIN hearings h ON h.id = s.hearing_id
         LEFT JOIN legislative_items li ON li.id = s.legislative_item_id
         WHERE s.status = "Active"
           AND (s.closes_at IS NULL OR s.closes_at >= NOW())
         ORDER BY s.created_at DESC
         LIMIT 3'
    );
    $survStmt->execute([':user' => currentUserId()]);
    $activeSurveys = $survStmt->fetchAll(PDO::FETCH_ASSOC);

    $upcoming = $pdo->query(
        'SELECT id,reference_number,title,hearing_date,hearing_time,status
         FROM hearings
         WHERE visibility="Public"
           AND status IN ("Upcoming","Ongoing")
         ORDER BY hearing_date,hearing_time
         LIMIT 5'
    )->fetchAll();

    $recentLegislation = $pdo->query(
        'SELECT li.id, li.reference_number, li.title, lit.name as item_type, p.publication_date, p.publication_reference
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         JOIN orlms_publications p ON p.legislative_item_id=li.id
         WHERE li.deleted_at IS NULL
           AND li.visibility="Public"
           AND p.publication_status="Published"
           AND p.release_classification="Public"
         ORDER BY p.publication_date DESC, li.updated_at DESC
         LIMIT 5'
    )->fetchAll();

} catch (Throwable $e) {
    error_log('[Citizen dashboard] ' . $e->getMessage());
    $upcoming = [];
    $recentLegislation = [];
}

include __DIR__ . '/layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/layouts/sidebar.php'; ?>
<main class="main-content">

    <!-- Executive Dashboard Page Header (Matches ORLMS & CEPFMS) -->
    <div class="dashboard-page-header">
        <div>
            <div class="dashboard-eyebrow">
                <i class="bi bi-bank2"></i> City Council of Manila &bull; Legislative Citizen Gateway
            </div>
            <h1>Welcome, <?= e(currentUser()['full_name'] ?? 'Citizen User') ?></h1>
            <p>Unified digital gateway providing citizens transparent, synchronized access to enacted city ordinances, official session agendas, voting decisions, public consultation hearings, and community feedback.</p>
        </div>
        <div class="dashboard-header-actions">
            <a class="btn btn-outline-secondary" href="<?= e(appUrl('pages/search.php')) ?>">
                <i class="bi bi-search"></i> Search Records
            </a>
            <a class="btn btn-primary" href="<?= e(appUrl('modules/engagement/index.php')) ?>">
                <i class="bi bi-chat-square-heart"></i> Submit Feedback
            </a>
        </div>
    </div>

    <!-- Executive Stat Metric Cards (5 Columns) -->
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['ordinances', 'bi-journal-check', 'Published Measures', $counts['ordinances'], 'Ordinances & Resolutions from ORLMS'],
            ['calendar', 'bi-calendar-event', 'Legislative Calendar', $counts['calendar'], 'Agendas & Deadlines from LACMS'],
            ['voting', 'bi-check2-square', 'Voting Decisions', $counts['voting'], 'Roll-Call Decisions from VQDSS'],
            ['hearings', 'bi-people', 'Public Hearings', $counts['hearings'], 'Consultation Notices from PHCMS'],
            ['engagement', 'bi-chat-square-heart', 'My Civic Activity', $counts['engagement'], 'Submissions & Inquiries from CEPFMS'],
        ] as [$url, $icon, $title, $value, $sub]): ?>
            <div class="col-sm-6 col-xl">
                <a class="portal-stat text-decoration-none" href="<?= e(appUrl('modules/' . $url . '/index.php')) ?>">
                    <i class="bi <?= e($icon) ?>"></i>
                    <div>
                        <strong><?= (int)$value ?></strong>
                        <span><?= e($title) ?></span>
                        <small><?= e($sub) ?></small>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main Operational Workspace Grid -->
    <div class="row g-4">
        <!-- Left Column: Services Directory & Recent Publications -->
        <div class="col-xl-8">
            <!-- Citizen Services Directory -->
            <div class="card portal-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Integrated Citizen Services</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ([
                            ['modules/ordinances/index.php', 'bi-journal-text', 'Published Legislation', 'Read enacted municipal ordinances and approved resolutions synchronized from ORLMS.'],
                            ['modules/calendar/index.php', 'bi-calendar3', 'Legislative Schedule', 'Inspect finalized City Council agendas, session calendars, and confirmed timelines from LACMS.'],
                            ['modules/voting/index.php', 'bi-bar-chart-steps', 'Voting Results', 'Explore published voting outcomes, quorum records, and roll-call decisions from VQDSS.'],
                            ['modules/hearings/index.php', 'bi-megaphone', 'Public Hearings', 'View scheduled public hearings, attendee information, and citizen consultation records from PHCMS.'],
                            ['modules/engagement/index.php', 'bi-chat-left-text', 'Citizen Engagement', 'Submit civic proposals, community concerns, or service feedback, and track resolution status in CEPFMS.'],
                            ['pages/search.php', 'bi-search', 'Unified Record Search', 'Perform instant cross-system keyword searches across published records, committee files, and citizen records.'],
                        ] as [$url, $icon, $title, $desc]): ?>
                            <div class="col-md-6">
                                <a class="service-link" href="<?= e(appUrl($url)) ?>">
                                    <i class="bi <?= e($icon) ?>"></i>
                                    <div>
                                        <strong><?= e($title) ?></strong>
                                        <small><?= e($desc) ?></small>
                                    </div>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Published Measures Table -->
            <div class="card portal-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-check me-2 text-primary"></i>Latest Enacted Measures & Published Legislation</span>
                    <a href="<?= e(appUrl('modules/ordinances/index.php')) ?>" class="btn btn-sm btn-outline-primary">Browse All</a>
                </div>
                <div class="table-responsive">
                    <table class="table dashboard-table mb-0">
                        <thead>
                            <tr>
                                <th>Item Number</th>
                                <th>Title / Subject</th>
                                <th>Classification</th>
                                <th>Published Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLegislation)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                        No recent published legislation available.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentLegislation as $leg): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <?= e($leg['reference_number']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e(mb_strimwidth($leg['title'], 0, 55, '...')) ?></div>
                                            <?php if (!empty($leg['publication_reference'])): ?>
                                                <small class="text-muted">Pub Ref: <?= e($leg['publication_reference']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <?= e($leg['item_type'] ?? 'Measure') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted small">
                                                <i class="bi bi-clock me-1"></i><?= !empty($leg['publication_date']) ? formatDate($leg['publication_date']) : 'Published' ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= e(appUrl('modules/ordinances/view.php?id=' . (int)$leg['id'])) ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Upcoming Hearings & Consultation Surveys -->
        <div class="col-xl-4">
            <!-- Active Consultation Surveys -->
            <div class="card portal-card mb-4" style="border-top: 3.5px solid #a97900 !important;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-ui-checks-grid me-2 text-warning"></i>Active Civic Surveys</span>
                    <a href="<?= e(appUrl('modules/surveys/index.php')) ?>" class="btn btn-sm btn-outline-primary">Browse All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($activeSurveys)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-ui-checks fs-2 d-block mb-1 text-secondary opacity-50"></i>
                            <small>No active consultation surveys at this moment.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeSurveys as $as): ?>
                            <a class="related-public-link" href="<?= e(appUrl('modules/surveys/take.php?id=' . (int)$as['id'])) ?>">
                                <i class="bi bi-patch-question fs-4" style="color: #a97900;"></i>
                                <div class="w-100">
                                    <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                        <small class="text-muted text-truncate" style="max-width: 140px;"><?= e($as['hearing_reference'] ?: 'Consultation') ?></small>
                                        <?php if ((int)$as['answered'] > 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-1.5" style="font-size: 0.68rem;">Answered ✓</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1.5" style="font-size: 0.68rem;">Participate</span>
                                        <?php endif; ?>
                                    </div>
                                    <strong class="d-block text-dark"><?= e(mb_strimwidth($as['title'], 0, 42, '...')) ?></strong>
                                    <span class="text-secondary small mt-1 d-flex align-items-center justify-content-between">
                                        <span><i class="bi bi-list-check me-1"></i><?= (int)$as['question_count'] ?> questions</span>
                                        <?php if (!empty($as['closes_at'])): ?>
                                            <span class="text-danger" style="font-size: 0.72rem;"><i class="bi bi-clock me-1"></i><?= formatDate($as['closes_at']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Hearings Queue -->
            <div class="card portal-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-check me-2 text-primary"></i>Upcoming Public Hearings</span>
                    <a href="<?= e(appUrl('modules/hearings/index.php')) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                            No upcoming public hearings scheduled at this time.
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming as $h): ?>
                            <a class="related-public-link" href="<?= e(appUrl('modules/hearings/view.php?id=' . (int)$h['id'])) ?>">
                                <i class="bi bi-calendar-event fs-4 text-primary"></i>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1"><?= e($h['status']) ?></span>
                                        <small class="text-muted"><?= e($h['reference_number']) ?></small>
                                    </div>
                                    <strong><?= e(mb_strimwidth($h['title'], 0, 50, '...')) ?></strong>
                                    <span class="text-secondary small mt-1">
                                        <i class="bi bi-clock me-1"></i><?= formatDate($h['hearing_date']) ?> &bull; <?= e(date('h:i A', strtotime($h['hearing_time']))) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Citizen Protection & Verified Access Notice -->
            <div class="card portal-card">
                <div class="card-header">
                    <span><i class="bi bi-shield-check me-2 text-success"></i>Official Citizen Verification</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="p-2 rounded-3 bg-light border text-primary fs-3">
                            <i class="bi bi-patch-check-fill text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Verified Public Records</div>
                            <small class="text-muted">All published legislation, session minutes, and voting records shown on this portal are certified official City Council records.</small>
                        </div>
                    </div>

                    <div class="safe-note mb-3">
                        <i class="bi bi-lock-fill"></i>
                        <div>
                            <strong>Data Privacy Protected</strong>
                            <span>Citizen identities, personal feedback, and sensitive submissions are strictly guarded under Republic Act No. 10173 (Data Privacy Act).</span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="<?= e(appUrl('pages/profile.php')) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-person-gear me-1"></i> Manage Citizen Profile
                        </a>
                        <a href="<?= e(appUrl('pages/account_security.php')) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-shield-lock me-1"></i> Password & Security
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
</div>
<?php include __DIR__ . '/layouts/footer.php'; ?>
