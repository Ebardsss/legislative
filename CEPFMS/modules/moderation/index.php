<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.view');

$pdo=db();$pageTitle='Moderation & Validation';$activeMenu='moderation';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');
$type=clean($_GET['submission_type']??'');
$moderation=clean($_GET['moderation_status']??'');

$where=['s.deleted_at IS NULL'];$params=[];
if($search!==''){
    $where[]='(s.reference_number LIKE :s1 OR s.title LIKE :s2 OR s.details LIKE :s3)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
}
if($type!==''){$where[]='s.submission_type=:type';$params[':type']=$type;}
if($moderation!==''){$where[]='s.moderation_status=:moderation';$params[':moderation']=$moderation;}

$q=$pdo->prepare(
 "SELECT s.*,c.name category_name,
         r.id review_id,r.review_round,r.decision,r.reviewer_id,r.reviewed_at,
         u.full_name reviewer_name,
         (SELECT COUNT(*) FROM cef_moderation_checklist cl
          WHERE cl.moderation_review_id=r.id AND cl.status='Pass') pass_count,
         (SELECT COUNT(*) FROM cef_moderation_checklist cl
          WHERE cl.moderation_review_id=r.id) checklist_count,
         (SELECT COUNT(*) FROM cef_duplicate_matches dm
          WHERE dm.submission_id=s.id AND dm.status IN ('Suggested','Confirmed')) duplicate_count
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  LEFT JOIN cef_moderation_reviews r
    ON r.id=(
      SELECT r2.id FROM cef_moderation_reviews r2
      WHERE r2.submission_id=s.id
      ORDER BY r2.review_round DESC,r2.id DESC LIMIT 1
    )
  LEFT JOIN users u ON u.id=r.reviewer_id
  WHERE ".implode(' AND ',$where)."
  ORDER BY FIELD(s.moderation_status,'Pending','Under Review','Needs Clarification','Validated','Duplicate','Rejected'),
           s.created_at DESC,s.id DESC"
);
$q->execute($params);$rows=$q->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,
         SUM(moderation_status='Pending') pending,
         SUM(moderation_status='Under Review') under_review,
         SUM(moderation_status='Needs Clarification') clarification,
         SUM(moderation_status='Validated') validated,
         SUM(moderation_status IN ('Rejected','Duplicate')) closed_review
  FROM cef_submissions
  WHERE deleted_at IS NULL"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head">
<div>
<div class="cef-eyebrow"><i class="bi bi-shield-check"></i> Step 5 · Controlled Intake Review</div>
<h1>Moderation & Validation</h1>
<p>Review every citizen submission for completeness, relevance, content, duplication, classification, and contact handling before it enters official assignment and response processing.</p>
</div>
<a class="btn btn-outline-secondary" href="report.php"><i class="bi bi-bar-chart"></i> Moderation Report</a>
</div>

<div class="cef-flow">
<div><strong>1. Queue</strong><small>New citizen records</small></div>
<div><strong>2. Screen</strong><small>Completeness / relevance</small></div>
<div><strong>3. Compare</strong><small>Duplicate scan</small></div>
<div><strong>4. Classify</strong><small>Type / category / urgency</small></div>
<div><strong>5. Decide</strong><small>Validate / clarify / reject</small></div>
<div><strong>6. Release</strong><small>Assignment / response</small></div>
</div>

<div class="row g-3 mb-3"><?php foreach([
 ['All Submissions',$stats['total']??0,'bi-inboxes'],
 ['Pending',$stats['pending']??0,'bi-hourglass'],
 ['Under Review',$stats['under_review']??0,'bi-search'],
 ['Needs Clarification',$stats['clarification']??0,'bi-question-circle'],
 ['Validated',$stats['validated']??0,'bi-check-circle'],
 ['Rejected/Duplicate',$stats['closed_review']??0,'bi-x-circle']
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title, or details"></div>
<div class="col-xl-3"><label class="form-label small">Submission Type</label><select class="form-select form-select-sm" name="submission_type"><option value="">All Types</option><?php foreach(cefSubmissionTypes() as $x): ?><option <?= $type===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">Moderation Status</label><select class="form-select form-select-sm" name="moderation_status"><option value="">All</option><?php foreach(cefModerationStatuses() as $x): ?><option <?= $moderation===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card cef-card">
<div class="card-header">Moderation Queue</div>
<div class="table-responsive"><table class="table table-hover cef-table mb-0">
<thead><tr><th>Submission</th><th>Type</th><th>Category</th><th>Citizen</th><th>Checklist</th><th>Duplicates</th><th>Reviewer</th><th>Moderation</th><th class="text-end">Review</th></tr></thead>
<tbody>
<?php if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-5">No moderation records found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?>
<tr>
<td><span class="cef-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= formatDateTime($r['created_at']) ?></div></td>
<td><?= e(cefSubmissionTypeLabel($r['submission_type'])) ?></td>
<td><?= e($r['category_name']?:'Unclassified') ?></td>
<td><?= $r['anonymous_flag']?'Anonymous':e($r['citizen_name']?:'Not provided') ?></td>
<td><?= (int)$r['pass_count'] ?>/<?= (int)$r['checklist_count'] ?> passed</td>
<td><?= (int)$r['duplicate_count'] ?></td>
<td><?= e($r['reviewer_name']?:'Unassigned') ?><?php if($r['review_round']): ?><div class="small text-muted">Round <?= (int)$r['review_round'] ?></div><?php endif; ?></td>
<td><span class="cef-status <?= e(cefStatusClass($r['status'])) ?>"><?= e($r['moderation_status']) ?></span></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-shield-check"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
