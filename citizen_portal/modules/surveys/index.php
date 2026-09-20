<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/public_data.php';
requireLogin();

$pdo = db();
$userId = currentUserId();
$pageTitle = 'Consultation Surveys';
$activeMenu = 'surveys';
$extraCss = [appUrl('assets/css/public-modules.css')];

$now = time();

// Fetch surveys with question counts and user submission status
$stmt = $pdo->prepare(
    'SELECT s.*,
            h.reference_number hearing_reference,
            h.title hearing_title,
            li.reference_number legislative_reference,
            li.title legislative_title,
            (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) AS question_count,
            (SELECT COUNT(*) FROM survey_submissions ss WHERE ss.survey_id = s.id AND ss.user_id = :user) AS has_answered,
            (SELECT MAX(submitted_at) FROM survey_submissions ss WHERE ss.survey_id = s.id AND ss.user_id = :user2) AS user_submitted_at
     FROM surveys s
     LEFT JOIN hearings h ON h.id = s.hearing_id
     LEFT JOIN legislative_items li ON li.id = s.legislative_item_id
     WHERE s.status IN ("Active", "Closed")
     ORDER BY s.created_at DESC, s.id DESC'
);
$stmt->execute([':user' => $userId, ':user2' => $userId]);
$surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize surveys
$activeCount = 0;
$answeredCount = 0;
foreach ($surveys as &$s) {
    $opensAt = !empty($s['opens_at']) ? strtotime($s['opens_at']) : null;
    $closesAt = !empty($s['closes_at']) ? strtotime($s['closes_at']) : null;

    if ($s['status'] === 'Closed') {
        $s['window_status'] = 'Closed';
    } elseif ($opensAt && $now < $opensAt) {
        $s['window_status'] = 'Scheduled';
    } elseif ($closesAt && $now > $closesAt) {
        $s['window_status'] = 'Closed';
    } else {
        $s['window_status'] = 'Open';
        $activeCount++;
    }

    if ((int)$s['has_answered'] > 0) {
        $answeredCount++;
    }
}
unset($s);

