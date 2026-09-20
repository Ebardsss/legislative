<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.manage');

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
$artaTier = clean($_POST['arta_tier'] ?? 'Simple (3 Days)');
$urgencyLevel = clean($_POST['urgency_level'] ?? 'Normal');

if ($submissionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
    exit;
}

$pdo = db();
$userId = currentUserId();

try {
    $days = match ($artaTier) {
        'Complex (7 Days)' => 7,
        'Highly Technical (20 Days)' => 20,
        default => 3,
    };

    // Compute new resolution target date
    $targetDate = date('Y-m-d H:i:s', strtotime("+{$days} weekdays"));

    $stmt = $pdo->prepare("UPDATE cef_complaints SET 
        arta_tier = ?, 
        urgency_level = ?, 
        resolution_target_at = ?, 
        updated_at = NOW() 
        WHERE submission_id = ?");
    $stmt->execute([$artaTier, $urgencyLevel, $targetDate, $submissionId]);

    cefLogHistory($pdo, $submissionId, 'ARTA SLA Adjusted', null, null, "ARTA Classification: $artaTier (Target: $targetDate) | Urgency: $urgencyLevel", false, $userId);

    echo json_encode([
        'success' => true,
        'message' => "ARTA tier updated to $artaTier. Resolution target set to " . formatDateTime($targetDate) . "."
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
