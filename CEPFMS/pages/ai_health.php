<?php
declare(strict_types=1);

/**
 * pages/ai_health.php
 * Ollama AI Health Check and Diagnostics for CEPFMS.
 */
require_once __DIR__ . '/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.system_health.view');

$pdo = db();
$pageTitle = 'Ollama AI Health Diagnostics';
$activeMenu = 'ai_health';
$extraCss = [
    appUrl('assets/css/dashboard.css'),
    appUrl('assets/css/cepfms-operational.css'),
];

$ai = cepfmsAiService();
$health = $ai->healthCheck();

$testOutput = null;
$testError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_prompt'])) {
    requireCsrf();
    $testText = trim((string)$_POST['test_prompt']);
    if ($testText !== '') {
        $simSubmission = [
            'id' => 0,
            'title' => 'Sample Test Complaint',
            'details' => $testText,
            'submission_type' => 'Complaint',
            'location_text' => 'District 1, Tondo',
        ];
        $testRes = $ai->analyzeSubmission($simSubmission);
        if ($testRes['success']) {
            $testOutput = $testRes;
        } else {
            $testError = $testRes['error'] ?? 'Ollama request failed.';
        }
    }
}

// AI Analysis History Counts
$totalAiRecords = (int)$pdo->query('SELECT COUNT(*) FROM cef_ai_analysis')->fetchColumn();
$recentAi = $pdo->query(
    'SELECT a.*, s.reference_number, s.title
     FROM cef_ai_analysis a
     JOIN cef_submissions s ON s.id = a.submission_id
     ORDER BY a.id DESC LIMIT 8'
)->fetchAll();

include __DIR__ . '/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__ . '/../layouts/sidebar.php'; ?><main class="main-content">

<div class="dashboard-page-header mb-4">
  <div>
    <div class="dashboard-eyebrow"><i class="bi bi-robot"></i> Subsystem #10 · Intelligent Civic Operations</div>
    <h1>Ollama AI Health & Diagnostics</h1>
    <p>Real-time connectivity, model readiness, local Philippine DPA privacy verification, and live inference testing.</p>
  </div>
  <div class="dashboard-header-actions">
    <a href="system_health.php" class="btn btn-outline-secondary"><i class="bi bi-heart-pulse"></i> System Health</a>
    <a href="?refresh=1" class="btn btn-primary"><i class="bi bi-arrow-clockwise"></i> Re-Check Status</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="cef-stat h-100">
      <i class="bi bi-cpu text-<?= $health['available'] ? 'success' : 'danger' ?>"></i>
      <div>
        <strong><?= $health['available'] ? 'Online' : 'Offline' ?></strong>
        <small>Ollama Daemon Status</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="cef-stat h-100">
      <i class="bi bi-layers text-<?= !empty($health['model_installed']) ? 'success' : 'warning' ?>"></i>
      <div>
        <strong><?= !empty($health['model_installed']) ? 'Ready' : 'Not Found' ?></strong>
        <small>Model (<?= e(OLLAMA_MODEL) ?>)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="cef-stat h-100">
      <i class="bi bi-shield-check text-primary"></i>
      <div>
        <strong>100% On-Premise</strong>
        <small>Data Privacy (DPA)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="cef-stat h-100">
      <i class="bi bi-stars text-warning"></i>
      <div>
        <strong><?= $totalAiRecords ?></strong>
        <small>Total AI Triage Records</small>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card cef-card h-100">
      <div class="card-header bg-light"><strong>Configuration & Service Details</strong></div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><th class="text-muted" style="width: 180px;">Ollama Base URL:</th><td><code><?= e(OLLAMA_BASE_URL) ?></code></td></tr>
            <tr><th class="text-muted">Active Model:</th><td><span class="badge bg-dark"><?= e(OLLAMA_MODEL) ?></span></td></tr>
            <tr><th class="text-muted">Connect Timeout:</th><td><?= AI_CONNECT_TIMEOUT_SECONDS ?> seconds</td></tr>
            <tr><th class="text-muted">Request Timeout:</th><td><?= AI_REQUEST_TIMEOUT_SECONDS ?> seconds</td></tr>
            <tr><th class="text-muted">Keep Alive:</th><td><?= AI_MODEL_KEEP_ALIVE_MINUTES ?> minutes</td></tr>
            <tr>
              <th class="text-muted">Service Message:</th>
              <td>
                <span class="badge bg-<?= $health['available'] && !empty($health['model_installed']) ? 'success' : 'danger' ?>">
                  <?= e($health['message'] ?? 'Unknown status') ?>
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="mt-4 pt-3 border-top">
          <label class="form-label small text-muted"><strong>Locally Installed Models:</strong></label>
          <div>
            <?php if (!empty($health['models'])): ?>
              <?php foreach ($health['models'] as $m): ?>
                <span class="badge bg-secondary me-1 mb-1 p-2"><?= e($m) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted small">No models detected on localhost:11434.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card cef-card h-100">
      <div class="card-header bg-light"><strong>Interactive Live AI Test</strong></div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <label class="form-label small text-muted">Enter a sample citizen report (English, Tagalog, or Taglish):</label>
          <textarea class="form-control mb-3" name="test_prompt" rows="3" required placeholder="Halimbawa: Sobrang dilim po sa kanto ng Recto Ave, sira ang poste ng ilaw at delikado sa mga estudyante sa gabi."><?= e($_POST['test_prompt'] ?? '') ?></textarea>
          <button class="btn btn-warning" <?= !$health['available'] ? 'disabled' : '' ?>>
            <i class="bi bi-play-fill"></i> Run Test Inference
          </button>
        </form>

        <?php if ($testError): ?>
          <div class="alert alert-danger mt-3 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($testError) ?></div>
        <?php endif; ?>

        <?php if ($testOutput): ?>
          <div class="alert alert-success mt-3 mb-0 small">
            <h6 class="fw-bold mb-2"><i class="bi bi-check-circle me-1"></i>Ollama Inference Result (<?= (int)$testOutput['duration_ms'] ?>ms)</h6>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <span class="badge bg-danger">Urgency: <?= e($testOutput['urgency_level']) ?> (<?= (int)$testOutput['urgency_score'] ?>%)</span>
              <span class="badge bg-secondary">Sentiment: <?= e($testOutput['sentiment']) ?></span>
              <span class="badge bg-info text-dark">Category: <?= e($testOutput['recommended_category']) ?></span>
              <span class="badge bg-dark">Safety: <?= e($testOutput['content_safety']) ?></span>
            </div>
            <p class="mb-1"><strong>Summary:</strong> <?= e($testOutput['executive_summary']) ?></p>
            <p class="mb-0"><strong>Recommended Office:</strong> <?= e($testOutput['recommended_office']) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card cef-card">
  <div class="card-header bg-light"><strong>Recent AI Moderation Records (cef_ai_analysis)</strong></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 small">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Submission</th>
          <th>Analysis Type</th>
          <th>Provider / Model</th>
          <th>Confidence</th>
          <th>Analyzed At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$recentAi): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No AI records stored yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentAi as $row): ?>
          <tr>
            <td><span class="cef-code"><?= e($row['reference_number']) ?></span></td>
            <td><strong><?= e($row['title']) ?></strong></td>
            <td><?= e($row['analysis_type']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= e($row['provider'] . ' / ' . $row['model_used']) ?></span></td>
            <td><?= number_format((float)$row['confidence_score'] * 100, 0) ?>%</td>
            <td><?= formatDateTime($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</main></div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
