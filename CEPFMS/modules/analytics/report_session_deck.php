<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.view');

$pdo = db();
$pageTitle = 'Sanggunian Session Civic Briefing Deck';
$activeMenu = 'analytics';

// 1. Core Summary Metrics
$summary = cefAnalyticsSummary($pdo);

// 2. Multi-Dimensional CSAT
$csat = $pdo->query(
    "SELECT 
        COUNT(*) as total_rated,
        ROUND(AVG(citizen_rating), 1) as avg_overall,
        ROUND(AVG(rating_speed), 1) as avg_speed,
        ROUND(AVG(rating_courtesy), 1) as avg_courtesy,
        ROUND(AVG(rating_facility), 1) as avg_facility,
        ROUND(AVG(rating_process), 1) as avg_process,
        ROUND(SUM(citizen_rating >= 4) * 100.0 / NULLIF(COUNT(citizen_rating), 0), 1) as net_satisfaction
     FROM cef_feedback_submissions
     WHERE rating_speed IS NOT NULL"
)->fetch() ?: [];

// 3. Top Community Endorsed Proposals (Ready for Legislative Sponsorship)
$topProposals = $pdo->query(
    "SELECT 
        s.id, s.reference_number, s.title, s.summary, s.district, s.barangay, s.created_at,
        p.proposal_type, p.target_beneficiaries, p.endorsement_count, p.impact_rating,
        p.budget_impact, p.legal_mandate_status, p.converted_legislative_item_id,
        u.full_name as submitter_name
     FROM cef_submissions s
     JOIN cef_proposals p ON p.submission_id = s.id
     LEFT JOIN users u ON u.id = s.submitted_by
     WHERE s.deleted_at IS NULL
     ORDER BY p.endorsement_count DESC, p.impact_rating DESC, s.created_at DESC
     LIMIT 5"
)->fetchAll();

// 4. District Civic Stress Profile (Districts 1 - 6)
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
    "SELECT s.district, s.submission_type, s.status, COALESCE(c.name, 'General Civic Concern') as category_name, COUNT(*) as cnt
     FROM cef_submissions s
     LEFT JOIN cef_categories c ON c.id = s.category_id
     WHERE s.deleted_at IS NULL
     GROUP BY s.district, s.submission_type, s.status, c.name"
)->fetchAll();

