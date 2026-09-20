<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.workflow.view');

$pdo=db();$pageTitle='Citizen Engagement Workflow';$activeMenu='engagement_workflow';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');
$where=['s.deleted_at IS NULL'];$params=[];
if($search!==''){
    $where[]='(s.reference_number LIKE :s1 OR s.title LIKE :s2)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;
}

$q=$pdo->prepare(
 "SELECT s.id,s.reference_number,s.submission_type,s.title,s.status,s.moderation_status,
         s.priority_level,s.created_at,c.name category_name,
         mr.decision moderation_decision,mr.reviewed_at moderation_reviewed_at,
         a.id assignment_id,a.status assignment_status,
         COALESCE(o.name,cm.name,u.full_name) assignment_owner,
         r.id response_id,r.response_reference,r.status response_status,r.delivered_at,
         (SELECT COUNT(*) FROM cef_followups f WHERE f.submission_id=s.id) followup_count,
         (SELECT COUNT(*) FROM cef_legislative_referrals lr WHERE lr.submission_id=s.id) referral_count
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  LEFT JOIN cef_moderation_reviews mr
    ON mr.id=(SELECT x.id FROM cef_moderation_reviews x WHERE x.submission_id=s.id ORDER BY x.review_round DESC,x.id DESC LIMIT 1)
  LEFT JOIN cef_assignments a
    ON a.id=(SELECT x.id FROM cef_assignments x WHERE x.submission_id=s.id AND x.status<>'Cancelled' ORDER BY x.assignment_role='Primary' DESC,x.id DESC LIMIT 1)
  LEFT JOIN offices o ON o.id=a.office_id
  LEFT JOIN committees cm ON cm.id=a.committee_id
  LEFT JOIN users u ON u.id=a.assigned_user_id
  LEFT JOIN cef_responses r
    ON r.id=(SELECT x.id FROM cef_responses x WHERE x.submission_id=s.id ORDER BY x.created_at DESC,x.id DESC LIMIT 1)
  WHERE ".implode(' AND ',$where)."
  ORDER BY s.created_at DESC,s.id DESC
  LIMIT 300"
);
$q->execute($params);$rows=$q->fetchAll();

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-diagram-3"></i> End-to-End Civic Traceability</div><h1>Citizen Engagement Workflow</h1><p>Trace each citizen record from public submission through moderation, routing, official response, secure citizen tracking, follow-up, and legislative referral.</p></div><a class="btn btn-outline-secondary" target="_blank" href="<?= e(citizenPortalUrl('index.php')) ?>"><i class="bi bi-globe2"></i> Citizen Portal</a></div>

<div class="cef-flow">
<div><strong>1. Submission</strong><small>Public reference + token</small></div>
<div><strong>2. Moderation</strong><small>Validation decision</small></div>
<div><strong>3. Assignment</strong><small>Office / committee / staff</small></div>
<div><strong>4. Response</strong><small>Draft → approval → publish</small></div>
<div><strong>5. Tracking</strong><small>Public-visible updates</small></div>
<div><strong>6. Insight</strong><small>Analytics / referrals</small></div>
</div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2"><div class="col-md-10"><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search reference or title"></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Trace</button></div></form></div></div>

<div class="card cef-card"><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Submission</th><th>Moderation</th><th>Assignment</th><th>Response</th><th>Follow-Ups</th><th>Legislative Referral</th><th>Current State</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No engagement workflow records found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?>
<tr>
<td><span class="cef-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e(cefSubmissionTypeLabel($r['submission_type']).' · '.($r['category_name']?:'Unclassified')) ?></div></td>
<td><?= e($r['moderation_status']) ?><?php if($r['moderation_decision']): ?><div class="small text-muted"><?= e($r['moderation_decision']) ?> · <?= formatDateTime($r['moderation_reviewed_at']) ?></div><?php endif; ?></td>
<td><?= e($r['assignment_owner']?:'Not assigned') ?><div class="small text-muted"><?= e($r['assignment_status']?:'') ?></div></td>
<td><?php if($r['response_id']): ?><a href="<?= e(appUrl('modules/responses/view.php?id='.$r['response_id'])) ?>"><?= e($r['response_reference']) ?></a><div class="small text-muted"><?= e($r['response_status']) ?><?= $r['delivered_at']?' · '.formatDateTime($r['delivered_at']):'' ?></div><?php else: ?>—<?php endif; ?></td>
<td><?= (int)$r['followup_count'] ?></td>
<td><?= (int)$r['referral_count'] ?></td>
<td><span class="cef-status <?= e(cefStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
