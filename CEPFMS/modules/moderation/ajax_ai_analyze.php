<?php
declare(strict_types=1);

/**
 * modules/moderation/ajax_ai_analyze.php
 * AJAX endpoint for Ollama AI submission analysis in Moderation.
 */
require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'analyze'));

if ($submissionId <= 0) {
    jsonResponse(false, 'Valid submission ID is required.');
}

$submission = cefSubmissionRow($pdo, $submissionId);
if (!$submission) {
    jsonResponse(false, 'Submission not found.');
}

if ($action === 'apply') {
    requireCefPermission('cepfms.moderation.manage');

    $suggestedCategory = clean($_POST['category'] ?? '');
    $suggestedPriority = clean($_POST['priority'] ?? '');

    try {
        $pdo->beginTransaction();
        $updates = [];
        $params = [':id' => $submissionId];

        // Match category
        if ($suggestedCategory !== '') {
            $catStmt = $pdo->prepare('SELECT id, name FROM cef_categories WHERE name LIKE :cat AND is_active = 1 LIMIT 1');
            $catStmt->execute([':cat' => '%' . $suggestedCategory . '%']);
            $catRow = $catStmt->fetch();
            if ($catRow) {
                $updates[] = 'category_id = :cat_id';
                $params[':cat_id'] = $catRow['id'];
            }
        }

        // Match priority
        if (in_array($suggestedPriority, ['Low', 'Normal', 'High', 'Urgent'], true)) {
            $updates[] = 'priority_level = :prio';
            $params[':prio'] = $suggestedPriority;

            // Also update complaint urgency if this is a complaint
            if ($submission['submission_type'] === 'Complaint') {
                $pdo->prepare('UPDATE cef_complaints SET urgency_level = :urgency WHERE submission_id = :id')
                    ->execute([':urgency' => $suggestedPriority, ':id' => $submissionId]);
            }
        }

        if (!empty($updates)) {
            $sql = 'UPDATE cef_submissions SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';
            $pdo->prepare($sql)->execute($params);

            cefSubmissionHistory(
                $pdo,
                $submissionId,
                'AI Recommendations Applied',
                $submission['status'],
                $submission['status'],
                "Applied AI recommendations: Category [{$suggestedCategory}], Priority [{$suggestedPriority}].",
                false
            );

            cepfmsLogActivity(
                currentUserId(),
                'CEPFMS AI Recommendations Applied',
                "{$submission['reference_number']} · Category & Priority updated via Ollama AI."
            );
        }

        $pdo->commit();
        jsonResponse(true, 'AI recommendations applied successfully.', [
            'category' => $suggestedCategory,
            'priority' => $suggestedPriority,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Failed to apply recommendations: ' . $e->getMessage());
    }
}

// Default: Perform Analysis
try {
    $aiService = cepfmsAiService();
    $analysis = $aiService->analyzeSubmission($submission);

    if (!$analysis['success']) {
        jsonResponse(false, $analysis['error'] ?? 'Ollama AI analysis could not be completed.');
    }

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS AI Moderation Analysis',
        "{$submission['reference_number']} · Sentiment: {$analysis['sentiment']}, Urgency: {$analysis['urgency_level']} ({$analysis['urgency_score']}%)."
    );

    jsonResponse(true, 'Analysis complete.', [
        'analysis' => $analysis,
    ]);
} catch (Throwable $e) {
    jsonResponse(false, 'AI analysis failed: ' . $e->getMessage());
}
