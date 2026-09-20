<?php
declare(strict_types=1);

/**
 * modules/complaints/ajax_ai_complaint.php
 * AJAX endpoint for Ollama AI Complaint & Issue assistance in CEPFMS.
 */
require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'analyze'));

if ($submissionId <= 0) {
    jsonResponse(false, 'Valid complaint ID is required.');
}

$submission = cefSubmissionRow($pdo, $submissionId);
if (!$submission || $submission['submission_type'] !== 'Complaint') {
    jsonResponse(false, 'Complaint record not found.');
}

$complaint = cefComplaintRow($pdo, $submissionId) ?: [];
$aiService = cepfmsAiService();

// Action: Generate Case Update Draft
if ($action === 'draft_update') {
    requireCefPermission('cepfms.complaints.manage');
    $updateType = clean($_POST['update_type'] ?? 'Progress Update');
    try {
        $res = $aiService->generateCaseUpdateDraft($submission, $updateType);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not generate case update.');
        }
        jsonResponse(true, 'AI update draft generated.', ['update_text' => $res['update_text']]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI draft error: ' . $e->getMessage());
    }
}

// Action: Generate Escalation Reason Draft
if ($action === 'draft_escalation') {
    requireCefPermission('cepfms.complaints.manage');
    $level = clean($_POST['escalation_level'] ?? 'High');
    try {
        $res = $aiService->generateEscalationDraft($submission, $level);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not generate escalation reasoning.');
        }
        jsonResponse(true, 'AI escalation reasoning generated.', ['reason' => $res['reason']]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI escalation draft error: ' . $e->getMessage());
    }
}

// Action: Apply AI Routing & Urgency
if ($action === 'apply_routing') {
    requireCefPermission('cepfms.complaints.manage');
    $suggestedCategory = clean($_POST['category'] ?? '');
    $suggestedUrgency = clean($_POST['urgency'] ?? 'Normal');
    $suggestedOffice = clean($_POST['office'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Update urgency
        if (in_array($suggestedUrgency, ['Low', 'Normal', 'High', 'Urgent'], true)) {
            $pdo->prepare('UPDATE cef_complaints SET urgency_level = :urgency, updated_at = NOW() WHERE submission_id = :id')
                ->execute([':urgency' => $suggestedUrgency, ':id' => $submissionId]);

            $pdo->prepare('UPDATE cef_submissions SET priority_level = :prio, updated_at = NOW() WHERE id = :id')
                ->execute([':prio' => $suggestedUrgency, ':id' => $submissionId]);
        }

        // 2. Update category if matched
        if ($suggestedCategory !== '') {
            $catStmt = $pdo->prepare('SELECT id FROM cef_categories WHERE name LIKE :cat AND is_active = 1 LIMIT 1');
            $catStmt->execute([':cat' => '%' . $suggestedCategory . '%']);
            $catId = $catStmt->fetchColumn();
            if ($catId) {
                $pdo->prepare('UPDATE cef_submissions SET category_id = :cat, updated_at = NOW() WHERE id = :id')
                    ->execute([':cat' => (int)$catId, ':id' => $submissionId]);
            }
        }

        // 3. Find and assign office if matched
        $matchedOfficeId = null;
        if ($suggestedOffice !== '') {
            $offStmt = $pdo->prepare('SELECT id, name FROM offices WHERE name LIKE :off AND status = "Active" LIMIT 1');
            $offStmt->execute([':off' => '%' . $suggestedOffice . '%']);
            $offRow = $offStmt->fetch();
            if ($offRow) {
                $matchedOfficeId = (int)$offRow['id'];
                // Check if primary assignment exists
                $existStmt = $pdo->prepare('SELECT id FROM cef_assignments WHERE submission_id = :id AND assignment_role = "Primary" AND status <> "Cancelled" LIMIT 1');
                $existStmt->execute([':id' => $submissionId]);
                $existAssignId = $existStmt->fetchColumn();

                if ($existAssignId) {
                    $pdo->prepare('UPDATE cef_assignments SET office_id = :off, notes = CONCAT(COALESCE(notes, ""), " [AI Routed to ", :off_name, "]"), updated_at = NOW() WHERE id = :id')
                        ->execute([':off' => $matchedOfficeId, ':off_name' => $offRow['name'], ':id' => (int)$existAssignId]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO cef_assignments (submission_id, office_id, assignment_role, status, assigned_by, notes, assigned_at)
                         VALUES (:sub, :off, "Primary", "Assigned", :user, :notes, NOW())'
                    )->execute([
                        ':sub' => $submissionId,
                        ':off' => $matchedOfficeId,
                        ':user' => currentUserId(),
                        ':notes' => 'Primary assignment auto-suggested by Ollama AI (' . $offRow['name'] . ')',
                    ]);
                }
            }
        }

        cefSubmissionHistory(
            $pdo,
            $submissionId,
            'AI Complaint Routing Applied',
            $submission['status'],
            $submission['status'],
            "Applied AI routing: Urgency [{$suggestedUrgency}], Category [{$suggestedCategory}], Office [{$suggestedOffice}].",
            false
        );

        cepfmsLogActivity(
            currentUserId(),
            'CEPFMS AI Complaint Routing',
            "{$submission['reference_number']} · Urgency & office assignment updated via Ollama AI."
        );

        $pdo->commit();
        jsonResponse(true, 'AI recommendations applied to complaint.', [
            'urgency' => $suggestedUrgency,
            'category' => $suggestedCategory,
            'office_id' => $matchedOfficeId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Failed to apply routing: ' . $e->getMessage());
    }
}

// Default Action: Analyze Complaint
try {
    $analysis = $aiService->analyzeSubmission($submission);
    if (!$analysis['success']) {
        jsonResponse(false, $analysis['error'] ?? 'Ollama AI complaint analysis could not be completed.');
    }

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS AI Complaint Analysis',
        "{$submission['reference_number']} · Urgency: {$analysis['urgency_level']}, Category: {$analysis['recommended_category']}."
    );

    jsonResponse(true, 'Complaint analysis complete.', [
        'analysis' => $analysis,
    ]);
} catch (Throwable $e) {
    jsonResponse(false, 'Analysis error: ' . $e->getMessage());
}
