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

$childId = (int)($_POST['child_submission_id'] ?? 0);
$masterId = (int)($_POST['master_submission_id'] ?? 0);

if ($childId <= 0 || $masterId <= 0 || $childId === $masterId) {
    echo json_encode(['success' => false, 'message' => 'Please specify valid and distinct child and master ticket IDs.']);
    exit;
}

$pdo = db();
$userId = currentUserId();

try {
    $pdo->beginTransaction();

    $child = cefSubmissionRow($pdo, $childId);
    $master = cefSubmissionRow($pdo, $masterId);

    if (!$child || !$master) {
        throw new Exception('Child or Master submission record not found.');
    }

    // Update child submission to Duplicate and link to master
    $upd = $pdo->prepare("UPDATE cef_submissions SET 
        master_submission_id = ?, 
        status = 'Duplicate', 
        moderation_status = 'Duplicate', 
        updated_at = NOW() 
        WHERE id = ?");
    $upd->execute([$masterId, $childId]);

    // Record in duplicate matches table
    $chk = $pdo->prepare("SELECT id FROM cef_duplicate_matches WHERE submission_id = ? AND matched_submission_id = ?");
    $chk->execute([$childId, $masterId]);
    if (!$chk->fetch()) {
        $ins = $pdo->prepare("INSERT INTO cef_duplicate_matches (submission_id, matched_submission_id, similarity_score, match_reasons, status, reviewed_by, reviewed_at) VALUES (?, ?, 0.95, 'Merged via Moderation Desk into Master Ticket', 'Confirmed', ?, NOW())");
        $ins->execute([$childId, $masterId, $userId]);
    } else {
        $pdo->prepare("UPDATE cef_duplicate_matches SET status = 'Confirmed', reviewed_by = ?, reviewed_at = NOW() WHERE submission_id = ? AND matched_submission_id = ?")->execute([$userId, $childId, $masterId]);
    }

    cefLogHistory($pdo, $childId, 'Merged as Duplicate', $child['status'], 'Duplicate', "Consolidated into Master Case: {$master['reference_number']} ({$master['title']})", true, $userId);
    cefLogHistory($pdo, $masterId, 'Child Ticket Merged', null, null, "Linked duplicate child case: {$child['reference_number']} ({$child['title']})", false, $userId);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully merged {$child['reference_number']} into Master Case {$master['reference_number']}!"
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
