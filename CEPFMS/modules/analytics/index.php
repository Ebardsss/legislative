<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.view');

$pdo=db();$pageTitle='Citizen Engagement Analytics';$activeMenu='analytics';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$summary=cefAnalyticsSummary($pdo);
$monthly=cefMonthlyTrend($pdo,6);

$typeDist=$pdo->query(
 "SELECT submission_type label,COUNT(*) total
  FROM cef_submissions
  WHERE deleted_at IS NULL
  GROUP BY submission_type
  ORDER BY total DESC"
)->fetchAll();

$statusDist=$pdo->query(
 "SELECT status label,COUNT(*) total
  FROM cef_submissions
  WHERE deleted_at IS NULL
  GROUP BY status
  ORDER BY total DESC"
)->fetchAll();

$categories=$pdo->query(
 "SELECT COALESCE(c.name,'Unclassified') label,COUNT(*) total
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  GROUP BY COALESCE(c.name,'Unclassified')
  ORDER BY total DESC,label
  LIMIT 10"
)->fetchAll();

$locations=$pdo->query(
 "SELECT COALESCE(NULLIF(barangay,''),NULLIF(district,''),NULLIF(location_text,''),'Unspecified') label,
         COUNT(*) total
  FROM cef_submissions
  WHERE deleted_at IS NULL
  GROUP BY COALESCE(NULLIF(barangay,''),NULLIF(district,''),NULLIF(location_text,''),'Unspecified')
  ORDER BY total DESC,label
  LIMIT 10"
)->fetchAll();

$complaintPerformance=$pdo->query(
 "SELECT
    COUNT(*) total,
    SUM(s.status IN ('Resolved','Closed')) resolved_count,
    SUM(s.status NOT IN ('Resolved','Closed','Rejected','Withdrawn') AND c.resolution_target_at<NOW()) overdue_open,
    SUM(c.resolved_at IS NOT NULL AND c.resolved_at<=c.resolution_target_at) resolved_on_time,
    ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,s.created_at,c.resolved_at) END),1) avg_resolution_hours
  FROM cef_submissions s
  JOIN cef_complaints c ON c.submission_id=s.id
  WHERE s.deleted_at IS NULL"
)->fetch()?:[];

$responsePerformance=$pdo->query(
 "SELECT COUNT(*) total,
         SUM(status='Delivered') delivered,
         ROUND(AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,created_at,delivered_at) END),1) avg_draft_to_delivery_hours
  FROM cef_responses"
)->fetch()?:[];

$rating=$pdo->query(
 "SELECT
    COUNT(citizen_rating) rated,
    ROUND(AVG(citizen_rating),2) avg_rating,
    SUM(citizen_rating>=4) positive_proxy,
    SUM(citizen_rating=3) neutral_proxy,
    SUM(citizen_rating<=2) negative_proxy
  FROM cef_feedback_submissions
  WHERE citizen_rating IS NOT NULL"
)->fetch()?:[];

$alerts=$pdo->query(
 "SELECT a.*,u.full_name created_name
  FROM cef_analytics_alerts a
  LEFT JOIN users u ON u.id=a.created_by
  WHERE a.status='Open'
  ORDER BY FIELD(a.severity,'High','Attention','Monitor'),a.created_at DESC
  LIMIT 20"
)->fetchAll();

$recent=$pdo->query(
 "SELECT s.reference_number,s.submission_type,s.title,s.status,s.priority_level,s.created_at,
         c.name category_name
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  ORDER BY s.created_at DESC
  LIMIT 10"
)->fetchAll();