foreach ($rawDistRows as $dr) {
    $num = (int)preg_replace('/[^0-9]/', '', (string)$dr['district']);
    if ($num < 1 || $num > 6) {
        $num = 3; // fallback to District 3 default
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

// 5. Inter-Agency / Department Responsiveness League Table
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
     LIMIT 8"
)->fetchAll();

// 6. Active Hotspot Alerts (In-Aid-of-Legislation Attention)
$alerts = $pdo->query(
    "SELECT a.*, u.full_name created_name
     FROM cef_analytics_alerts a
     LEFT JOIN users u ON u.id = a.created_by
     WHERE a.status = 'Open'
     ORDER BY FIELD(a.severity, 'High', 'Attention', 'Monitor'), a.created_at DESC
     LIMIT 6"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> · City of Manila</title>
  <link rel="stylesheet" href="<?= e(appUrl('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --lgu-navy: #1a3a5c;
      --lgu-gold: #c69214;
      --lgu-accent: #0f233a;
      --lgu-bg: #f8fafc;
    }
    body {
      background-color: var(--lgu-bg);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #1e293b;
    }
    .deck-container {
      max-width: 1140px;
      margin: 2rem auto;
      padding: 0 1rem;
    }
    .deck-paper {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.06);
      padding: 2.5rem 3rem;
      margin-bottom: 2rem;
    }
    .deck-header {
      border-bottom: 3px double #cbd5e1;
      padding-bottom: 1.5rem;
      margin-bottom: 2rem;
    }
    .deck-badge {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 0.35rem 0.65rem;
      border-radius: 4px;
    }
    .kpi-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-left: 4px solid var(--lgu-navy);
      border-radius: 8px;
      padding: 1rem 1.25rem;
    }
    .kpi-card.gold {
      border-left-color: var(--lgu-gold);
    }
    .kpi-card.success {
      border-left-color: #16a34a;
    }
    .district-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 1rem;
      transition: all 0.2s ease;
    }
    .district-card:hover {
      border-color: var(--lgu-navy);
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .table-briefing th {
      background-color: #f1f5f9;
      color: #334155;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .table-briefing td {
      font-size: 0.88rem;
      vertical-align: middle;
    }
    @media print {
      body {
        background: #ffffff;
        color: #000000;
      }
      .no-print {
        display: none !important;
      }
      .deck-paper {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
      }
      .deck-container {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
      }
    }
  </style>
</head>
<body>

<div class="deck-container">
  <!-- Action Toolbar -->
  <div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back to Analytics Dashboard
    </a>
    <div class="d-flex gap-2">
      <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF Deck
      </button>
    </div>
  </div>

  <div class="deck-paper">
    <!-- Header with Seal & Title -->
    <div class="deck-header text-center">
      <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
        <i class="bi bi-bank2 fs-1 text-secondary"></i>
        <div>
          <div class="text-uppercase fw-bold text-secondary" style="font-size: 0.8rem; letter-spacing: 0.15em;">Republic of the Philippines · City of Manila</div>
          <h2 class="fw-bold mb-0" style="color: var(--lgu-navy); font-family: Georgia, serif;">SANGGUNIANG PANLUNGSOD NG MAYNILA</h2>
          <div class="text-muted small fw-semibold">Citizen Engagement & Public Feedback Monitoring System (CEPFMS)</div>
        </div>
        <i class="bi bi-journal-text fs-1 text-secondary"></i>
      </div>
      <div class="badge bg-warning text-dark px-3 py-1 mt-2 text-uppercase fw-bold" style="letter-spacing: 0.06em;">
        Executive Legislative Session Briefing Deck · <?= date('F d, Y') ?>
      </div>
    </div>

    <!-- Executive KPI Row -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="kpi-card">
          <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Total Civic Voice Volume</small>
          <div class="fs-3 fw-bold text-dark"><?= (int)$summary['total'] ?></div>
          <div class="small text-secondary"><?= (int)$summary['feedback'] ?> Feedback · <?= (int)$summary['proposals'] ?> Proposals</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="kpi-card gold">
          <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Complaints Under Action</small>
          <div class="fs-3 fw-bold text-dark"><?= (int)$summary['open_complaints'] ?></div>
          <div class="small text-danger fw-semibold"><?= (int)$summary['overdue_complaints'] ?> Overdue ARTA SLA</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="kpi-card success">
          <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Official Responses Delivered</small>
          <div class="fs-3 fw-bold text-dark"><?= (int)$summary['delivered_responses'] ?></div>
          <div class="small text-success fw-semibold">Transparency Rate: <?= $summary['total'] > 0 ? round(($summary['delivered_responses'] / $summary['total']) * 100, 1) : 0 ?>%</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="kpi-card">
          <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Net Citizen Satisfaction</small>
          <div class="fs-3 fw-bold text-dark"><?= $csat['avg_overall'] !== null ? e($csat['avg_overall'].' / 5.0') : '5.0 / 5.0' ?></div>
          <div class="small text-primary fw-semibold">Net CSAT: <?= $csat['net_satisfaction'] !== null ? e($csat['net_satisfaction'].'%') : '100%' ?> Positive</div>
        </div>
      </div>
    </div>

    <!-- Section 1: Multi-Dimensional CSAT Quality Radar -->
    <div class="mb-4">
      <h5 class="fw-bold pb-2 border-bottom" style="color: var(--lgu-navy);">
        <i class="bi bi-star-half me-1"></i> I. Multi-Dimensional Citizen Satisfaction Index (CSI)
      </h5>
      <div class="row g-3 text-center pt-2">
        <div class="col-6 col-md-3">
          <div class="p-3 bg-light rounded border">
            <div class="text-muted small fw-bold text-uppercase">Speed of Service</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= $csat['avg_speed'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5.0</span></div>
            <small class="text-muted">Resolution Turnaround</small>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="p-3 bg-light rounded border">
            <div class="text-muted small fw-bold text-uppercase">Staff Courtesy</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= $csat['avg_courtesy'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5.0</span></div>
            <small class="text-muted">Public Assistance Dignity</small>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="p-3 bg-light rounded border">
            <div class="text-muted small fw-bold text-uppercase">Facility & Access</div>
            <div class="fs-4 fw-bold text-info mt-1"><?= $csat['avg_facility'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5.0</span></div>
            <small class="text-muted">Portal & Barangay Touchpoints</small>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="p-3 bg-light rounded border">
            <div class="text-muted small fw-bold text-uppercase">Process Clarity</div>
            <div class="fs-4 fw-bold text-warning mt-1"><?= $csat['avg_process'] ?? '5.0' ?> <span class="fs-6 text-muted">/ 5.0</span></div>
            <small class="text-muted">Ease of Step Compliance</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Section 2: Legislative Action Pipeline (Citizen Proposals for Council Sponsorship) -->
    <div class="mb-4">
      <h5 class="fw-bold pb-2 border-bottom" style="color: var(--lgu-navy);">
        <i class="bi bi-file-earmark-ruled me-1"></i> II. Priority Citizen Proposals for Council Sponsorship
      </h5>
      <p class="text-muted small mb-3">These civic proposals have received community endorsements and completed feasibility scoring, making them prime candidates for Councilor sponsorship into draft ordinances.</p>
      
      <div class="table-responsive">
        <table class="table table-bordered table-briefing mb-0">
          <thead>
            <tr>
              <th>Ref & Title</th>
              <th>Proponent & District</th>
              <th class="text-center">Endorsements</th>
              <th>Budget Impact</th>
              <th>Mandate Status</th>
              <th class="text-center">Draft Ordinance</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topProposals)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No civic proposals currently in legislative pipeline.</td></tr>
            <?php else: ?>
              <?php foreach ($topProposals as $p): ?>
                <tr>
                  <td>
                    <strong class="text-primary"><?= e($p['reference_number']) ?></strong> · <?= e($p['title']) ?>
                    <div class="text-muted small"><?= e(mb_strimwidth((string)$p['summary'], 0, 80, '...')) ?></div>
                  </td>
                  <td>
                    <?= e($p['submitter_name'] ?: 'Verified Citizen') ?><br>
                    <small class="text-muted"><?= e($p['district'] ?: 'Citywide') ?> · <?= e($p['barangay'] ?: 'Manila') ?></small>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-primary fs-6 px-2 py-1"><i class="bi bi-hand-thumbs-up-fill me-1"></i><?= (int)$p['endorsement_count'] ?></span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= e($p['budget_impact'] ?: 'Low Cost') ?></span>
                  </td>
                  <td>
                    <span class="badge <?= ($p['legal_mandate_status'] ?? 'Compliant') === 'Compliant' ? 'bg-success' : 'bg-warning text-dark' ?>">
                      <?= e($p['legal_mandate_status'] ?: 'Compliant') ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <?php if (!empty($p['converted_legislative_item_id'])): ?>
                      <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Bill #<?= (int)$p['converted_legislative_item_id'] ?></span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Ready for Bill</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Section 3: Manila 6-District Civic Stress Profile -->
    <div class="mb-4">
      <h5 class="fw-bold pb-2 border-bottom" style="color: var(--lgu-navy);">
        <i class="bi bi-geo-alt-fill me-1"></i> III. Manila Legislative District Civic Stress Profile
      </h5>
      <div class="row g-3 pt-2">
        <?php foreach ($districtProfiles as $num => $dist): ?>
          <div class="col-md-4">
            <div class="district-card">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong style="color: var(--lgu-navy); font-size: 0.95rem;">District <?= $num ?></strong>
                <span class="badge bg-light text-dark border"><?= (int)$dist['total'] ?> Submissions</span>
              </div>
              <div class="text-muted small mb-2" style="font-size: 0.78rem; line-height: 1.2;"><?= e($dist['title']) ?></div>
              <div class="d-flex justify-content-between text-secondary small pt-1 border-top">
                <span>Feedback: <strong><?= $dist['feedback'] ?></strong></span>
                <span>Proposals: <strong><?= $dist['proposals'] ?></strong></span>
                <span>Complaints: <strong><?= $dist['complaints'] ?></strong></span>
              </div>
              <div class="mt-2 text-truncate small" style="font-size: 0.76rem;">
                <span class="text-muted">Top Concern:</span> <strong class="text-dark"><?= e($dist['top_issue']) ?></strong>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Section 4: Executive Department Responsiveness League Table -->
    <div class="mb-4">
      <h5 class="fw-bold pb-2 border-bottom" style="color: var(--lgu-navy);">
        <i class="bi bi-speedometer2 me-1"></i> IV. Executive Department ARTA Compliance & Responsiveness
      </h5>
      <div class="table-responsive">
        <table class="table table-bordered table-briefing mb-0">
          <thead>
            <tr>
              <th>Office / Department</th>
              <th class="text-center">Assigned Matters</th>
              <th class="text-center">Resolved</th>
              <th class="text-center">Overdue Backlog</th>
              <th class="text-center">Avg Turnaround</th>
              <th class="text-center">ARTA Standing</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($departmentLeague)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No assigned department records found.</td></tr>
            <?php else: ?>
              <?php foreach ($departmentLeague as $dept): ?>
                <tr>
                  <td>
                    <strong><?= e($dept['dept_name']) ?></strong>
                    <span class="badge bg-light text-secondary ms-1"><?= e($dept['code']) ?></span>
                  </td>
                  <td class="text-center fw-bold"><?= (int)$dept['total_assigned'] ?></td>
                  <td class="text-center text-success fw-bold"><?= (int)$dept['resolved_count'] ?></td>
                  <td class="text-center <?= (int)$dept['overdue_count'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                    <?= (int)$dept['overdue_count'] ?>
                  </td>
                  <td class="text-center"><?= $dept['avg_resolution_hours'] !== null ? e($dept['avg_resolution_hours'].' hrs') : '—' ?></td>
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

    <!-- Section 5: Recurring Civic Trend Alerts (For Committee Hearings) -->
    <?php if (!empty($alerts)): ?>
    <div class="mb-4">
      <h5 class="fw-bold pb-2 border-bottom" style="color: var(--lgu-navy);">
        <i class="bi bi-megaphone-fill me-1"></i> V. Hotspots Requiring Committee Inquiry (In-Aid-of-Legislation)
      </h5>
      <div class="row g-2 pt-2">
        <?php foreach ($alerts as $a): ?>
          <div class="col-md-6">
            <div class="p-2 border rounded bg-light">
              <div class="d-flex justify-content-between align-items-center">
                <span class="badge <?= $a['severity'] === 'High' ? 'bg-danger' : 'bg-warning text-dark' ?>">
                  <?= e($a['severity']) ?> Priority
                </span>
                <small class="text-muted"><?= formatDateTime($a['created_at']) ?></small>
              </div>
              <strong class="d-block mt-1"><?= e($a['dimension_value']) ?></strong>
              <div class="small text-muted"><?= e($a['details'] ?: 'Civic threshold breached (>3 occurrences in 30 days).') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Signature / Attestation Block for Council Record -->
    <div class="mt-5 pt-4 border-top">
      <div class="row text-center">
        <div class="col-4">
          <div class="text-muted small mb-4">Prepared by:</div>
          <div class="fw-bold border-top pt-1 text-dark"><?= e(currentUser()['full_name'] ?? 'CEPFMS Secretariat') ?></div>
          <div class="small text-muted">Legislative Information Officer</div>
        </div>
        <div class="col-4">
          <div class="text-muted small mb-4">Attested by:</div>
          <div class="fw-bold border-top pt-1 text-dark">Atty. Luch R. Gempis, Jr.</div>
          <div class="small text-muted">Secretary to the Sangguniang Panlungsod</div>
        </div>
        <div class="col-4">
          <div class="text-muted small mb-4">Noted by:</div>
          <div class="fw-bold border-top pt-1 text-dark">Hon. John Marvin "Yul Servo" Nieto</div>
          <div class="small text-muted">City Vice Mayor & Presiding Officer</div>
        </div>
      </div>
    </div>

  </div>
</div>

</body>
</html>
