<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';

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
$pdo->prepare("UPDATE cef_feedback_submissions SET upvote_count = upvote_count + 1 WHERE submission_id = ?")->execute([$submissionId]);
$newCount = (int)$pdo->query("SELECT upvote_count FROM cef_feedback_submissions WHERE submission_id = $submissionId")->fetchColumn();

echo json_encode(['success' => true, 'new_count' => $newCount, 'message' => 'Upvote recorded. Community resonance updated.']);
