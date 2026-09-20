<?php
declare(strict_types=1);

/**
 * modules/responses/ajax_ai_draft.php
 * AJAX endpoint for Ollama AI-generated official government response drafts.
 */
require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$responseType = clean($_POST['response_type'] ?? 'Official Response');

if ($submissionId <= 0) {
    jsonResponse(false, 'Valid submission ID is required.');
}

$submission = cefSubmissionRow($pdo, $submissionId);
if (!$submission) {
    jsonResponse(false, 'Citizen submission not found.');
}

try {
    $aiService = cepfmsAiService();
    $draft = $aiService->generateResponseDraft($submission, $responseType);

    if (!$draft['success']) {
        jsonResponse(false, $draft['error'] ?? 'Ollama could not generate response draft.');
    }

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS AI Response Draft',
        "{$submission['reference_number']} · Generated {$responseType} draft via Ollama ({$draft['model_used']})."
    );

    jsonResponse(true, 'Response draft generated.', [
        'subject' => $draft['subject'],
        'body' => $draft['body'],
        'model' => $draft['model_used'] ?? 'ollama',
        'duration_ms' => $draft['duration_ms'] ?? 0,
    ]);
} catch (Throwable $e) {
    jsonResponse(false, 'Draft generation failed: ' . $e->getMessage());
}
