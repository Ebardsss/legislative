<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.view');

$rows=db()->query(
 "SELECT r.response_reference,r.response_type,r.subject,r.status,r.created_at,r.reviewed_at,
         r.approved_at,r.delivered_at,s.reference_number submission_reference,
         s.submission_type,s.title submission_title,d.full_name drafted_name,
         a.full_name approved_name
  FROM cef_responses r
  JOIN cef_submissions s ON s.id=r.submission_id
  LEFT JOIN users d ON d.id=r.drafted_by
  LEFT JOIN users a ON a.id=r.approved_by
  ORDER BY r.created_at DESC,r.id DESC"
)->fetchAll();

$pageTitle='Response Management Report';$activeMenu='responses';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow">Operational Report</div><h1>Official Response Report</h1><p>Response drafting, review, approval, release, submission linkage and delivery timestamps.</p></div><a class="btn btn-outline-secondary" href="index.php">Response Management</a></div>
<div class="card cef-card"><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Response</th><th>Citizen Submission</th><th>Type</th><th>Subject</th><th>Drafted</th><th>Approved</th><th>Delivered</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= e($r['response_reference']) ?></td><td><?= e($r['submission_reference']) ?><div class="small text-muted"><?= e($r['submission_title']) ?></div></td><td><?= e($r['response_type']) ?></td><td><?= e($r['subject']) ?></td><td><?= formatDateTime($r['created_at']) ?><div class="small text-muted"><?= e($r['drafted_name']?:'—') ?></div></td><td><?= formatDateTime($r['approved_at']) ?><div class="small text-muted"><?= e($r['approved_name']?:'—') ?></div></td><td><?= formatDateTime($r['delivered_at']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
