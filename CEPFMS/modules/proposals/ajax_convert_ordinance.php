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
$itemTypeId = (int)($_POST['item_type_id'] ?? 1); // 1 = Ordinance, 2 = Resolution
$ordinanceTitle = clean($_POST['title'] ?? '');
$committeeId = (int)($_POST['committee_id'] ?? 0);

if ($submissionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid proposal ID.']);
    exit;
}

$pdo = db();
$userId = currentUserId();

try {
    $pdo->beginTransaction();

    $sub = cefSubmissionRow($pdo, $submissionId);
    if (!$sub || $sub['submission_type'] !== 'Proposal') {
        throw new Exception('Proposal record not found.');
    }

    $prop = cefProposalRow($pdo, $submissionId);
    if (!$prop) {
        throw new Exception('Proposal details not found.');
    }

    if (!empty($prop['converted_legislative_item_id'])) {
        $existing = $pdo->query("SELECT id, reference_number FROM legislative_items WHERE id = " . (int)$prop['converted_legislative_item_id'])->fetch();
        if ($existing) {
            throw new Exception("This proposal has already been converted into {$existing['reference_number']}.");
        }
    }

    $year = date('Y');
    $prefix = ($itemTypeId === 2) ? "RES-{$year}-" : "ORD-{$year}-";

    $maxNum = $pdo->query("SELECT reference_number FROM legislative_items WHERE reference_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextSeq = 1;
    if ($maxNum && preg_match('/(\d+)$/', (string)$maxNum, $m)) {
        $nextSeq = (int)$m[1] + 1;
    }
    $refNumber = $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);

    $finalTitle = !empty($ordinanceTitle) ? $ordinanceTitle : ("An Ordinance " . $sub['title']);

    $summaryContent = "ORIGINATING FROM CITIZEN PROPOSAL: " . $sub['reference_number'] . "\n\n"
        . "PROBLEM / RATIONALE:\n" . ($prop['problem_statement'] ?? $sub['details']) . "\n\n"
        . "PROPOSED SOLUTION:\n" . ($prop['proposed_solution'] ?? 'See citizen submission.') . "\n\n"
        . "EXPECTED PUBLIC BENEFIT:\n" . ($prop['expected_public_benefit'] ?? 'Promoting general welfare.');

    $ins = $pdo->prepare("INSERT INTO legislative_items (
        reference_number, item_type_id, originating_office_id, title, summary, current_status, priority_level, visibility, created_by, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, 'Draft', ?, 'Internal', ?, NOW(), NOW())");

    $ins->execute([
        $refNumber,
        $itemTypeId,
        $committeeId > 0 ? $committeeId : null,
        $finalTitle,
        $summaryContent,
        $sub['priority_level'] ?: 'Normal',
        $userId
    ]);

    $newItemId = (int)$pdo->lastInsertId();

    // Update proposal record with converted legislative item ID
    $pdo->prepare("UPDATE cef_proposals SET converted_legislative_item_id = ?, disposition = 'Converted to Draft Ordinance', feasibility_status = 'Feasible' WHERE submission_id = ?")->execute([$newItemId, $submissionId]);

    // Update submission status to In Progress
    $pdo->prepare("UPDATE cef_submissions SET status = 'In Progress', updated_at = NOW() WHERE id = ?")->execute([$submissionId]);

    // Insert legislative referral link
    $refStmt = $pdo->prepare("INSERT INTO cef_legislative_referrals (submission_id, legislative_item_id, referral_type, notes, referred_by, referred_at, status) VALUES (?, ?, 'Ordinance Bridge', 'Converted from civic proposal to legislative draft bill.', ?, NOW(), 'Adopted')");
    $refStmt->execute([$submissionId, $newItemId, $userId]);

    // Log history
    cefLogHistory($pdo, $submissionId, 'Converted to Draft Ordinance', $sub['status'], 'In Progress', "Formally converted into Legislative Item: $refNumber ($finalTitle)", true, $userId);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Citizen Proposal successfully converted into {$refNumber}!",
        'legislative_item_id' => $newItemId,
        'reference_number' => $refNumber
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
