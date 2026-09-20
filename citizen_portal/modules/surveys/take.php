<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/public_data.php';
requireLogin();

$pdo = db();
$userId = currentUserId();
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*,
            h.reference_number hearing_reference,
            h.title hearing_title,
            h.hearing_date,
            li.reference_number legislative_reference,
            li.title legislative_title
     FROM surveys s
     LEFT JOIN hearings h ON h.id = s.hearing_id
     LEFT JOIN legislative_items li ON li.id = s.legislative_item_id
     WHERE s.id = :id'
);
$stmt->execute([':id' => $id]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$survey) {
    http_response_code(404);
    exit('Survey not found.');
}

// Fetch all questions
$qStmt = $pdo->prepare('SELECT * FROM survey_questions WHERE survey_id = :id ORDER BY sequence_number, id');
$qStmt->execute([':id' => $id]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

$options = [];
foreach ($questions as $question) {
    $oStmt = $pdo->prepare('SELECT * FROM survey_question_options WHERE question_id = :qid ORDER BY sequence_number, id');
    $oStmt->execute([':qid' => $question['id']]);
    $options[$question['id']] = $oStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if this citizen has already submitted
$subStmt = $pdo->prepare('SELECT id, submitted_at FROM survey_submissions WHERE survey_id = :id AND user_id = :user ORDER BY id DESC LIMIT 1');
$subStmt->execute([':id' => $id, ':user' => $userId]);
$priorSubmission = $subStmt->fetch(PDO::FETCH_ASSOC);

$submittedAnswers = [];
if ($priorSubmission) {
    $ansStmt = $pdo->prepare(
        'SELECT a.*, o.option_text
         FROM survey_answers a
         LEFT JOIN survey_question_options o ON o.id = a.option_id
         WHERE a.submission_id = :sid'
    );
    $ansStmt->execute([':sid' => (int)$priorSubmission['id']]);
    while ($row = $ansStmt->fetch(PDO::FETCH_ASSOC)) {
        $qid = (int)$row['question_id'];
        if (!isset($submittedAnswers[$qid])) {
            $submittedAnswers[$qid] = [];
        }
        if ($row['option_text'] !== null) {
            $submittedAnswers[$qid][] = $row['option_text'];
        } elseif ($row['numeric_value'] !== null) {
            $submittedAnswers[$qid][] = (string)$row['numeric_value'];
        } elseif ($row['answer_text'] !== null) {
            $submittedAnswers[$qid][] = $row['answer_text'];
        }
    }
}

$now = time();
$opensAt = !empty($survey['opens_at']) ? strtotime($survey['opens_at']) : null;
$closesAt = !empty($survey['closes_at']) ? strtotime($survey['closes_at']) : null;
$isScheduled = ($opensAt && $now < $opensAt);
$isExpired = ($closesAt && $now > $closesAt);
$isActive = ($survey['status'] === 'Active');
$isOpen = $isActive && !$isScheduled && !$isExpired;

$pageTitle = $survey['title'];
$activeMenu = 'surveys';
$extraCss = [appUrl('assets/css/public-modules.css')];

include __DIR__ . '/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/../../layouts/sidebar.php'; ?>
<main class="main-content">

  <!-- Breadcrumb Head -->
  <div class="page-head">
    <div>
      <a class="small text-decoration-none" href="<?= e(appUrl('modules/surveys/index.php')) ?>">
        <i class="bi bi-arrow-left"></i> Consultation Surveys
      </a>
      <h1 class="mt-1"><?= e($survey['title']) ?></h1>
      <p>
        <?php if ($survey['hearing_reference']): ?>
          <span class="badge" style="background: rgba(169, 121, 0, 0.12); color: #8A6400; border: 1px solid rgba(169, 121, 0, 0.3); font-size: 0.75rem;">
            <i class="bi bi-calendar-event me-1"></i>Hearing: <?= e($survey['hearing_reference']) ?>
          </span>
        <?php endif; ?>
        <?php if ($survey['legislative_reference']): ?>
          <span class="badge bg-light text-dark border font-monospace ms-1" style="font-size: 0.75rem;">
            <?= e($survey['legislative_reference']) ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($survey['closes_at'])): ?>
          <span class="text-danger small ms-2"><i class="bi bi-clock me-1"></i>Closing: <?= formatDate($survey['closes_at']) ?></span>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="row justify-content-center">
    <div class="col-xl-9">

      <!-- Background Description Card -->
      <?php if (!empty($survey['description']) || !empty($survey['hearing_title'])): ?>
        <div class="card portal-card mb-4" style="border-left: 4px solid #a97900 !important;">
          <div class="card-body">
            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1 text-primary"></i> Consultation Background</h6>
            <?php if (!empty($survey['hearing_title'])): ?>
              <div class="small text-muted mb-2">
                Linked Hearing: <strong><?= e($survey['hearing_title']) ?></strong>
              </div>
            <?php endif; ?>
            <?php if (!empty($survey['description'])): ?>
              <p class="text-secondary small mb-0"><?= nl2br(e($survey['description'])) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($priorSubmission): ?>
        <!-- ALREADY ANSWERED NOTICE & SUMMARY -->
        <div class="alert alert-success border border-success d-flex align-items-start gap-3 p-3.5 mb-4" style="border-radius: 12px; background: #ecfdf5;">
          <i class="bi bi-check-circle-fill text-success fs-3 flex-shrink-0"></i>
          <div>
            <h5 class="fw-bold text-success mb-1">You have participated in this consultation survey</h5>
            <p class="small text-secondary mb-0">
              Your response was recorded on <strong><?= date('F j, Y \a\t g:i A', strtotime($priorSubmission['submitted_at'])) ?></strong> under your verified citizen profile. Below is the summary of the answers you provided.
            </p>
          </div>
        </div>

        <!-- Read-only View of Submitted Answers -->
        <div class="card portal-card mb-4">
          <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-clipboard-check me-1 text-success"></i> Your Submitted Answers</span>
          </div>
          <div class="card-body p-4">
            <?php foreach ($questions as $idx => $q): ?>
              <?php
                $qid = (int)$q['id'];
                $answers = $submittedAnswers[$qid] ?? [];
              ?>
              <div class="border-bottom pb-3 mb-3">
                <div class="fw-semibold text-dark mb-1.5" style="font-size: 0.92rem;">
                  <span class="badge bg-secondary-subtle text-secondary me-1"><?= $idx + 1 ?></span>
                  <?= e($q['question_text']) ?>
                </div>
                <div class="ps-4">
                  <?php if (empty($answers)): ?>
                    <span class="text-muted small fst-italic">No answer recorded.</span>
                  <?php elseif ($q['question_type'] === 'Rating'): ?>
                    <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 0.85rem;">
                      <?= e($answers[0]) ?> ★
                    </span>
                  <?php else: ?>
                    <?php foreach ($answers as $ans): ?>
                      <div class="text-secondary small bg-light p-2 rounded border mb-1">
                        <?= nl2br(e($ans)) ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="text-center pt-2">
              <a href="index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i> Return to Surveys List
              </a>
            </div>
          </div>
        </div>

      <?php elseif (!$isOpen): ?>
        <!-- SURVEY NOT OPEN -->
        <div class="card portal-card text-center py-5">
          <?php if ($isScheduled): ?>
            <i class="bi bi-clock-history text-warning display-4 d-block mb-3"></i>
            <h4 class="fw-bold text-dark">Survey Opens Soon</h4>
            <p class="text-muted">This consultation survey is scheduled to open on <strong><?= formatDateTime($survey['opens_at']) ?></strong>.</p>
          <?php else: ?>
            <i class="bi bi-lock-fill text-danger display-4 d-block mb-3"></i>
            <h4 class="fw-bold text-dark">Survey Closed</h4>
            <p class="text-muted">This consultation survey is closed and is no longer accepting new responses.</p>
          <?php endif; ?>
          <div>
            <a href="index.php" class="btn btn-outline-secondary">Return to Surveys</a>
          </div>
        </div>

      <?php else: ?>

        <!-- ACTIVE QUESTIONNAIRE FORM -->
        <div class="card portal-card mb-4" style="border-top: 3.5px solid #0F2137 !important;">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-ui-checks-grid me-2 text-primary"></i>Consultation Questionnaire</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle">Accepting Responses</span>
          </div>

          <div class="card-body p-4">
            <!-- Verified Citizen Header Tag -->
            <div class="d-flex align-items-center gap-2 p-2.5 bg-light rounded-2 border mb-4">
              <i class="bi bi-shield-check text-success fs-5"></i>
              <div class="small">
                <span class="text-muted">Submitting as:</span>
                <strong><?= e($user['full_name'] ?? 'Citizen User') ?></strong> (<?= e($user['email'] ?? '') ?>)
                <span class="badge bg-success-subtle text-success ms-1">Verified Citizen</span>
              </div>
            </div>

            <form id="portalSurveyForm">
              <?= csrfField() ?>
              <input type="hidden" name="survey_id" value="<?= (int)$id ?>">

              <?php foreach ($questions as $idx => $q): ?>
                <?php
                  $qid = (int)$q['id'];
                  $isReq = (int)$q['is_required'] === 1;
                  $qType = $q['question_type'];
                ?>
                <div class="border rounded-3 p-3 mb-3 bg-white" style="border-left: 3.5px solid #a97900 !important;">
                  <div class="fw-bold text-dark mb-2.5 d-flex justify-content-between align-items-start">
                    <div>
                      <span class="badge bg-secondary-subtle text-secondary me-1"><?= $idx + 1 ?></span>
                      <?= e($q['question_text']) ?>
                      <?php if ($isReq): ?>
                        <span class="text-danger" title="Required">*</span>
                      <?php endif; ?>
                    </div>
                    <small class="text-muted font-monospace" style="font-size: 0.68rem;"><?= e($qType) ?></small>
                  </div>

                  <?php if ($qType === 'Long Text'): ?>
                    <textarea class="form-control" name="q[<?= $qid ?>]" rows="4" placeholder="Write your comments, suggestions, or concerns here..." <?= $isReq ? 'required' : '' ?>></textarea>

                  <?php elseif ($qType === 'Single Choice'): ?>
                    <div class="d-grid gap-2">
                      <?php foreach ($options[$qid] as $o): ?>
                        <label class="d-flex align-items-center p-2.5 border rounded-2 bg-light cursor-pointer hover-shadow" style="cursor: pointer;">
                          <input class="form-check-input me-2" type="radio" name="q[<?= $qid ?>]" value="<?= (int)$o['id'] ?>" <?= $isReq ? 'required' : '' ?>>
                          <span class="small fw-semibold text-dark"><?= e($o['option_text']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>

                  <?php elseif ($qType === 'Multiple Choice'): ?>
                    <div class="d-grid gap-2">
                      <?php foreach ($options[$qid] as $o): ?>
                        <label class="d-flex align-items-center p-2.5 border rounded-2 bg-light cursor-pointer hover-shadow" style="cursor: pointer;">
                          <input class="form-check-input me-2" type="checkbox" name="q[<?= $qid ?>][]" value="<?= (int)$o['id'] ?>">
                          <span class="small fw-semibold text-dark"><?= e($o['option_text']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>

                  <?php elseif ($qType === 'Rating'): ?>
                    <div class="d-flex gap-2 flex-wrap">
                      <?php
                        $ratings = [
                          1 => ['1 ★', 'Strongly Disagree / Poor'],
                          2 => ['2 ★', 'Disagree / Fair'],
                          3 => ['3 ★', 'Neutral / Moderate'],
                          4 => ['4 ★', 'Agree / Good'],
                          5 => ['5 ★', 'Strongly Agree / Excellent'],
                        ];
                      ?>
                      <?php foreach ($ratings as $rNum => [$rStars, $rLbl]): ?>
                        <label class="flex-grow-1 p-2 text-center border rounded-2 bg-light rating-option" style="cursor: pointer; min-width: 90px;">
                          <input type="radio" name="q[<?= $qid ?>]" value="<?= $rNum ?>" class="d-none" <?= $isReq ? 'required' : '' ?>>
                          <strong class="d-block text-warning"><?= $rStars ?></strong>
                          <small class="text-secondary d-block" style="font-size: 0.68rem;"><?= e($rLbl) ?></small>
                        </label>
                      <?php endforeach; ?>
                    </div>

                  <?php elseif ($qType === 'Number'): ?>
                    <input type="number" step="any" class="form-control" name="q[<?= $qid ?>]" placeholder="Enter numeric response..." <?= $isReq ? 'required' : '' ?>>

                  <?php else: ?>
                    <input type="text" class="form-control" name="q[<?= $qid ?>]" placeholder="Your answer..." <?= $isReq ? 'required' : '' ?>>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>

              <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                <span class="small text-muted"><span class="text-danger">*</span> Required questions</span>
                <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnSubmitSurvey">
                  <i class="bi bi-send-check me-1"></i> Submit My Response
                </button>
              </div>
            </form>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>

</main>
</div>

<!-- Include SweetAlert2 from CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Style rating options on click
  document.querySelectorAll('.rating-option').forEach(label => {
    label.addEventListener('click', function() {
      const container = this.closest('.d-flex');
      container.querySelectorAll('.rating-option').forEach(l => {
        l.classList.remove('border-warning', 'bg-warning-subtle');
        l.classList.add('bg-light');
      });
      this.classList.remove('bg-light');
      this.classList.add('border-warning', 'bg-warning-subtle');
      const radio = this.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    });
  });

  const form = document.getElementById('portalSurveyForm');
  if (form) {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.getElementById('btnSubmitSurvey');
      const origText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

      try {
        const formData = new FormData(form);
        const res = await fetch('<?= e(appUrl('modules/surveys/submit.php')) ?>', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });

        const data = await res.json();

        if (data && data.success) {
          // 1. Instantly replace form with smooth confirmation screen
          const formCardBody = form.closest('.card-body');
          if (formCardBody) {
            formCardBody.innerHTML = `
              <div class="text-center py-4">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                  <i class="bi bi-check-circle-fill display-5"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Maraming Salamat!</h4>
                <p class="text-secondary small mb-3">${data.message || 'Matagumpay na naitala ang iyong sagot sa consultation survey na ito.'}</p>
                <div class="d-flex justify-content-center align-items-center gap-2 text-primary small">
                  <span class="spinner-border spinner-border-sm"></span>
                  <span>Inililipat ka sa iyong opisyal na sagot...</span>
                </div>
              </div>
            `;
          }

          // 2. Show SweetAlert toast if available (display for 1.5 seconds)
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: 'Maraming Salamat!',
              text: data.message || 'Naitala na ang iyong sagot.',
              timer: 1500,
              showConfirmButton: false
            });
          }

          // 3. Smooth auto-reload after 1.5 seconds (1500ms) as requested
          setTimeout(() => {
            window.location.reload();
          }, 1500);

        } else {
          btn.disabled = false;
          btn.innerHTML = origText;
          const msg = (data && data.message) ? data.message : 'Hindi naisumite ang sagot. Pakitingnan ang mga tanong.';
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'warning',
              title: 'Paunawa',
              text: msg
            });
          } else {
            alert(msg);
          }
        }
      } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origText;
        console.error('Survey submit error:', err);
        const errMsg = 'Nagkaroon ng problema sa network. Pakisubukang muli.';
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errMsg
          });
        } else {
          alert(errMsg);
        }
      }
    });
  }
});
</script>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
