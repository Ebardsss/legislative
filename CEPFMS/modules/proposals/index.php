<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.proposals.view');
$pdo=db();$pageTitle='Proposal & Suggestion Management';$activeMenu='proposals';$extraCss=[appUrl('assets/css/cepfms-operational.css')];
$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');$feas=clean($_GET['feasibility']??'');
$where=["s.submission_type='Proposal'","s.deleted_at IS NULL"];$params=[];
if($search!==''){$where[]='(s.reference_number LIKE :s1 OR s.title LIKE :s2 OR p.proposed_solution LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='s.status=:status';$params[':status']=$status;}
if($feas!==''){$where[]='p.feasibility_status=:feas';$params[':feas']=$feas;}
$q=$pdo->prepare(
"SELECT s.*,c.name category_name,p.problem_statement,p.proposed_solution,p.expected_public_benefit,
 p.estimated_scope,p.feasibility_status,p.disposition,
 (SELECT COUNT(*) FROM cef_legislative_referrals r WHERE r.submission_id=s.id) referral_count
 FROM cef_submissions s JOIN cef_proposals p ON p.submission_id=s.id
 LEFT JOIN cef_categories c ON c.id=s.category_id
 WHERE ".implode(' AND ',$where)." ORDER BY s.created_at DESC,s.id DESC"
);$q->execute($params);$rows=$q->fetchAll();
$stats=$pdo->query("SELECT COUNT(*) total,SUM(p.feasibility_status='For Study') study_count,SUM(p.feasibility_status='Feasible') feasible,SUM(p.feasibility_status='Referred') referred,SUM(s.status IN ('Resolved','Closed')) closed_count FROM cef_submissions s JOIN cef_proposals p ON p.submission_id=s.id WHERE s.submission_type='Proposal' AND s.deleted_at IS NULL")->fetch()?:[];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-lightbulb"></i> Step 3 · Operational Backend</div><h1>Proposal & Suggestion Management</h1><p>Review citizen ideas, assess feasibility, document disposition, refer proposals to existing legislative records, and preserve the civic-to-legislative trace.</p></div><a class="btn btn-outline-secondary" target="_blank" href="<?= e(citizenPortalUrl('index.php')) ?>">Citizen Portal</a></div>
<div class="cef-flow"><div><strong>1. Citizen Idea</strong><small>Proposal submitted</small></div><div><strong>2. Classify</strong><small>Category and priority</small></div><div><strong>3. Study</strong><small>Feasibility assessment</small></div><div><strong>4. Disposition</strong><small>Accept / revise / decline</small></div><div><strong>5. Refer</strong><small>Legislative item linkage</small></div><div><strong>6. Track</strong><small>Citizen-visible progress</small></div></div>
<div class="row g-3 mb-3"><?php foreach([['Proposals',$stats['total']??0,'bi-lightbulb'],['For Study',$stats['study_count']??0,'bi-search'],['Feasible',$stats['feasible']??0,'bi-check-circle'],['Referred',$stats['referred']??0,'bi-arrow-up-right-square'],['Resolved/Closed',$stats['closed_count']??0,'bi-archive']] as [$l,$v,$i]): ?><div class="col-6 col-xl"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>
<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>"></div><div class="col-xl-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach(cefSubmissionStatuses() as $x): ?><option <?= $status===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><div class="col-xl-3"><label class="form-label small">Feasibility</label><select class="form-select form-select-sm" name="feasibility"><option value="">All</option><?php foreach(['Not Reviewed','For Study','Feasible','Needs Revision','Not Feasible','Referred'] as $x): ?><option <?= $feas===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div></form></div></div>
<div class="card cef-card"><div class="card-header d-flex justify-content-between"><span>Proposal Registry</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover cef-table mb-0"><thead><tr><th>Proposal</th><th>Category / Scope</th><th>Citizen</th><th>Feasibility</th><th>Disposition</th><th>Referral</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No proposal submissions found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="cef-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= formatDateTime($r['created_at']) ?></div></td><td><?= e($r['category_name']?:'Unclassified') ?><div class="small text-muted"><?= e($r['estimated_scope']?:'Scope not specified') ?></div></td><td><?= $r['anonymous_flag']?'Anonymous':e($r['citizen_name']?:'Not provided') ?></td><td><?= e($r['feasibility_status']) ?></td><td><?= e($r['disposition']?:'—') ?></td><td><?= (int)$r['referral_count'] ?></td><td><span class="cef-status <?= e(cefStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
