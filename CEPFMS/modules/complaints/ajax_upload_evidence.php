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
$photoType = clean($_POST['photo_type'] ?? 'after'); // 'before' or 'after'

if ($submissionId <= 0 || !in_array($photoType, ['before', 'after'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid evidence upload request.']);
    exit;
}

if (!isset($_FILES['evidence_photo']) || $_FILES['evidence_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid image file.']);
    exit;
}

$file = $_FILES['evidence_photo'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are permitted.']);
    exit;
}

$uploadDir = UPLOAD_DIR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fileName = 'evidence_' . $photoType . '_' . $submissionId . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded photo.']);
    exit;
}

$pdo = db();
$userId = currentUserId();

$column = ($photoType === 'before') ? 'before_photo_path' : 'after_photo_path';
$pdo->prepare("UPDATE cef_complaints SET $column = ?, updated_at = NOW() WHERE submission_id = ?")->execute([$fileName, $submissionId]);

// Also record as a document in cef_submission_documents
$docType = ($photoType === 'before') ? 'Before Resolution Photo' : 'After Resolution Proof';
$pdo->prepare("INSERT INTO cef_submission_documents (submission_id, file_name, file_path, file_size, mime_type, document_type, visibility, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, 'Public', ?, NOW())")
    ->execute([$submissionId, $file['name'], $fileName, $file['size'], $file['type'], $docType, $userId]);

cefLogHistory($pdo, $submissionId, 'Resolution Proof Uploaded', null, null, "Uploaded $docType: $fileName", true, $userId);

echo json_encode([
    'success' => true,
    'message' => ucfirst($photoType) . ' evidence photo uploaded successfully.',
    'file_path' => UPLOAD_URL . $fileName
]);
