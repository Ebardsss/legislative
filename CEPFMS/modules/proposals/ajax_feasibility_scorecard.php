<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.proposals.manage');

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
$legalMandate = clean($_POST['legal_mandate_status'] ?? 'Compliant (LGC Sec. 16)');
$budgetImpact = clean($_POST['budget_impact'] ?? 'Low / Standard Allocation');
$impactRating = max(1, min(5, (int)($_POST['impact_rating'] ?? 4)));
$feasibilityStatus = clean($_POST['feasibility_status'] ?? 'For Study');

if ($submissionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid proposal ID.']);
    exit;
}

$pdo = db();
$userId = currentUserId();

try {
    $stmt = $pdo->prepare("UPDATE cef_proposals SET 
        legal_mandate_status = ?, 
        budget_impact = ?, 
        impact_rating = ?, 
        feasibility_status = ?, 
        updated_at = NOW() 
        WHERE submission_id = ?");
    $stmt->execute([$legalMandate, $budgetImpact, $impactRating, $feasibilityStatus, $submissionId]);

    cefLogHistory($pdo, $submissionId, 'Feasibility Scorecard Updated', null, null, "Audited: Mandate: $legalMandate | Budget: $budgetImpact | Impact: $impactRating/5 Stars", false, $userId);

    echo json_encode(['success' => true, 'message' => 'Feasibility scorecard saved successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
