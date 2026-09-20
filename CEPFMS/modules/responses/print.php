<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.view');
$r=cefResponseRow(db(),(int)($_GET['id']??0));if(!$r)exit('Response not found.');
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($r['response_reference']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px}.box{border:1px solid #ccc;padding:12px;margin-top:12px;line-height:1.5}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Official Citizen Response</h2><div><?= e($r['response_reference']) ?></div></div><div class="box"><strong>Submission:</strong> <?= e($r['submission_reference'].' · '.$r['submission_title']) ?><br><strong>Response Type:</strong> <?= e($r['response_type']) ?><br><strong>Status:</strong> <?= e($r['status']) ?><br><strong>Approved:</strong> <?= formatDateTime($r['approved_at']) ?><br><strong>Delivered:</strong> <?= formatDateTime($r['delivered_at']) ?><hr><h3><?= e($r['subject']) ?></h3><?= nl2br(e($r['body'])) ?></div></body></html>
