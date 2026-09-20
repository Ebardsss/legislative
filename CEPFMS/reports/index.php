<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.reports.view');

$pdo=db();$pageTitle='Reports & Workflow';$activeMenu='reports';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
$summary=cefAnalyticsSummary($pdo);

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-bar-chart-line"></i> Step 7 · Operational Reporting</div><h1>CEPFMS Reports & Workflow Center</h1><p>Consolidated links for citizen intake, moderation, complaint performance, official responses, analytics, and end-to-end civic workflow traceability.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php">Print Summary</a><a class="btn btn-primary" href="export.php?report=workflow">Workflow CSV</a></div></div>

<div class="row g-3 mb-4"><?php foreach([
 [$summary['total'],'Citizen Submissions'],[$summary['pending_moderation'],'Moderation Queue'],
 [$summary['open_complaints'],'Open Complaints'],[$summary['overdue_complaints'],'Overdue Complaints'],
 [$summary['delivered_responses'],'Delivered Responses']
] as [$v,$l]): ?><div class="col-6 col-xl"><div class="cef-stat"><i class="bi bi-bar-chart"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card cef-card mb-4"><div class="card-header">Operational Reports</div><div class="card-body"><div class="row g-3">
<?php foreach([
 ['Public Feedback Report','modules/feedback/report.php','bi-chat-square-text','Feedback category, service area, rating and status.'],
 ['Proposal & Suggestion Report','modules/proposals/report.php','bi-lightbulb','Feasibility, disposition and legislative referral.'],
 ['Complaint & Issue Report','modules/complaints/report.php','bi-exclamation-diamond','Urgency, SLA targets, escalation and resolution.'],
 ['Moderation & Validation Report','modules/moderation/report.php','bi-shield-check','Review rounds, decisions and duplicates.'],
 ['Official Response Report','modules/responses/report.php','bi-reply-all','Draft, approval and delivery records.'],
 ['Citizen Engagement Analytics','modules/analytics/report.php','bi-graph-up-arrow','Volume, categories, SLA and recurring concerns.'],
 ['Live Engagement Workflow','pages/engagement_workflow.php','bi-diagram-3','Submission → moderation → assignment → response trace.'],
 ['Citizen Notification Queue','modules/responses/notifications.php','bi-bell','Portal/internal/external delivery state.'],
] as [$title,$url,$icon,$desc]): ?><div class="col-md-6"><a class="text-decoration-none" href="<?= e(appUrl($url)) ?>"><div class="cef-person h-100"><div><strong><i class="bi <?= e($icon) ?>"></i> <?= e($title) ?></strong><small><?= e($desc) ?></small></div><i class="bi bi-arrow-right"></i></div></a></div><?php endforeach; ?>
</div></div></div>

<div class="card cef-card"><div class="card-header">CSV Exports</div><div class="card-body d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="export.php?report=submissions">All Citizen Submissions</a><a class="btn btn-outline-primary" href="export.php?report=moderation">Moderation</a><a class="btn btn-outline-primary" href="export.php?report=responses">Responses</a><a class="btn btn-primary" href="export.php?report=workflow">End-to-End Workflow</a></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
