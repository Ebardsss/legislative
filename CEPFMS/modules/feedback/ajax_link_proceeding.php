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

$submissionId = (int)($_POST['submission_id'] ?? 0);
$legislativeItemId = (int)($_POST['legislative_item_id'] ?? 0);
$notes = clean($_POST['notes'] ?? '');

if ($submissionId <= 0 || $legislativeItemId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid proceeding or legislative item.']);
    exit;
}

$pdo = db();
$userId = currentUserId();

try {
    $legItem = $pdo->prepare("SELECT id, reference_number, title FROM legislative_items WHERE id = ? AND deleted_at IS NULL");
    $legItem->execute([$legislativeItemId]);
    $item = $legItem->fetch();

    if (!$item) {
        throw new Exception('Legislative item not found.');
    }

    $chk = $pdo->prepare("SELECT id FROM cef_legislative_referrals WHERE submission_id = ? AND legislative_item_id = ?");
    $chk->execute([$submissionId, $legislativeItemId]);
    if ($chk->fetch()) {
        throw new Exception('This feedback is already linked to this legislative item.');
    }

    $ins = $pdo->prepare("INSERT INTO cef_legislative_referrals (submission_id, legislative_item_id, referral_type, notes, referred_by, referred_at, status) VALUES (?, ?, 'Civic Feedback Link', ?, ?, NOW(), 'Linked')");
    $ins->execute([$submissionId, $legislativeItemId, $notes ?: 'Linked for legislative session review.', $userId]);

    cefLogHistory($pdo, $submissionId, 'Legislative Item Linked', null, null, "Linked to {$item['reference_number']} - {$item['title']}", true, $userId);

    echo json_encode(['success' => true, 'message' => "Successfully linked to {$item['reference_number']}."]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
