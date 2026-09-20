<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.manage');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
    exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
if ($submissionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID.']);
    exit;
}

$pdo = db();
$sub = cefSubmissionRow($pdo, $submissionId);
if (!$sub) {
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

$text = $sub['details'];

// PII regex redactions
// 1. Phone numbers (e.g. 0917-123-4567, 09171234567, +639171234567)
$text = preg_replace('/(\+?63|0)9\d{2}[-\s]?\d{3}[-\s]?\d{4}/', '[REDACTED PHONE NUMBER]', $text);
// 2. Email addresses
$text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED EMAIL]', $text);
// 3. Profanity masking
$profanities = ['putangina', 'tangina', 'gago', 'tarantado', 'ulol', 'pota', 'puta', 'leche', 'bwitset', 'inutil'];
foreach ($profanities as $bad) {
    $text = preg_replace('/\b' . preg_quote($bad, '/') . '\b/i', '***', $text);
}

$pdo->prepare("UPDATE cef_submissions SET details = ?, updated_at = NOW() WHERE id = ?")->execute([$text, $submissionId]);

$userId = currentUserId();
cefLogHistory($pdo, $submissionId, 'PII & Content Redaction Applied', null, null, 'Automated Privacy & Content Redaction applied by moderator.', false, $userId);

echo json_encode([
    'success' => true,
    'message' => 'PII and inappropriate terms successfully redacted!',
    'sanitized_text' => $text
]);
