<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.view');

$rows=db()->query(
 "SELECT s.reference_number,s.submission_type,s.title,s.created_at,s.status,s.moderation_status,
         c.name category_name,r.review_round,r.decision,r.reviewed_at,u.full_name reviewer_name,
         (SELECT COUNT(*) FROM cef_duplicate_matches dm WHERE dm.submission_id=s.id AND dm.status='Confirmed') confirmed_duplicates
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  LEFT JOIN cef_moderation_reviews r
    ON r.id=(SELECT r2.id FROM cef_moderation_reviews r2 WHERE r2.submission_id=s.id ORDER BY r2.review_round DESC,r2.id DESC LIMIT 1)
  LEFT JOIN users u ON u.id=r.reviewer_id
  WHERE s.deleted_at IS NULL
  ORDER BY s.created_at DESC"
)->fetchAll();

$pageTitle='Moderation & Validation Report';$activeMenu='moderation';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow">Operational Report</div><h1>Moderation & Validation Report</h1><p>Submission review status, latest moderation round, reviewer, decision, duplicate confirmation and release state.</p></div><a class="btn btn-outline-secondary" href="index.php">Moderation Queue</a></div>
<div class="card cef-card"><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Reference</th><th>Type</th><th>Submission</th><th>Category</th><th>Moderation</th><th>Round</th><th>Reviewer</th><th>Decision</th><th>Duplicates</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['submission_type']) ?></td><td><?= e($r['title']) ?><div class="small text-muted"><?= formatDateTime($r['created_at']) ?></div></td><td><?= e($r['category_name']?:'Unclassified') ?></td><td><?= e($r['moderation_status']) ?></td><td><?= (int)($r['review_round']??0) ?></td><td><?= e($r['reviewer_name']?:'—') ?></td><td><?= e($r['decision']?:'Pending') ?></td><td><?= (int)$r['confirmed_duplicates'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
