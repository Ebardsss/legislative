<?php
declare(strict_types=1);

require_once __DIR__.'/includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.dashboard.view');

$pdo=db();$pageTitle='Dashboard';$activeMenu='dashboard';
$extraCss=[appUrl('assets/css/dashboard.css'),appUrl('assets/css/cepfms-operational.css')];

$summary=cefAnalyticsSummary($pdo);
$monthly=cefMonthlyTrend($pdo,6);

$moderationQueue=$pdo->query(
 "SELECT id,reference_number,submission_type,title,moderation_status,priority_level,created_at
  FROM cef_submissions
  WHERE deleted_at IS NULL
    AND moderation_status IN ('Pending','Under Review','Needs Clarification')
  ORDER BY FIELD(moderation_status,'Under Review','Pending','Needs Clarification'),created_at
  LIMIT 8"
)->fetchAll();

$complaintWatch=$pdo->query(
 "SELECT s.id,s.reference_number,s.title,s.status,c.urgency_level,c.resolution_target_at
  FROM cef_submissions s
  JOIN cef_complaints c ON c.submission_id=s.id
  WHERE s.deleted_at IS NULL
    AND s.status NOT IN ('Resolved','Closed','Rejected','Withdrawn')
  ORDER BY
    (c.resolution_target_at<NOW()) DESC,
    FIELD(c.urgency_level,'Urgent','High','Normal','Low'),
    c.resolution_target_at
  LIMIT 8"
)->fetchAll();

$responseQueue=$pdo->query(
 "SELECT r.id,r.response_reference,r.subject,r.status,r.updated_at,
         s.reference_number submission_reference,s.title submission_title
  FROM cef_responses r
  JOIN cef_submissions s ON s.id=r.submission_id
  WHERE r.status IN ('Draft','Returned','Under Review','Approved')
  ORDER BY FIELD(r.status,'Returned','Under Review','Approved','Draft'),r.updated_at
  LIMIT 8"
)->fetchAll();

$categories=$pdo->query(
 "SELECT COALESCE(c.name,'Unclassified') label,COUNT(*) total
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  GROUP BY COALESCE(c.name,'Unclassified')
  ORDER BY total DESC
  LIMIT 8"
)->fetchAll();

$recentActivity=[];
if(cepfmsSystemId()&&tableExists('activity_logs')){
    $q=$pdo->prepare(
        'SELECT al.action,al.details,al.created_at,u.full_name
         FROM activity_logs al
         LEFT JOIN users u ON u.id=al.user_id
         WHERE al.system_id=:system
         ORDER BY al.created_at DESC,al.id DESC
         LIMIT 50'
    );
    $q->execute([':system'=>cepfmsSystemId()]);
    $recentActivity=$q->fetchAll();
}

include __DIR__.'/layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/layouts/sidebar.php'; ?><main class="main-content">

<section class="dashboard-page-header">
<div>
<div class="dashboard-eyebrow"><i class="bi bi-chat-square-heart"></i> City Council of Manila · Civic Engagement Operations</div>
<h1>Citizen Engagement and Public Feedback Management System</h1>
<p>Live operational overview for citizen submissions, moderation, complaint response performance, official communication, public tracking, and recurring civic concerns.</p>
</div>
<div class="dashboard-header-actions">
<a href="<?= e(citizenPortalUrl('index.php')) ?>" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-globe2"></i> Citizen Portal</a>
<a href="<?= e(appUrl('reports/index.php')) ?>" class="btn btn-primary"><i class="bi bi-bar-chart-line"></i> Reports</a>
</div>
</section>