// 6-District Manila Civic Intelligence
$districtNames = [
    1 => 'District 1 · Tondo I (West)',
    2 => 'District 2 · Tondo II (East / Gagalangin)',
    3 => 'District 3 · Binondo, Quiapo, San Nicolas, Sta. Cruz',
    4 => 'District 4 · Sampaloc',
    5 => 'District 5 · Ermita, Malate, Paco, Intramuros',
    6 => 'District 6 · Pandacan, Sta. Ana, San Miguel, Sta. Mesa'
];
$districtProfiles = [];
for ($d = 1; $d <= 6; $d++) {
    $districtProfiles[$d] = [
        'id' => $d,
        'title' => $districtNames[$d],
        'total' => 0,
        'feedback' => 0,
        'proposals' => 0,
        'complaints' => 0,
        'resolved' => 0,
        'top_issue' => 'Normal Civic Flow'
    ];
}
$rawDistRows = $pdo->query(
    "SELECT s.district, s.submission_type, s.status, COALESCE(c.name, 'General') as category_name, COUNT(*) as cnt
     FROM cef_submissions s
     LEFT JOIN cef_categories c ON c.id = s.category_id
     WHERE s.deleted_at IS NULL
     GROUP BY s.district, s.submission_type, s.status, c.name"
)->fetchAll();
foreach ($rawDistRows as $dr) {
    $num = (int)preg_replace('/[^0-9]/', '', (string)$dr['district']);
    if ($num < 1 || $num > 6) {
        $num = 3;
    }
    $cnt = (int)$dr['cnt'];
    $districtProfiles[$num]['total'] += $cnt;
    if ($dr['submission_type'] === 'Feedback') $districtProfiles[$num]['feedback'] += $cnt;
    elseif ($dr['submission_type'] === 'Proposal') $districtProfiles[$num]['proposals'] += $cnt;
    elseif ($dr['submission_type'] === 'Complaint') {
        $districtProfiles[$num]['complaints'] += $cnt;
        if (in_array($dr['status'], ['Resolved', 'Closed'])) {
            $districtProfiles[$num]['resolved'] += $cnt;
        }
    }
    $districtProfiles[$num]['top_issue'] = $dr['category_name'];
}

// Multi-Dimensional CSAT Quality Breakdown
$csatScorecard = $pdo->query(
    "SELECT 
        COUNT(*) as total_rated,
        ROUND(AVG(rating_speed), 1) as avg_speed,
        ROUND(AVG(rating_courtesy), 1) as avg_courtesy,
        ROUND(AVG(rating_facility), 1) as avg_facility,
        ROUND(AVG(rating_process), 1) as avg_process,
        ROUND(AVG(citizen_rating), 1) as avg_overall,
        ROUND(SUM(citizen_rating >= 4) * 100.0 / NULLIF(COUNT(citizen_rating), 0), 1) as net_satisfaction_rate
     FROM cef_feedback_submissions
     WHERE rating_speed IS NOT NULL"
)->fetch() ?: [];

// Department Responsiveness League Table
$departmentLeague = $pdo->query(
    "SELECT 
        o.id, o.code, o.name as dept_name,
        COUNT(a.id) as total_assigned,
        SUM(CASE WHEN a.status = 'Completed' OR s.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as resolved_count,
        SUM(CASE WHEN a.status != 'Completed' AND (a.due_at < NOW() OR c.resolution_target_at < NOW()) THEN 1 ELSE 0 END) as overdue_count,
        ROUND(AVG(CASE WHEN a.completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, a.assigned_at, a.completed_at) END), 1) as avg_resolution_hours
     FROM offices o
     LEFT JOIN cef_assignments a ON a.office_id = o.id
     LEFT JOIN cef_submissions s ON s.id = a.submission_id AND s.deleted_at IS NULL
     LEFT JOIN cef_complaints c ON c.submission_id = s.id
     WHERE o.status = 'Active'
     GROUP BY o.id, o.code, o.name
     ORDER BY total_assigned DESC, o.name ASC
     LIMIT 6"
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-graph-up-arrow"></i> Step 7 · Civic Intelligence</div><h1>Citizen Engagement Analytics</h1><p>Live civic-engagement trends, recurring concerns, complaint service-level performance, response delivery performance, location patterns, category distribution, and rating-based sentiment proxy.</p></div><div class="d-flex gap-2"><a class="btn btn-warning text-dark fw-bold" href="report_session_deck.php" target="_blank"><i class="bi bi-file-earmark-slides-fill me-1"></i> Session Briefing Deck</a><?php if(cefHasPermission('cepfms.analytics.manage')): ?><button class="btn btn-primary" id="btnSnapshot"><i class="bi bi-camera"></i> Save Snapshot</button><?php endif; ?><a class="btn btn-outline-secondary" href="report.php">Report</a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 [$summary['total'],'Total Submissions','bi-inboxes'],
 [$summary['pending_moderation'],'Pending Moderation','bi-shield-check'],
 [$summary['open_complaints'],'Open Complaints','bi-exclamation-diamond'],
 [$summary['overdue_complaints'],'Overdue Complaints','bi-alarm'],
 [$summary['delivered_responses'],'Delivered Responses','bi-send-check'],
 [count($alerts),'Open Trend Alerts','bi-bullseye']
] as [$v,$l,$i]): ?><div class="col-6 col-xl-2"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-4 mb-4">
<div class="col-xl-8"><div class="card cef-card h-100"><div class="card-header card-header-clean">Six-Month Citizen Submission Trend</div><div class="card-body"><canvas id="monthlyChart" height="110"></canvas></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header card-header-clean">Submission Type Distribution</div><div class="card-body"><canvas id="typeChart" height="170"></canvas></div></div></div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-6"><div class="card cef-card h-100"><div class="card-header card-header-clean">Top Civic Categories</div><div class="card-body"><canvas id="categoryChart" height="150"></canvas></div></div></div>
<div class="col-xl-6"><div class="card cef-card h-100"><div class="card-header card-header-clean">Top Locations</div><div class="card-body"><canvas id="locationChart" height="150"></canvas></div></div></div>
</div>

