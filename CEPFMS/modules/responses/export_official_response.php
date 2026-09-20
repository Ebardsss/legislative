<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.view');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$r = cefResponseRow($pdo, $id);
if (!$r) {
    exit('Official Response document not found.');
}

$sub = cefSubmissionRow($pdo, (int)$r['submission_id']);

// Public tracking verification URL
$verifyUrl = citizenPortalUrl("track.php?ref=" . urlencode($sub['reference_number'] ?? ''));
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=" . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Official Response - <?= e($r['response_reference']) ?> - City Government of Manila</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @page { size: letter portrait; margin: 20mm; }
    body {
      font-family: 'Times New Roman', Times, serif;
      color: #111827;
      background: #f8fafc;
      margin: 0;
      padding: 30px;
    }
    .sheet {
      background: #ffffff;
      max-width: 800px;
      margin: 0 auto;
      padding: 50px 60px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border-radius: 4px;
      position: relative;
    }
    .letterhead {
      text-align: center;
      border-bottom: 2px solid #0F2137;
      padding-bottom: 20px;
      margin-bottom: 30px;
      position: relative;
    }
    .seal-logo {
      width: 80px;
      height: 80px;
      object-fit: contain;
      position: absolute;
      left: 10px;
      top: 0;
    }
    .qr-verify {
      position: absolute;
      right: 10px;
      top: 0;
      text-align: center;
    }
    .qr-verify img {
      width: 75px;
      height: 75px;
      border: 1px solid #ddd;
      padding: 2px;
      border-radius: 4px;
    }
    .header-text h4 { margin: 0; font-size: 13px; font-weight: normal; text-transform: uppercase; letter-spacing: 1px; }
    .header-text h2 { margin: 4px 0; font-size: 19px; font-weight: bold; color: #0F2137; }
    .header-text h3 { margin: 2px 0; font-size: 15px; font-weight: bold; color: #a97900; }
    .header-text p { margin: 0; font-size: 11px; color: #64748b; font-family: Arial, sans-serif; }

    .doc-meta {
      display: flex;
      justify-content: space-between;
      margin-bottom: 25px;
      font-size: 14px;
      font-family: Arial, sans-serif;
    }
    .doc-meta .ref { font-weight: bold; color: #0F2137; }

    .recipient-block {
      margin-bottom: 25px;
      font-size: 15px;
      line-height: 1.4;
    }

    .subject-line {
      font-weight: bold;
      font-size: 16px;
      text-decoration: underline;
      margin-bottom: 25px;
    }

    .letter-body {
      font-size: 15px;
      line-height: 1.7;
      text-align: justify;
      margin-bottom: 45px;
      min-height: 250px;
    }

    .sign-section {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 50px;
      font-family: Arial, sans-serif;
    }
    .signature-box {
      width: 280px;
      text-align: left;
    }
    .signature-line {
      border-bottom: 1px solid #111827;
      margin-bottom: 6px;
      height: 50px;
    }

    .footer-watermark {
      margin-top: 50px;
      padding-top: 15px;
      border-top: 1px solid #e2e8f0;
      font-size: 11px;
      color: #94a3b8;
      display: flex;
      justify-content: space-between;
      font-family: Arial, sans-serif;
    }

    .no-print-bar {
      max-width: 800px;
      margin: 0 auto 20px auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: Arial, sans-serif;
    }
    .btn-print {
      background: #0F2137;
      color: #ffffff;
      border: none;
      padding: 8px 18px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }

    @media print {
      body { background: #ffffff; padding: 0; }
      .sheet { box-shadow: none; padding: 0; }
      .no-print-bar { display: none; }
    }
  </style>
</head>
<body>

<div class="no-print-bar">
  <a href="view.php?id=<?= (int)$r['id'] ?>" style="color: #64748b; text-decoration: none;">&larr; Back to Response Desk</a>
  <button class="btn-print" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print Official Document</button>
</div>

<div class="sheet">
  <div class="letterhead">
    <img src="<?= e(appUrl('assets/images/manila.png')) ?>" class="seal-logo" alt="City Seal">
    <div class="header-text">
      <h4>Republic of the Philippines</h4>
      <h2>City Government of Manila</h2>
      <h3>Sangguniang Panlungsod · City Council</h3>
      <p>Citizen Engagement &amp; Public Feedback Monitoring System (CEPFMS)</p>
      <p>City Hall Complex, Padre Burgos Ave, Ermita, Manila, 1000 Metro Manila</p>
    </div>
    <div class="qr-verify">
      <img src="<?= e($qrUrl) ?>" alt="Verification QR">
      <div style="font-size: 8px; font-family: Arial; margin-top: 2px; color: #64748b;">Scan to Verify</div>
    </div>
  </div>

  <div class="doc-meta">
    <div><strong>Date:</strong> <?= date('F d, Y', strtotime($r['delivered_at'] ?? $r['created_at'])) ?></div>
    <div class="ref">Official Dispatch Ref: <?= e($r['response_reference']) ?></div>
  </div>

  <div class="recipient-block">
    <strong>To:</strong> <?= e($sub['anonymous_flag'] ? 'Concerned Citizen / Stakeholder' : ($sub['citizen_name'] ?: 'Valued Citizen')) ?><br>
    <?php if(!empty($sub['barangay']) || !empty($sub['district'])): ?>
      <span><?= e($sub['barangay'] ?: '') ?><?= !empty($sub['district']) ? ' · ' . e($sub['district']) : '' ?>, City of Manila</span><br>
    <?php endif; ?>
    <small style="color: #64748b; font-family: Arial;">Civic Record Tracking ID: <strong><?= e($sub['reference_number']) ?></strong></small>
  </div>

  <div class="subject-line">
    SUBJECT: <?= e($r['subject']) ?>
  </div>

  <div class="letter-body">
    <?= nl2br(e($r['body'])) ?>
  </div>

  <div class="sign-section">
    <div>
      <small class="text-muted" style="font-size: 11px; display: block; margin-bottom: 4px;">Verified by CEPFMS Registry:</small>
      <div style="font-size: 12px; font-family: monospace; color: #0F2137; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; display: inline-block;">
        CERTIFIED OFFICIAL LGU DISPATCH
      </div>
    </div>

    <div class="signature-box">
      <div class="signature-line"></div>
      <strong style="font-size: 14px; text-transform: uppercase;"><?= e($r['approved_name'] ?? 'Authorized Sangguniang Official') ?></strong><br>
      <span style="font-size: 12px; color: #64748b;"><?= e($r['signoff_role'] ?? 'City Council Public Assistance & Legislative Oversight') ?></span>
    </div>
  </div>

  <div class="footer-watermark">
    <div>City of Manila · Integrity, Transparency, and Citizen-First Service</div>
    <div>Document generated on <?= date('Y-m-d H:i:s') ?> · Security Hash: <?= substr(md5($r['response_reference']), 0, 12) ?></div>
  </div>
</div>

</body>
</html>