<div class="row g-3 mb-4"><?php foreach([
 [$summary['total'],'Citizen Submissions','bi-inboxes'],
 [$summary['pending_moderation'],'Moderation Queue','bi-shield-check'],
 [$summary['open_complaints'],'Open Complaints','bi-exclamation-diamond'],
 [$summary['overdue_complaints'],'Overdue Complaints','bi-alarm'],
 [$summary['delivered_responses'],'Delivered Responses','bi-send-check'],
 [(int)$pdo->query("SELECT COUNT(*) FROM cef_analytics_alerts WHERE status='Open'")->fetchColumn(),'Trend Alerts','bi-bullseye']
] as [$v,$l,$i]): ?><div class="col-6 col-xl-2"><div class="cef-stat h-100"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-4 mb-4">
<div class="col-xl-8"><div class="card cef-card h-100"><div class="card-header card-header-clean">Six-Month Citizen Engagement Trend</div><div class="card-body"><canvas id="monthlyChart" height="110"></canvas></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header card-header-clean">Top Civic Categories</div><div class="card-body"><canvas id="categoryChart" height="165"></canvas></div></div></div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header d-flex justify-content-between align-items-center"><span>Moderation Queue</span><a class="btn btn-sm btn-outline-primary" href="<?= e(appUrl('modules/moderation/index.php')) ?>">Open</a></div><div class="list-group list-group-flush"><?php if(!$moderationQueue): ?><div class="list-group-item text-muted">No pending moderation records.</div><?php endif; ?><?php foreach($moderationQueue as $r): ?><a class="list-group-item list-group-item-action" href="<?= e(appUrl('modules/moderation/view.php?id='.$r['id'])) ?>"><strong class="small"><?= e($r['reference_number'].' · '.$r['title']) ?></strong><div class="small text-muted"><?= e($r['submission_type'].' · '.$r['priority_level'].' · '.$r['moderation_status']) ?></div></a><?php endforeach; ?></div></div></div>

<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header d-flex justify-content-between align-items-center"><span>Complaint SLA Watch</span><a class="btn btn-sm btn-outline-primary" href="<?= e(appUrl('modules/complaints/index.php')) ?>">Open</a></div><div class="list-group list-group-flush"><?php if(!$complaintWatch): ?><div class="list-group-item text-muted">No active complaints.</div><?php endif; ?><?php foreach($complaintWatch as $r): $over=$r['resolution_target_at']&&strtotime($r['resolution_target_at'])<time(); ?><a class="list-group-item list-group-item-action" href="<?= e(appUrl('modules/complaints/view.php?id='.$r['id'])) ?>"><strong class="small"><?= e($r['reference_number'].' · '.$r['title']) ?></strong><div class="small <?= $over?'text-danger fw-bold':'text-muted' ?>"><?= e($r['urgency_level'].' · '.$r['status']) ?> · <?= formatDateTime($r['resolution_target_at']) ?><?= $over?' · OVERDUE':'' ?></div></a><?php endforeach; ?></div></div></div>

<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header d-flex justify-content-between align-items-center"><span>Response Workflow</span><a class="btn btn-sm btn-outline-primary" href="<?= e(appUrl('modules/responses/index.php')) ?>">Open</a></div><div class="list-group list-group-flush"><?php if(!$responseQueue): ?><div class="list-group-item text-muted">No response drafts or approvals pending.</div><?php endif; ?><?php foreach($responseQueue as $r): ?><a class="list-group-item list-group-item-action" href="<?= e(appUrl('modules/responses/view.php?id='.$r['id'])) ?>"><strong class="small"><?= e($r['response_reference'].' · '.$r['subject']) ?></strong><div class="small text-muted"><?= e($r['submission_reference'].' · '.$r['status']) ?> · <?= formatDateTime($r['updated_at']) ?></div></a><?php endforeach; ?></div></div></div>
</div>

