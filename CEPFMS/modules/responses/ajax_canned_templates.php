<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.manage');

header('Content-Type: application/json');

$pdo = db();
$templates = $pdo->query("SELECT id, category, title, content FROM cef_canned_templates WHERE template_type = 'Response' ORDER BY category ASC, title ASC")->fetchAll();

echo json_encode(['success' => true, 'templates' => $templates]);
