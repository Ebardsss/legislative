<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.view');

$pdo=db();
$summary=cefAnalyticsSummary($pdo);

$categories=$pdo->query(
 "SELECT COALESCE(c.name,'Unclassified') category,COUNT(*) total,
         SUM(s.submission_type='Feedback') feedback,
         SUM(s.submission_type='Proposal') proposals,
         SUM(s.submission_type='Complaint') complaints
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  GROUP BY COALESCE(c.name,'Unclassified')
  ORDER BY total DESC,category"
)->fetchAll();

$complaints=$pdo->query(
 "SELECT s.reference_number,s.title,c.urgency_level,c.acknowledgement_target_at,
         c.response_target_at,c.resolution_target_at,c.resolved_at,s.status
  FROM cef_submissions s
  JOIN cef_complaints c ON c.submission_id=s.id
  WHERE s.deleted_at IS NULL
  ORDER BY c.resolution_target_at,s.created_at"
)->fetchAll();

$alerts=$pdo->query(
 "SELECT * FROM cef_analytics_alerts
  ORDER BY status='Open' DESC,created_at DESC"
)->fetchAll();

$pageTitle='Citizen Engagement Analytics Report';$activeMenu='analytics';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow">Management Report</div><h1>Citizen Engagement Analytics Report</h1><p>Summary of civic participation volume, category distribution, complaint SLA performance, and recurring concern alerts.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php">Print</a><a class="btn btn-primary" href="export.php">CSV Export</a></div></div>

<div class="row g-3 mb-4"><?php foreach([
 [$summary['total'],'Submissions'],[$summary['feedback'],'Feedback'],[$summary['proposals'],'Proposals'],
 [$summary['complaints'],'Complaints'],[$summary['overdue_complaints'],'Overdue Complaints'],[$summary['delivered_responses'],'Delivered Responses']
] as [$v,$l]): ?><div class="col-6 col-xl-2"><div class="cef-stat"><i class="bi bi-bar-chart"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card cef-card mb-4"><div class="card-header">Category Distribution</div><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Category</th><th>Total</th><th>Feedback</th><th>Proposals</th><th>Complaints</th></tr></thead><tbody><?php foreach($categories as $r): ?><tr><td><?= e($r['category']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['feedback'] ?></td><td><?= (int)$r['proposals'] ?></td><td><?= (int)$r['complaints'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card cef-card mb-4"><div class="card-header">Complaint SLA Watch</div><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Reference</th><th>Complaint</th><th>Urgency</th><th>Ack Target</th><th>Response Target</th><th>Resolution Target</th><th>Resolved</th><th>Status</th></tr></thead><tbody><?php foreach($complaints as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['urgency_level']) ?></td><td><?= formatDateTime($r['acknowledgement_target_at']) ?></td><td><?= formatDateTime($r['response_target_at']) ?></td><td><?= formatDateTime($r['resolution_target_at']) ?></td><td><?= formatDateTime($r['resolved_at']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card cef-card"><div class="card-header">Recurring Concern Alerts</div><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Dimension</th><th>Value</th><th>Metric</th><th>Severity</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach($alerts as $a): ?><tr><td><?= e($a['dimension_type']?:'—') ?></td><td><?= e($a['dimension_value']?:'—') ?></td><td><?= e($a['metric_value']) ?></td><td><?= e($a['severity']) ?></td><td><?= e($a['status']) ?></td><td><?= formatDateTime($a['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