<div class="row g-4">
<div class="col-xl-6"><div class="card cef-card h-100" id="cepfmsRecentActivityCard">
    <div class="card-header">Recent CEPFMS Activity</div>
    <div class="list-group list-group-flush" id="cepfmsActivityList">
        <?php if(!$recentActivity): ?>
            <div class="list-group-item text-muted">No recent CEPFMS activity.</div>
        <?php endif; ?>
        <?php foreach($recentActivity as $idx => $a): ?>
            <div class="list-group-item <?= $idx >= 5 ? 'cepfms-activity-extra d-none' : '' ?>">
                <strong class="small"><?= e($a['action']) ?></strong>
                <div class="small text-muted"><?= e($a['full_name']?:'Public/System') ?> · <?= formatDateTime($a['created_at']) ?></div>
                <?php if($a['details']): ?>
                    <div class="small"><?= e(mb_strimwidth($a['details'],0,150,'…')) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if(count($recentActivity) > 5): ?>
        <div class="card-footer bg-transparent border-top-0 pt-2 pb-3 px-3">
            <button type="button" class="btn btn-sm btn-light border w-100 py-1 fw-semibold text-secondary d-flex align-items-center justify-content-center gap-2" id="btnToggleRecentActivityBottom" onclick="toggleRecentActivity()" style="font-size: 0.8rem; border-radius: 8px;">
                <i class="bi bi-chevron-down" id="iconToggleActivityBottom"></i>
                <span id="textToggleActivityBottom">See All (<?= count($recentActivity) ?>)</span>
            </button>
        </div>
    <?php endif; ?>
</div></div>
<div class="col-xl-6"><div class="card cef-card h-100"><div class="card-header">Operational Shortcuts</div><div class="card-body"><div class="row g-2"><?php foreach([
 ['Moderation & Validation','modules/moderation/index.php','bi-shield-check'],
 ['Response Management','modules/responses/index.php','bi-reply-all'],
 ['Citizen Notifications','modules/responses/notifications.php','bi-bell'],
 ['Citizen Analytics','modules/analytics/index.php','bi-graph-up-arrow'],
 ['Engagement Workflow','pages/engagement_workflow.php','bi-diagram-3'],
 ['Reports Center','reports/index.php','bi-bar-chart-line'],
] as [$label,$url,$icon]): ?><div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="<?= e(appUrl($url)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a></div><?php endforeach; ?></div></div></div></div>
</div>

</main></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const monthly=<?= json_encode($monthly,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('monthlyChart'),{
   type:'bar',
   data:{
     labels:monthly.map(x=>x.label),
     datasets:[
       {label:'Feedback',data:monthly.map(x=>Number(x.feedback)),backgroundColor:'#1d6fb8',borderColor:'#1d6fb8',borderWidth:1,borderRadius:4},
       {label:'Proposals',data:monthly.map(x=>Number(x.proposals)),backgroundColor:'#b8860b',borderColor:'#b8860b',borderWidth:1,borderRadius:4},
       {label:'Complaints',data:monthly.map(x=>Number(x.complaints)),backgroundColor:'#1a3a5c',borderColor:'#1a3a5c',borderWidth:1,borderRadius:4}
     ]
   },
   options:{responsive:true,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
 });
 const cats=<?= json_encode($categories,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('categoryChart'),{
   type:'doughnut',
   data:{
     labels:cats.map(x=>x.label),
     datasets:[{
       data:cats.map(x=>Number(x.total)),
       backgroundColor:['#b8860b','#1d6fb8','#1a3a5c','#3b82f6','#0284c7','#0f2137','#64748b']
     }]
   },
   options:{responsive:true}
 });
});
</script>
<style>
.cepfms-activity-extra:not(.d-none) {
    animation: fadeInActivity 0.25s ease-in-out;
}
@keyframes fadeInActivity {
    from { opacity: 0; transform: translateY(-3px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<script>
function toggleRecentActivity() {
  const extras = document.querySelectorAll('.cepfms-activity-extra');
  if (!extras.length) return;
  const isHidden = extras[0].classList.contains('d-none');

  extras.forEach(el => {
    if (isHidden) {
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  });

  const totalCount = extras.length + 5;
  const textBottom = document.getElementById('textToggleActivityBottom');
  const iconBottom = document.getElementById('iconToggleActivityBottom');

  if (isHidden) {
    if (textBottom) textBottom.textContent = 'Hide';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-up';
  } else {
    if (textBottom) textBottom.textContent = 'See All (' + totalCount + ')';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-down';

    const card = document.getElementById('cepfmsRecentActivityCard');
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
}
</script>
<?php include __DIR__.'/layouts/footer.php'; ?>
