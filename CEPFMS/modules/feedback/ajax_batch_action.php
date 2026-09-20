<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.manage');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
    exit;
}

$action = clean($_POST['batch_action'] ?? '');
$ids = $_POST['ids'] ?? [];
$categoryId = (int)($_POST['category_id'] ?? 0);

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No feedback items selected.']);
    exit;
}

$cleanIds = array_map('intval', $ids);
$cleanIds = array_filter($cleanIds, fn($id) => $id > 0);

if (empty($cleanIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid feedback IDs selected.']);
    exit;
}

$pdo = db();
$inPlaceholder = implode(',', array_fill(0, count($cleanIds), '?'));
$userId = currentUserId();

try {
    $pdo->beginTransaction();

    if ($action === 'validate') {
        $stmt = $pdo->prepare("UPDATE cef_submissions SET status = 'Validated', moderation_status = 'Validated', updated_at = NOW() WHERE id IN ($inPlaceholder) AND submission_type = 'Feedback' AND deleted_at IS NULL");
        $stmt->execute($cleanIds);

        foreach ($cleanIds as $subId) {
            cefLogHistory($pdo, $subId, 'Batch Validated', 'Submitted', 'Validated', 'Batch validated via Feedback Registry triage bar.', true, $userId);
        }
        $msg = count($cleanIds) . ' feedback item(s) marked as Validated.';
    } elseif ($action === 'categorize') {
        if ($categoryId <= 0) {
            throw new Exception('Please select a valid category.');
        }
        $catName = $pdo->query("SELECT name FROM cef_categories WHERE id = $categoryId")->fetchColumn() ?: 'Category #' . $categoryId;
        $params = array_merge([$categoryId], $cleanIds);
        $stmt = $pdo->prepare("UPDATE cef_submissions SET category_id = ?, updated_at = NOW() WHERE id IN ($inPlaceholder) AND submission_type = 'Feedback' AND deleted_at IS NULL");
        $stmt->execute($params);

        foreach ($cleanIds as $subId) {
            cefLogHistory($pdo, $subId, 'Batch Categorized', null, null, "Batch categorized to: $catName", false, $userId);
        }
        $msg = count($cleanIds) . " feedback item(s) assigned to $catName.";
    } elseif ($action === 'moderation') {
        $stmt = $pdo->prepare("UPDATE cef_submissions SET status = 'Under Moderation', moderation_status = 'Under Review', updated_at = NOW() WHERE id IN ($inPlaceholder) AND submission_type = 'Feedback' AND deleted_at IS NULL");
        $stmt->execute($cleanIds);

        foreach ($cleanIds as $subId) {
            cefLogHistory($pdo, $subId, 'Batch Moved to Moderation', null, 'Under Moderation', 'Sent to moderation review queue.', false, $userId);
        }
        $msg = count($cleanIds) . ' feedback item(s) routed to Moderation Queue.';
    } else {
        throw new Exception('Unknown batch action.');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
