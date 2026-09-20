<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';requireCefPermission('cepfms.complaints.view');
$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);if(!$s||$s['submission_type']!=='Complaint')exit('Complaint not found.');$c=cefComplaintRow($pdo,$id)?:[];
$beforePhotoPath = $c['before_photo_path'] ?? '';
if (empty($beforePhotoPath)) {
    $docsQ = $pdo->prepare("SELECT file_path FROM cef_submission_documents WHERE submission_id = :id AND (mime_type LIKE 'image/%' OR document_type = 'Incident Evidence Photo') LIMIT 1");
    $docsQ->execute([':id' => $id]);
    $beforePhotoPath = $docsQ->fetchColumn() ?: '';
}
$beforePhotoUrl = cefEvidenceUrl($beforePhotoPath);
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($s['reference_number']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px}.box{border:1px solid #ccc;padding:10px;margin-top:12px}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Complaint / Issue Record</h2><div><?= e($s['reference_number']) ?></div></div><h3><?= e($s['title']) ?></h3><div class="box"><strong>Affected Service:</strong> <?= e($c['affected_service']??'—') ?><br><strong>Urgency:</strong> <?= e($c['urgency_level']??'Normal') ?><br><strong>Response Target:</strong> <?= formatDateTime($c['response_target_at']??null) ?><br><strong>Resolution Target:</strong> <?= formatDateTime($c['resolution_target_at']??null) ?><br><strong>Status:</strong> <?= e($s['status']) ?><br><br><strong>Citizen Details</strong><br><?= nl2br(e($s['details'])) ?><?php if(!empty($beforePhotoUrl)): ?><br><br><strong>Citizen Incident Photo Evidence</strong><br><div style="margin-top:8px; border:1px solid #ddd; padding:6px; display:inline-block; border-radius:4px;"><img src="<?= e($beforePhotoUrl) ?>" style="max-height:280px; max-width:100%; object-fit:contain;" alt="Evidence Photo"></div><?php endif; ?><?php if(!empty($c['resolution_summary'])): ?><br><br><strong>Resolution Summary</strong><br><?= nl2br(e($c['resolution_summary'])) ?><?php endif; ?></div></body></html>