include __DIR__ . '/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/../../layouts/sidebar.php'; ?>
<main class="main-content">

  <!-- Header -->
  <div class="page-head">
    <div>
      <div class="dashboard-eyebrow">
        <i class="bi bi-bank2"></i> Citizen Participation &bull; Civic Consultations
      </div>
      <h1>Consultation Surveys</h1>
      <p>Share your perspective on proposed municipal ordinances, committee public hearings, and local governance policies.</p>
    </div>
  </div>

  <!-- KPI Filter Summary Strip -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="portal-stat">
        <i class="bi bi-ui-checks text-primary"></i>
        <div>
          <strong><?= count($surveys) ?></strong>
          <span>Total Surveys</span>
          <small>Public consultations</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="portal-stat">
        <i class="bi bi-broadcast text-success"></i>
        <div>
          <strong class="text-success"><?= $activeCount ?></strong>
          <span>Open Now</span>
          <small>Accepting responses</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="portal-stat">
        <i class="bi bi-check2-circle text-info"></i>
        <div>
          <strong style="color: #0d9488;"><?= $answeredCount ?></strong>
          <span>My Submissions</span>
          <small>Surveys participated</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="portal-stat">
        <i class="bi bi-shield-check text-warning"></i>
        <div>
          <strong style="color: #d97706;">Official</strong>
          <span>City Council</span>
          <small>Verified civic channel</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Surveys Catalog Card -->
  <div class="card portal-card mb-4" style="border-top: 3.5px solid #a97900 !important;">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-card-checklist text-primary fs-5"></i>
        <span class="fw-bold">Active &amp; Scheduled Consultation Surveys</span>
      </div>

      <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Filter Pills -->
        <div class="btn-group btn-group-sm" id="surveyFilterGroup">
          <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
          <button type="button" class="btn btn-outline-secondary" data-filter="open">Open Now</button>
          <button type="button" class="btn btn-outline-secondary" data-filter="answered">My Answered</button>
          <button type="button" class="btn btn-outline-secondary" data-filter="scheduled">Scheduled</button>
          <button type="button" class="btn btn-outline-secondary" data-filter="closed">Closed</button>
        </div>

        <!-- Search Input -->
        <div class="input-group input-group-sm" style="width: 200px;">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" class="form-control border-start-0 ps-0" id="surveySearch" placeholder="Search surveys...">
        </div>
      </div>
    </div>

    <div class="card-body p-3">
      <?php if (empty($surveys)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
          <strong>No consultation surveys available at this moment.</strong>
          <p class="small text-muted mb-0">Surveys will appear here whenever city council committees conduct public polling.</p>
        </div>
      <?php else: ?>
        <div class="row g-3" id="surveysList">
          <?php foreach ($surveys as $s): ?>
            <?php
              $sid = (int)$s['id'];
              $hasAnswered = (int)$s['has_answered'] > 0;
              $wStatus = $s['window_status'];
              $searchString = strtolower(
                  $s['title'] . ' ' .
                  ($s['description'] ?? '') . ' ' .
                  ($s['hearing_reference'] ?? '') . ' ' .
                  ($s['hearing_title'] ?? '') . ' ' .
                  ($s['legislative_reference'] ?? '')
              );
            ?>
            <div class="col-md-6 col-xl-4 survey-item-col"
                 data-status="<?= strtolower($wStatus) ?>"
                 data-answered="<?= $hasAnswered ? 'answered' : 'not-answered' ?>"
                 data-search="<?= e($searchString) ?>">

              <div class="card h-100 border rounded-3 p-3 shadow-sm d-flex flex-column justify-content-between" style="transition: transform 0.15s ease, box-shadow 0.15s ease; background: #ffffff;">
                <div>
                  <!-- Status & Context Top Row -->
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <?php if (!empty($s['hearing_reference'])): ?>
                      <span class="badge" style="background: rgba(169, 121, 0, 0.1); color: #8A6400; border: 1px solid rgba(169, 121, 0, 0.25); font-size: 0.7rem;">
                        <i class="bi bi-calendar-event me-1"></i><?= e($s['hearing_reference']) ?>
                      </span>
                    <?php elseif (!empty($s['legislative_reference'])): ?>
                      <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                        <i class="bi bi-file-earmark-text me-1"></i><?= e($s['legislative_reference']) ?>
                      </span>
                    <?php else: ?>
                      <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">
                        Civic Consultation
                      </span>
                    <?php endif; ?>

                    <?php if ($hasAnswered): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">
                        <i class="bi bi-check-circle-fill me-1"></i>Answered ✓
                      </span>
                    <?php elseif ($wStatus === 'Open'): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">
                        <i class="bi bi-broadcast me-1"></i>Open Now
                      </span>
                    <?php elseif ($wStatus === 'Scheduled'): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.7rem;">
                        <i class="bi bi-clock-history me-1"></i>Scheduled
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 0.7rem;">
                        Closed
                      </span>
                    <?php endif; ?>
                  </div>

                  <!-- Title & Description -->
                  <h6 class="fw-bold text-dark mb-1" style="font-size: 0.98rem; line-height: 1.35;">
                    <?= e($s['title']) ?>
                  </h6>

                  <?php if (!empty($s['description'])): ?>
                    <p class="text-secondary small mb-3" style="line-height: 1.4;">
                      <?= e(mb_strimwidth($s['description'], 0, 95, '...')) ?>
                    </p>
                  <?php else: ?>
                    <p class="text-muted small fst-italic mb-3">No additional description provided.</p>
                  <?php endif; ?>
                </div>

                <!-- Footer / Metadata & Action -->
                <div>
                  <div class="border-top pt-2 mb-3 small text-muted">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span><i class="bi bi-patch-question me-1 text-primary"></i>Questions:</span>
                      <strong><?= (int)$s['question_count'] ?></strong>
                    </div>
                    <?php if (!empty($s['closes_at'])): ?>
                      <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock me-1 text-danger"></i>Deadline:</span>
                        <span class="text-danger fw-semibold"><?= formatDate($s['closes_at']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if ($hasAnswered && !empty($s['user_submitted_at'])): ?>
                      <div class="d-flex justify-content-between align-items-center text-success mt-1" style="font-size: 0.72rem;">
                        <span><i class="bi bi-calendar-check me-1"></i>Submitted on:</span>
                        <span><?= date('M d, Y', strtotime($s['user_submitted_at'])) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="d-grid">
                    <?php if ($hasAnswered): ?>
                      <a href="take.php?id=<?= $sid ?>" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-eye me-1"></i> View My Response
                      </a>
                    <?php elseif ($wStatus === 'Open'): ?>
                      <a href="take.php?id=<?= $sid ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil-square me-1"></i> Answer Survey
                      </a>
                    <?php elseif ($wStatus === 'Scheduled'): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-clock me-1"></i> Opens <?= !empty($s['opens_at']) ? date('M d, Y', strtotime($s['opens_at'])) : 'Soon' ?>
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-lock me-1"></i> Consultation Closed
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('surveySearch');
  const filterButtons = document.querySelectorAll('#surveyFilterGroup button');
  let currentFilter = 'all';

  function filterSurveys() {
    const query = (searchInput.value || '').toLowerCase().trim();
    const items = document.querySelectorAll('.survey-item-col');

    items.forEach(item => {
      const status = item.getAttribute('data-status');
      const answered = item.getAttribute('data-answered');
      const search = item.getAttribute('data-search');

      let matchesFilter = true;
      if (currentFilter === 'open') matchesFilter = (status === 'open');
      else if (currentFilter === 'scheduled') matchesFilter = (status === 'scheduled');
      else if (currentFilter === 'closed') matchesFilter = (status === 'closed');
      else if (currentFilter === 'answered') matchesFilter = (answered === 'answered');

      const matchesSearch = !query || search.includes(query);

      if (matchesFilter && matchesSearch) {
        item.style.display = '';
      } else {
        item.style.display = 'none';
      }
    });
  }

  if (searchInput) searchInput.addEventListener('input', filterSurveys);

  filterButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      filterButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentFilter = this.getAttribute('data-filter') || 'all';
      filterSurveys();
    });
  });
});
</script>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