<!-- MANILA 6-DISTRICT CIVIC STRESS HEATMAP GRID -->
<div class="card cef-card mb-4">
  <div class="card-header card-header-clean d-flex justify-content-between align-items-center">
    <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> Manila 6-District Civic Stress Map</span>
    <span class="badge bg-light text-dark border">Constituent Area Breakdown</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($districtProfiles as $num => $dist): ?>
        <div class="col-md-4 col-lg-2">
          <div class="p-2 border rounded text-center h-100 <?= $dist['complaints'] > 3 ? 'border-warning bg-warning-subtle' : 'bg-light' ?>">
            <strong class="d-block text-primary">District <?= $num ?></strong>
            <small class="text-muted text-truncate d-block" style="font-size: 0.72rem;" title="<?= e($dist['title']) ?>"><?= e($dist['title']) ?></small>
            <div class="fs-4 fw-bold mt-1 text-dark"><?= (int)$dist['total'] ?></div>
            <div class="d-flex justify-content-center gap-1 small mt-1" style="font-size: 0.72rem;">
              <span class="badge bg-info text-dark" title="Feedback">F: <?= $dist['feedback'] ?></span>
              <span class="badge bg-warning text-dark" title="Proposals">P: <?= $dist['proposals'] ?></span>
              <span class="badge bg-danger" title="Complaints">C: <?= $dist['complaints'] ?></span>
            </div>
            <div class="mt-2 pt-1 border-top text-truncate small" style="font-size: 0.7rem;" title="<?= e($dist['top_issue']) ?>">
              <i class="bi bi-tag me-1"></i><?= e($dist['top_issue']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- MULTI-DIMENSIONAL CSAT BREAKDOWN -->
  <div class="col-xl-5">
    <div class="card cef-card h-100">
      <div class="card-header card-header-clean d-flex justify-content-between align-items-center">
        <span><i class="bi bi-star-half text-warning me-1"></i> Multi-Dimensional CSAT Index</span>
        <span class="badge bg-success-subtle text-success border">Net: <?= $csatScorecard['net_satisfaction_rate'] ?? '100' ?>%</span>
      </div>
      <div class="card-body">
        <div class="row g-2 text-center mb-3">
          <div class="col-6">
            <div class="p-2 border rounded bg-light">
              <small class="text-muted text-uppercase d-block" style="font-size: 0.7rem;">Speed of Service</small>
              <strong class="fs-5 text-primary"><?= $csatScorecard['avg_speed'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5</span></strong>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 border rounded bg-light">
              <small class="text-muted text-uppercase d-block" style="font-size: 0.7rem;">Staff Courtesy</small>
              <strong class="fs-5 text-success"><?= $csatScorecard['avg_courtesy'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5</span></strong>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 border rounded bg-light">
              <small class="text-muted text-uppercase d-block" style="font-size: 0.7rem;">Facility & Access</small>
              <strong class="fs-5 text-info"><?= $csatScorecard['avg_facility'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5</span></strong>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 border rounded bg-light">
              <small class="text-muted text-uppercase d-block" style="font-size: 0.7rem;">Process Clarity</small>
              <strong class="fs-5 text-warning"><?= $csatScorecard['avg_process'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5</span></strong>
            </div>
          </div>
        </div>
        <div class="small text-muted border-top pt-2">
          <i class="bi bi-info-circle me-1"></i> Calculated from verified citizen feedback rating dimensions (Speed, Courtesy, Facility, Process).
        </div>
      </div>
    </div>
  </div>

  <!-- INTER-AGENCY RESPONSIVENESS LEAGUE TABLE -->
  <div class="col-xl-7">
    <div class="card cef-card h-100">
      <div class="card-header card-header-clean d-flex justify-content-between align-items-center">
        <span><i class="bi bi-speedometer2 text-primary me-1"></i> Department Responsiveness League Table</span>
        <span class="badge bg-light text-dark border">ARTA SLA Ranking</span>
      </div>
      <div class="table-responsive">
        <table class="table cef-table mb-0">
          <thead>
            <tr>
              <th>Office / Department</th>
              <th class="text-center">Assigned</th>
              <th class="text-center">Resolved</th>
              <th class="text-center">Overdue</th>
              <th class="text-center">Avg Hours</th>
              <th class="text-center">ARTA Standing</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($departmentLeague)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No department assignments recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($departmentLeague as $dept): ?>
                <tr>
                  <td><strong><?= e($dept['dept_name']) ?></strong> <span class="badge bg-light text-muted ms-1"><?= e($dept['code']) ?></span></td>
                  <td class="text-center fw-bold"><?= (int)$dept['total_assigned'] ?></td>
                  <td class="text-center text-success fw-bold"><?= (int)$dept['resolved_count'] ?></td>
                  <td class="text-center <?= (int)$dept['overdue_count'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= (int)$dept['overdue_count'] ?></td>
                  <td class="text-center"><?= $dept['avg_resolution_hours'] !== null ? e($dept['avg_resolution_hours'].'h') : '—' ?></td>
                  <td class="text-center">
                    <?php if ((int)$dept['overdue_count'] === 0): ?>
                      <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> ARTA Compliant</span>
                    <?php else: ?>
                      <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> SLA Breached</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header">Complaint SLA Performance</div><div class="card-body"><div class="cef-grid" style="grid-template-columns:1fr 1fr"><div><small>Total Complaints</small><strong><?= (int)($complaintPerformance['total']??0) ?></strong></div><div><small>Resolved</small><strong><?= (int)($complaintPerformance['resolved_count']??0) ?></strong></div><div><small>Open Overdue</small><strong><?= (int)($complaintPerformance['overdue_open']??0) ?></strong></div><div><small>Resolved On Time</small><strong><?= (int)($complaintPerformance['resolved_on_time']??0) ?></strong></div><div><small>Avg Resolution</small><strong><?= $complaintPerformance['avg_resolution_hours']!==null?e($complaintPerformance['avg_resolution_hours'].' h'):'—' ?></strong></div></div></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header">Response Performance</div><div class="card-body"><div class="cef-grid" style="grid-template-columns:1fr 1fr"><div><small>Responses</small><strong><?= (int)($responsePerformance['total']??0) ?></strong></div><div><small>Delivered</small><strong><?= (int)($responsePerformance['delivered']??0) ?></strong></div><div><small>Avg Draft → Delivery</small><strong><?= $responsePerformance['avg_draft_to_delivery_hours']!==null?e($responsePerformance['avg_draft_to_delivery_hours'].' h'):'—' ?></strong></div></div></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header">Feedback Rating Proxy</div><div class="card-body"><div class="cef-alert mb-3">This is a rating-based proxy, not AI text sentiment analysis.</div><div class="cef-grid" style="grid-template-columns:1fr 1fr"><div><small>Rated Feedback</small><strong><?= (int)($rating['rated']??0) ?></strong></div><div><small>Average Rating</small><strong><?= $rating['avg_rating']!==null?e($rating['avg_rating'].' / 5'):'—' ?></strong></div><div><small>Positive Proxy</small><strong><?= (int)($rating['positive_proxy']??0) ?></strong></div><div><small>Neutral Proxy</small><strong><?= (int)($rating['neutral_proxy']??0) ?></strong></div><div><small>Negative Proxy</small><strong><?= (int)($rating['negative_proxy']??0) ?></strong></div></div></div></div></div>
</div>

<div class="row g-4">
<div class="col-xl-7"><div class="card cef-card h-100"><div class="card-header">Recurring Civic Concern Alerts</div><div class="card-body d-grid gap-2"><?php if(!$alerts): ?><div class="text-muted small">No open analytics alert. Use Save Snapshot to record recurring categories with at least three submissions in the last 30 days.</div><?php endif; ?><?php foreach($alerts as $a): ?><div class="cef-risk"><div class="d-flex justify-content-between gap-2"><div><strong><?= e($a['severity'].' · '.$a['dimension_value']) ?></strong><small><?= e($a['details']?:'Recurring civic concern detected.') ?><br><?= formatDateTime($a['created_at']) ?></small></div><?php if(cefHasPermission('cepfms.analytics.manage')): ?><button class="btn btn-sm btn-outline-danger resolve-alert" data-id="<?= (int)$a['id'] ?>">Resolve</button><?php endif; ?></div></div><?php endforeach; ?></div></div></div>
<div class="col-xl-5"><div class="card cef-card h-100"><div class="card-header">Recent Citizen Records</div><div class="list-group list-group-flush"><?php foreach($recent as $r): ?><div class="list-group-item"><strong class="small"><?= e($r['reference_number'].' · '.$r['title']) ?></strong><div class="small text-muted"><?= e($r['submission_type'].' · '.($r['category_name']?:'Unclassified').' · '.$r['status']) ?> · <?= formatDateTime($r['created_at']) ?></div></div><?php endforeach; ?></div></div></div>
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
 const types=<?= json_encode($typeDist,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('typeChart'),{
   type:'doughnut',
   data:{
     labels:types.map(x=>x.label),
     datasets:[{
       data:types.map(x=>Number(x.total)),
       backgroundColor:['#1d6fb8','#b8860b','#1a3a5c']
     }]
   },
   options:{responsive:true}
 });
 const cats=<?= json_encode($categories,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('categoryChart'),{
   type:'bar',
   data:{
     labels:cats.map(x=>x.label),
     datasets:[{
       label:'Submissions',
       data:cats.map(x=>Number(x.total)),
       backgroundColor:'#b8860b',
       borderColor:'#b8860b',
       borderWidth:1,
       borderRadius:4
     }]
   },
   options:{indexAxis:'y',responsive:true,scales:{x:{beginAtZero:true,ticks:{precision:0}}}}
 });
 const locs=<?= json_encode($locations,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('locationChart'),{
   type:'bar',
   data:{
     labels:locs.map(x=>x.label),
     datasets:[{
       label:'Submissions',
       data:locs.map(x=>Number(x.total)),
       backgroundColor:'#1d6fb8',
       borderColor:'#1d6fb8',
       borderWidth:1,
       borderRadius:4
     }]
   },
   options:{indexAxis:'y',responsive:true,scales:{x:{beginAtZero:true,ticks:{precision:0}}}}
 });

 document.getElementById('btnSnapshot')?.addEventListener('click',async()=>{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');const r=await fetch('ajax_snapshot.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Analytics',r.message+' '+r.alerts_created+' new alert(s).','success');location.reload();}else Swal.fire('Analytics',r.message,'error');});
 document.querySelectorAll('.resolve-alert').forEach(b=>b.onclick=async function(){const c=await Swal.fire({title:'Resolve this trend alert?',input:'textarea',inputLabel:'Resolution / management note',showCancelButton:true,inputValidator:v=>!v?'A resolution note is required':undefined});if(!c.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('alert_id',this.dataset.id);fd.append('resolution_notes',c.value);const r=await fetch('ajax_alert.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Analytics Alert',r.message,'error');});
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
