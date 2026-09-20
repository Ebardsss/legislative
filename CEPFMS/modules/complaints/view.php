<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.complaints.view');

$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);
if(!$s||$s['submission_type']!=='Complaint'){setFlash('warning','Complaint not found.');redirect(appUrl('modules/complaints/index.php'));}

$c=cefComplaintRow($pdo,$id)?:[];
$a=$pdo->prepare("SELECT a.*,o.name office_name,cm.name committee_name,u.full_name assigned_name FROM cef_assignments a LEFT JOIN offices o ON o.id=a.office_id LEFT JOIN committees cm ON cm.id=a.committee_id LEFT JOIN users u ON u.id=a.assigned_user_id WHERE a.submission_id=:id AND a.status<>'Cancelled' ORDER BY a.id DESC");$a->execute([':id'=>$id]);$assignments=$a->fetchAll();
$primary=$assignments[0]??null;
$updatesQ=$pdo->prepare("SELECT x.*,u.full_name created_name FROM cef_case_updates x LEFT JOIN users u ON u.id=x.created_by WHERE x.submission_id=:id ORDER BY x.created_at DESC,x.id DESC");$updatesQ->execute([':id'=>$id]);$updates=$updatesQ->fetchAll();
$escQ=$pdo->prepare("SELECT e.*,u.full_name created_name,eu.full_name target_user,o.name target_office FROM cef_escalations e LEFT JOIN users u ON u.id=e.created_by LEFT JOIN users eu ON eu.id=e.escalated_to_user_id LEFT JOIN offices o ON o.id=e.escalated_to_office_id WHERE e.submission_id=:id ORDER BY e.created_at DESC,e.id DESC");$escQ->execute([':id'=>$id]);$escalations=$escQ->fetchAll();
$docsQ=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM cef_submission_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.submission_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$docsQ->execute([':id'=>$id]);$docs=$docsQ->fetchAll();
$histQ=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM cef_submission_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.submission_id=:id ORDER BY h.created_at DESC,h.id DESC");$histQ->execute([':id'=>$id]);$history=$histQ->fetchAll();

$aiQ=$pdo->prepare('SELECT * FROM cef_ai_analysis WHERE submission_id=:id ORDER BY id DESC LIMIT 1');
$aiQ->execute([':id'=>$id]);$latestAi=$aiQ->fetch()?:null;
$latestAiData=$latestAi&&!empty($latestAi['result_json'])?json_decode((string)$latestAi['result_json'],true):null;

$users=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' AND deleted_at IS NULL ORDER BY full_name")->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();

$slaOver=!in_array($s['status'],['Resolved','Closed','Rejected','Withdrawn'],true)&&!empty($c['resolution_target_at'])&&strtotime($c['resolution_target_at'])<time();

// Smart fallback for before photo: check $c['before_photo_path'], or fallback to any image uploaded in $docs
$beforePhotoPath = $c['before_photo_path'] ?? '';
$citizenPhotoDocs = [];
if (!empty($docs)) {
    foreach ($docs as $d) {
        $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $citizenPhotoDocs[] = $d;
            if (empty($beforePhotoPath)) {
                $beforePhotoPath = $d['file_path'];
            }
        }
    }
}
$beforePhotoUrl = cefEvidenceUrl($beforePhotoPath);
$afterPhotoPath = $c['after_photo_path'] ?? '';
$afterPhotoUrl = !empty($afterPhotoPath) ? cefEvidenceUrl($afterPhotoPath) : '';

$pageTitle=$s['reference_number'];$activeMenu='complaints';$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Complaint Registry</a><div class="cef-eyebrow mt-2"><?= e($s['reference_number']) ?> · Complaint / Issue</div><h1><?= e($s['title']) ?></h1><p><?= e($c['affected_service']??'General issue') ?> · <?= e($c['urgency_level']??$s['priority_level']) ?> urgency<?php if(!empty($beforePhotoPath)): ?><span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2"><i class="bi bi-camera-fill me-1"></i>Photo Evidence Attached</span><?php endif; ?></p></div><div class="d-flex gap-2"><span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<?php
$now = time();
$targetTime = !empty($c['resolution_target_at']) ? strtotime($c['resolution_target_at']) : 0;
$diffSeconds = $targetTime - $now;
$isOverdue = $targetTime > 0 && $diffSeconds < 0 && !in_array($s['status'], ['Resolved', 'Closed', 'Rejected', 'Withdrawn'], true);
$diffHours = abs(round($diffSeconds / 3600));
$diffDays = floor($diffHours / 24);
$remHours = $diffHours % 24;
?>

<!-- ARTA RA 11032 STATUTORY COMPLIANCE BAR -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px; overflow: hidden; border-left: 5px solid <?= $isOverdue ? '#dc2626' : ($diffHours < 24 ? '#f59e0b' : '#16a34a') ?> !important;">
  <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded-circle d-flex align-items-center justify-content-center <?= $isOverdue ? 'bg-danger text-white' : 'bg-success text-white' ?>" style="width: 44px; height: 44px; font-size: 1.25rem;">
        <i class="bi <?= $isOverdue ? 'bi-alarm-fill' : 'bi-shield-check' ?>"></i>
      </div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <strong class="text-dark" style="font-size: 0.95rem;">ARTA RA 11032 Statutory Compliance Clock</strong>
          <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-success' ?>" style="font-size: 0.72rem;">
            <?= $isOverdue ? 'BREACHED SLA' : 'ON TRACK' ?>
          </span>
          <span class="badge bg-light text-secondary border" style="font-size: 0.72rem;">
            Tier: <?= e($c['arta_tier'] ?: 'Simple (3 Days)') ?>
          </span>
        </div>
        <small class="text-muted">
          <?php if ($isOverdue): ?>
            <span class="text-danger fw-bold">Overdue by <?= $diffDays > 0 ? "{$diffDays} day(s) and " : "" ?><?= $remHours ?> hour(s)</span> · Deadline was <?= formatDateTime($c['resolution_target_at']) ?>
          <?php elseif($targetTime > 0): ?>
            <span class="text-success fw-bold"><?= $diffDays > 0 ? "{$diffDays} day(s) " : "" ?><?= $remHours ?> hour(s) remaining</span> · Statutory Target: <?= formatDateTime($c['resolution_target_at']) ?>
          <?php else: ?>
            No target date configured.
          <?php endif; ?>
        </small>
      </div>
    </div>
    <?php if (cefHasPermission('cepfms.complaints.manage')): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#artaModal">
        <i class="bi bi-clock-history me-1"></i> Adjust ARTA Tier
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3"><div class="col-xl-8">
<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Complaint Details</span><?php if(!empty($beforePhotoPath)): ?><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size:0.75rem;"><i class="bi bi-camera-fill me-1"></i>Evidence Photo Attached</span><?php endif; ?></div><div class="card-body"><div class="cef-grid"><div><small>Citizen</small><strong><?= $s['anonymous_flag']?'Anonymous':e($s['citizen_name']?:'Not provided') ?></strong></div><div><small>Incident</small><strong><?= formatDateTime($c['incident_datetime']??null) ?></strong></div><div><small>Affected Service</small><strong><?= e($c['affected_service']??'—') ?></strong></div><div><small>Acknowledgement Target</small><strong><?= formatDateTime($c['acknowledgement_target_at']??null) ?></strong></div><div><small>Response Target</small><strong><?= formatDateTime($c['response_target_at']??null) ?></strong></div><div><small>Resolution Target</small><strong><?= formatDateTime($c['resolution_target_at']??null) ?></strong></div><div><small>Incident Address</small><strong class="text-danger"><i class="bi bi-geo-alt-fill me-1"></i><?= e($s['location_text'] ?: 'Not specified') ?><?= !empty($s['barangay']) ? ' · Brgy '.e($s['barangay']) : '' ?><?= !empty($s['district']) ? ' · '.e($s['district']) : '' ?></strong></div><div><small>Urgency</small><strong><?= e($c['urgency_level']??$s['priority_level']) ?></strong></div></div><div class="cef-alert mt-3"><strong>Citizen Details</strong><br><?= nl2br(e($s['details'])) ?></div><?php if(!empty($beforePhotoPath)): ?>
<!-- CITIZEN PHOTO EVIDENCE - IMMEDIATELY OPEN & VISIBLE -->
<div class="mt-3 p-3 rounded-3 border bg-light shadow-sm" style="border-left: 4px solid #0d6efd !important;">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 pb-2 border-bottom">
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-primary rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
        <i class="bi bi-camera-fill text-white fs-6"></i>
      </span>
      <div>
        <strong class="text-dark d-block" style="font-size: 0.95rem;">Citizen Incident Photo Evidence</strong>
        <small class="text-muted">Uploaded directly by citizen during filing · Visible incident proof</small>
      </div>
    </div>
    <div class="d-flex align-items-center gap-1">
      <button type="button" class="btn btn-sm btn-primary" onclick="openPhotoModal('<?= e($beforePhotoUrl) ?>', 'Citizen Incident Evidence (<?= e($s['reference_number']) ?>)')">
        <i class="bi bi-arrows-fullscreen me-1"></i> Full Size / Zoom
      </button>
      <a href="<?= e($beforePhotoUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Open in new tab">
        <i class="bi bi-box-arrow-up-right"></i>
      </a>
      <a href="<?= e($beforePhotoUrl) ?>" download class="btn btn-sm btn-outline-secondary" title="Download photo">
        <i class="bi bi-download"></i>
      </a>
    </div>
  </div>

  <div class="text-center p-3 rounded-2" style="min-height: 240px; max-height: 480px; background-color: #0f172a !important; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
    <a href="javascript:void(0)" onclick="openPhotoModal('<?= e($beforePhotoUrl) ?>', 'Citizen Incident Evidence (<?= e($s['reference_number']) ?>)')" title="Click to view full screen">
      <img src="<?= e($beforePhotoUrl) ?>" 
           alt="Citizen Evidence Photo" 
           class="img-fluid rounded" 
           style="max-height: 440px; max-width: 100%; width: auto; height: auto; object-fit: contain; cursor: zoom-in; box-shadow: 0 4px 20px rgba(0,0,0,0.5); transition: transform 0.2s;"
           onmouseover="this.style.transform='scale(1.01)'"
           onmouseout="this.style.transform='scale(1)'">
    </a>
    <div class="position-absolute bottom-0 end-0 p-2 m-2 bg-dark bg-opacity-75 rounded text-white small" style="backdrop-filter: blur(4px); font-size: 0.75rem; pointer-events: none;">
      <i class="bi bi-zoom-in me-1"></i> Click photo to enlarge
    </div>
  </div>

  <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 px-1 small text-muted">
    <span><i class="bi bi-check-circle-fill text-success me-1"></i> Verified uploaded asset: <code><?= e(basename($beforePhotoPath)) ?></code></span>
    <span><i class="bi bi-clock-history me-1"></i> Logged with complaint on <?= formatDateTime($c['created_at'] ?? $s['created_at']) ?></span>
  </div>
</div>
<?php endif; ?><?php if(!empty($c['resolution_summary'])): ?><div class="alert alert-success mt-3 mb-0"><strong>Resolution Summary</strong><br><?= nl2br(e($c['resolution_summary'])) ?></div><?php endif; ?></div></div>

<!-- BEFORE & AFTER RESOLUTION PROOF PROTOCOL -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-images text-primary me-1"></i> <strong>Before &amp; After Resolution Proof Protocol</strong></span>
    <?php if (!empty($beforePhotoPath) && !empty($afterPhotoPath)): ?>
      <span class="badge bg-success-subtle text-success"><i class="bi bi-check2-all me-1"></i> Full Verification Proof Attached</span>
    <?php elseif(!empty($beforePhotoPath)): ?>
      <span class="badge bg-info-subtle text-info"><i class="bi bi-camera me-1"></i> Incident Photo Attached</span>
    <?php else: ?>
      <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i> Evidence Incomplete</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-3">
    <div class="row g-3">
      <!-- Before Photo -->
      <div class="col-md-6 text-center border-end">
        <div class="small fw-bold text-secondary mb-2"><i class="bi bi-camera me-1"></i> BEFORE RESOLUTION (Report State)</div>
        <?php if (!empty($beforePhotoPath)): ?>
          <div class="p-2 rounded bg-dark d-flex align-items-center justify-content-center mb-2" style="background-color: #0f172a !important; min-height: 220px;">
            <a href="javascript:void(0)" onclick="openPhotoModal('<?= e($beforePhotoUrl) ?>', 'BEFORE RESOLUTION - Incident Report State')" title="Click to view full screen">
              <img src="<?= e($beforePhotoUrl) ?>" class="img-fluid rounded shadow-sm" style="max-height: 240px; max-width: 100%; object-fit: contain; cursor: zoom-in;" alt="Before Evidence">
            </a>
          </div>
          <div class="d-flex justify-content-center align-items-center gap-2">
            <small class="text-muted"><i class="bi bi-check-circle text-success me-1"></i> Citizen / Initial incident photo</small>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="openPhotoModal('<?= e($beforePhotoUrl) ?>', 'BEFORE RESOLUTION - Incident Report State')">
              <i class="bi bi-arrows-fullscreen"></i>
            </button>
          </div>
        <?php else: ?>
          <div class="p-4 bg-light border rounded text-muted small mb-2" style="min-height: 220px; display: flex; align-items: center; justify-content: center;">
            <div><i class="bi bi-camera text-secondary fs-3 d-block mb-1"></i>No before photo attached yet.</div>
          </div>
        <?php endif; ?>
        <?php if (cefHasPermission('cepfms.complaints.manage')): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-2 btn-upload-proof" data-type="before">
            <i class="bi bi-upload me-1"></i> <?= empty($beforePhotoPath) ? 'Upload Before Photo' : 'Replace Photo' ?>
          </button>
        <?php endif; ?>
      </div>

      <!-- After Photo -->
      <div class="col-md-6 text-center">
        <div class="small fw-bold text-secondary mb-2"><i class="bi bi-patch-check-fill text-success me-1"></i> AFTER RESOLUTION (LGU Action Proof)</div>
        <?php if (!empty($afterPhotoPath)): ?>
          <div class="p-2 rounded bg-dark d-flex align-items-center justify-content-center mb-2" style="background-color: #0f172a !important; min-height: 220px;">
            <a href="javascript:void(0)" onclick="openPhotoModal('<?= e($afterPhotoUrl) ?>', 'AFTER RESOLUTION - LGU Rectification Proof')" title="Click to view full screen">
              <img src="<?= e($afterPhotoUrl) ?>" class="img-fluid rounded border shadow-sm" style="max-height: 240px; max-width: 100%; object-fit: contain; cursor: zoom-in;" alt="After Evidence">
            </a>
          </div>
          <div class="d-flex justify-content-center align-items-center gap-2">
            <small class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Rectified / Completed Action</small>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="openPhotoModal('<?= e($afterPhotoUrl) ?>', 'AFTER RESOLUTION - LGU Rectification Proof')">
              <i class="bi bi-arrows-fullscreen"></i>
            </button>
          </div>
        <?php else: ?>
          <div class="p-4 bg-light border rounded text-muted small mb-2" style="min-height: 220px; display: flex; align-items: center; justify-content: center;">
            <div><i class="bi bi-shield-check text-secondary fs-3 d-block mb-1"></i>No resolution proof photo uploaded yet.</div>
          </div>
        <?php endif; ?>
        <?php if (cefHasPermission('cepfms.complaints.manage')): ?>
          <button type="button" class="btn btn-outline-success btn-sm mt-2 btn-upload-proof" data-type="after">
            <i class="bi bi-upload me-1"></i> <?= empty($afterPhotoPath) ? 'Upload Resolution Proof' : 'Replace Photo' ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- GEO-LOCATION & MAP PINNING -->
<div class="card cef-card mb-3 border-0 shadow-sm" style="border-radius: 12px;">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> <strong>Incident Location &amp; Dispatch Pin</strong></span>
    <?php 
      $lat = $c['latitude'] ?? '14.599512';
      $lng = $c['longitude'] ?? '120.984222';
      $mapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
    ?>
    <a href="<?= e($mapsUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-map me-1"></i> Open Google Maps Navigation
    </a>
  </div>
  <div class="card-body p-3">
    <div class="row g-2 align-items-center">
      <div class="col-md-7">
        <div><strong>Location / Landmark:</strong> <?= e($s['location_text'] ?: 'City of Manila') ?></div>
        <div class="small text-muted">Barangay: <?= e($s['barangay'] ?: 'Unspecified') ?> · District: <?= e($s['district'] ?: 'Unspecified') ?></div>
        <div class="font-monospace small text-secondary mt-1">GPS Coordinates: <?= e((string)$lat) ?>, <?= e((string)$lng) ?></div>
      </div>
      <div class="col-md-5 text-end">
        <span class="badge bg-light text-dark border p-2 text-start w-100">
          <i class="bi bi-info-circle text-primary me-1"></i> Responders may tap the button above to launch turn-by-turn navigation on mobile devices.
        </span>
      </div>
    </div>
  </div>
</div>

<div class="card cef-card mb-3" id="aiComplaintCard" style="border-left: 4px solid #f59e0b;">
  <div class="card-header d-flex justify-content-between align-items-center bg-light">
    <span><i class="bi bi-robot text-warning me-1"></i> <strong>Ollama AI Complaint Assistant</strong></span>
    <button type="button" class="btn btn-sm btn-outline-warning" id="btnAiAnalyzeComplaint">
      <i class="bi bi-stars"></i> <span id="aiComplaintBtnText">Analyze Complaint</span>
    </button>
  </div>
  <div class="card-body" id="aiComplaintResultArea">
    <?php if ($latestAiData): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-<?= in_array($latestAiData['urgency_level'] ?? '', ['High', 'Urgent']) ? 'danger' : 'warning text-dark' ?>">
          <i class="bi bi-alarm me-1"></i>Urgency: <?= e($latestAiData['urgency_level'] ?? 'Normal') ?> (<?= (int)($latestAiData['urgency_score'] ?? 0) ?>%)
        </span>
        <span class="badge bg-<?= ($latestAiData['sentiment'] ?? '') === 'Negative' ? 'danger' : (($latestAiData['sentiment'] ?? '') === 'Positive' ? 'success' : 'secondary') ?>">
          Sentiment: <?= e($latestAiData['sentiment'] ?? 'Neutral') ?>
        </span>
        <span class="badge bg-<?= ($latestAiData['content_safety'] ?? '') === 'Safe' ? 'success' : 'danger' ?>">
          Safety: <?= e($latestAiData['content_safety'] ?? 'Safe') ?>
        </span>
        <span class="badge bg-info text-dark">
          Actionability: <?= e($latestAiData['actionability'] ?? 'Complete') ?>
        </span>
      </div>

      <div class="mb-2"><strong>Executive Summary:</strong> <?= e($latestAiData['executive_summary'] ?? '') ?></div>

      <div class="row g-2 mb-2">
        <div class="col-md-4">
          <small class="text-muted d-block">Suggested Category</small>
          <strong><?= e($latestAiData['recommended_category'] ?? 'General Complaints') ?></strong>
        </div>
        <div class="col-md-4">
          <small class="text-muted d-block">Recommended Office</small>
          <strong><?= e($latestAiData['recommended_office'] ?? 'Public Assistance Desk') ?></strong>
        </div>
        <div class="col-md-4">
          <small class="text-muted d-block">Council Committee</small>
          <strong><?= e($latestAiData['recommended_committee'] ?? 'Committee on Public Accountability') ?></strong>
        </div>
      </div>

      <?php if (!empty($latestAiData['action_recommendation'])): ?>
        <div class="alert alert-light border py-2 px-3 small mb-2">
          <strong>Recommended Action Plan:</strong> <?= e($latestAiData['action_recommendation']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($latestAiData['risk_keywords']) || !empty($latestAiData['key_topics'])): ?>
        <div class="small mb-2">
          <span class="text-muted me-1">Keywords:</span>
          <?php foreach(($latestAiData['risk_keywords'] ?? []) as $kw): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1"><?= e($kw) ?></span>
          <?php endforeach; ?>
          <?php foreach(($latestAiData['key_topics'] ?? []) as $kw): ?>
            <span class="badge bg-light text-dark border me-1"><?= e($kw) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (cefHasPermission('cepfms.complaints.manage')): ?>
        <div class="d-flex gap-2 mt-3 pt-2 border-top">
          <button type="button" class="btn btn-sm btn-primary" id="btnApplyAiRouting"
                  data-cat="<?= e($latestAiData['recommended_category'] ?? '') ?>"
                  data-urgency="<?= e($latestAiData['urgency_level'] ?? 'Normal') ?>"
                  data-office="<?= e($latestAiData['recommended_office'] ?? '') ?>">
            <i class="bi bi-check2-circle me-1"></i> Apply AI Routing & Recommendations
          </button>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="text-muted small py-2">
        <i class="bi bi-info-circle me-1"></i> No AI triage analysis generated yet. Click <strong>Analyze Complaint</strong> to run local Ollama hazard detection, urgency scoring, and smart department routing.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card cef-card mb-3"><div class="card-header">Assignments</div><div class="card-body d-grid gap-2"><?php if(!$assignments): ?><div class="text-muted small">No active assignment.</div><?php endif; ?><?php foreach($assignments as $x): ?><div class="cef-person"><div><strong><?= e($x['assigned_name']?:$x['office_name']?:$x['committee_name']?:'Assignment') ?></strong><small><?= e($x['assignment_role'].' · '.$x['status']) ?><?= $x['due_at']?' · Due '.formatDateTime($x['due_at']):'' ?><?= $x['notes']?'<br>'.e($x['notes']):'' ?></small></div></div><?php endforeach; ?></div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between"><span>Case Updates</span><?php if(cefHasPermission('cepfms.complaints.manage')): ?><button class="btn btn-sm btn-primary" id="btnUpdate">Add Update</button><?php endif; ?></div><div class="card-body cef-timeline"><?php if(!$updates): ?><div class="text-muted small">No case updates yet.</div><?php endif; ?><?php foreach($updates as $x): ?><div><strong><?= e($x['update_type']) ?> · <?= e($x['created_name']?:'System') ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= nl2br(e($x['update_text'])) ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div>

<div class="card cef-card mb-3"><div class="card-header">Escalations</div><div class="card-body d-grid gap-2"><?php if(!$escalations): ?><div class="text-muted small">No escalation recorded.</div><?php endif; ?><?php foreach($escalations as $x): ?><div class="cef-risk"><strong><?= e($x['escalation_level'].' · '.$x['status']) ?></strong><small><?= e($x['reason']) ?><br>Target: <?= e($x['target_user']?:$x['target_office']?:'Management attention') ?> · <?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Documents / Evidence</span><span class="badge bg-secondary"><?= count($docs) ?> Attachment<?= count($docs) === 1 ? '' : 's' ?></span></div><div class="card-body"><div class="list-group mb-3"><?php if(!$docs): ?><div class="list-group-item text-muted">No supporting documents.</div><?php endif; ?><?php foreach($docs as $doc): ?>
<?php 
  $docExt = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
  $isImg = in_array($docExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
  $docUrl = cefEvidenceUrl($doc['file_path']);
?>
<div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3">
  <div class="d-flex align-items-center gap-3 overflow-hidden">
    <?php if($isImg): ?>
      <a href="javascript:void(0)" onclick="openPhotoModal('<?= e($docUrl) ?>', '<?= e(addslashes($doc['file_name'])) ?>')" title="Click to enlarge">
        <img src="<?= e($docUrl) ?>" alt="Evidence" style="width: 54px; height: 54px; object-fit: cover; border-radius: 6px; cursor: zoom-in;" class="border shadow-sm">
      </a>
    <?php else: ?>
      <div class="rounded bg-light border d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; font-size: 1.5rem; color: #6c757d;">
        <i class="bi bi-file-earmark-text"></i>
      </div>
    <?php endif; ?>
    <div>
      <div class="d-flex align-items-center gap-2">
        <a href="<?= e($docUrl) ?>" target="_blank" class="text-decoration-none fw-bold text-dark d-block text-truncate">
          <?= e($doc['file_name']) ?>
        </a>
        <?php if ($doc['document_type'] === 'Incident Evidence Photo'): ?>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">Citizen Evidence Photo</span>
        <?php endif; ?>
      </div>
      <div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if($isImg): ?>
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPhotoModal('<?= e($docUrl) ?>', '<?= e(addslashes($doc['file_name'])) ?>')"><i class="bi bi-eye"></i> View</button>
    <?php else: ?>
      <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e($docUrl) ?>"><i class="bi bi-eye"></i> View</a>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e($docUrl) ?>" download title="Download"><i class="bi bi-download"></i></a>
    <?php if(cefHasPermission('cepfms.complaints.manage')): ?>
      <button type="button" class="btn btn-outline-secondary btn-sm doc-visibility-btn" data-id="<?= (int)$doc['id'] ?>" data-scope="submission" data-next="<?= $doc['visibility']==='Public'?'Internal':'Public' ?>"><?= $doc['visibility']==='Public'?'Make Internal':'Make Public' ?></button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?></div><?php if(cefHasPermission('cepfms.complaints.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><div class="col-md-4"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Complaint Evidence"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option value="Internal">Internal Only</option><option value="Public">Citizen Visible</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
<?php $ticketChatPermission='cepfms.complaints.manage'; include __DIR__.'/../../includes/cepfms_ticket_chat.php'; ?>
</div>

<div class="col-xl-4">
<?php if(cefHasPermission('cepfms.complaints.manage')): ?>
<div class="card cef-card mb-3"><div class="card-header">Primary Assignment</div><div class="card-body"><form id="assignmentForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><label class="form-label">Office</label><select class="form-select mb-2" name="office_id"><option value="">None</option><?php foreach($offices as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $primary&&(int)$primary['office_id']===(int)$x['id']?'selected':'' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select><label class="form-label">Committee</label><select class="form-select mb-2" name="committee_id"><option value="">None</option><?php foreach($committees as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $primary&&(int)$primary['committee_id']===(int)$x['id']?'selected':'' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select><label class="form-label">Staff User</label><select class="form-select mb-2" name="assigned_user_id"><option value="">None</option><?php foreach($users as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $primary&&(int)$primary['assigned_user_id']===(int)$x['id']?'selected':'' ?>><?= e($x['full_name']) ?></option><?php endforeach; ?></select><label class="form-label">Action Due</label><input type="datetime-local" class="form-control mb-2" name="due_at" value="<?= $primary&&!empty($primary['due_at'])?e(date('Y-m-d\TH:i',strtotime($primary['due_at']))):'' ?>"><label class="form-label">Notes</label><textarea class="form-control mb-2" name="notes" rows="2"><?= e($primary['notes']??'') ?></textarea><button class="btn btn-primary w-100">Save Assignment</button></form></div></div>

<div class="card cef-card mb-3"><div class="card-header">Complaint Workflow</div><div class="card-body d-grid gap-2">
<?php if(in_array($s['status'],['Submitted','Under Moderation'],true)): ?><a class="btn btn-outline-primary" href="<?= e(appUrl('modules/moderation/view.php?id='.$id)) ?>">Open Moderation Review</a><?php endif; ?>
<?php if(in_array($s['status'],['Validated','Assigned'],true)): ?><button class="btn btn-primary complaint-action" data-action="start">Start Action</button><?php endif; ?>
<?php if(in_array($s['status'],['Assigned','In Progress'],true)): ?><button class="btn btn-outline-warning complaint-action" data-action="await_citizen">Request Citizen Information</button><?php endif; ?>
<?php if($s['status']==='Awaiting Citizen'): ?><button class="btn btn-primary complaint-action" data-action="resume">Resume Action</button><?php endif; ?>
<?php if(in_array($s['status'],['Assigned','In Progress','Awaiting Citizen','Responded'],true)): ?><button class="btn btn-success complaint-action" data-action="resolve">Resolve Complaint</button><?php endif; ?>
<?php if($s['status']==='Resolved'): ?><button class="btn btn-dark complaint-action" data-action="close">Close Complaint</button><?php endif; ?>
<?php if(!in_array($s['status'],['Resolved','Closed','Rejected','Withdrawn'],true)): ?><button class="btn btn-danger" id="btnEscalate">Escalate</button><?php endif; ?>
</div></div>
<?php endif; ?>

<div class="card cef-card"><div class="card-header">Complaint History</div><div class="card-body cef-timeline"><?php foreach($history as $x): ?><div><strong><?= e($x['action']) ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?= $x['details']?'<br>'.e($x['details']):'' ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div></div>
</main></div>

<?php if(cefHasPermission('cepfms.complaints.manage')): ?>
<div class="modal fade" id="updateModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 520px;"><div class="modal-content shadow"><form id="caseUpdateForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="assignment_id" value="<?= (int)($primary['id']??0) ?>"><div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-clock-history me-1"></i> Complaint Case Update</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><label class="form-label small fw-bold mb-1">Update Type</label><select class="form-select form-select-sm mb-2" name="update_type"><option>Progress Update</option><option>Inspection / Verification</option><option>Office Action</option><option>Citizen Contact</option><option>Service Coordination</option><option>Resolution Preparation</option></select><div class="d-flex justify-content-between align-items-center mb-1"><label class="form-label small fw-bold mb-0">Update Details</label><button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" id="btnAiDraftUpdate" style="font-size:0.78rem;"><i class="bi bi-stars"></i> AI Draft Update</button></div><textarea class="form-control form-control-sm mb-2" name="update_text" rows="4" style="resize: vertical; min-height: 90px; max-height: 200px;" required></textarea><div class="form-check"><input class="form-check-input" type="checkbox" name="public_visible" value="1" id="publicUpdate" checked><label class="form-check-label small" for="publicUpdate">Show this update in citizen tracking</label></div></div><div class="modal-footer py-2 px-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save Update</button></div></form></div></div></div>

<div class="modal fade" id="escalationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 520px;"><div class="modal-content shadow"><form id="escalationForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="assignment_id" value="<?= (int)($primary['id']??0) ?>"><div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Escalate Complaint</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><label class="form-label small fw-bold mb-1">Level</label><select class="form-select form-select-sm mb-2" name="escalation_level"><option>Attention</option><option>High</option><option>Urgent</option><option>Executive Review</option></select><label class="form-label small fw-bold mb-1">Escalate to User</label><select class="form-select form-select-sm mb-2" name="escalated_to_user_id"><option value="">None</option><?php foreach($users as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['full_name']) ?></option><?php endforeach; ?></select><label class="form-label small fw-bold mb-1">Escalate to Office</label><select class="form-select form-select-sm mb-2" name="escalated_to_office_id"><option value="">None</option><?php foreach($offices as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['name']) ?></option><?php endforeach; ?></select><div class="d-flex justify-content-between align-items-center mb-1"><label class="form-label small fw-bold mb-0">Reason</label><button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" id="btnAiDraftEscalate" style="font-size:0.78rem;"><i class="bi bi-stars"></i> AI Draft Reason</button></div><textarea class="form-control form-control-sm" name="reason" rows="3" style="resize: vertical; min-height: 80px; max-height: 180px;" required></textarea></div><div class="modal-footer py-2 px-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-danger">Record Escalation</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const assignment=document.getElementById('assignmentForm'),update=document.getElementById('caseUpdateForm'),esc=document.getElementById('escalationForm'),doc=document.getElementById('docForm');
 const um=new bootstrap.Modal(document.getElementById('updateModal')),em=new bootstrap.Modal(document.getElementById('escalationModal'));
 document.getElementById('btnUpdate')?.addEventListener('click',()=>um.show());
 document.getElementById('btnEscalate')?.addEventListener('click',()=>em.show());
 assignment.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_assignment.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(assignment)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Assignment Error',r.message,'error');};
 update.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_case_update.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(update)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Update Error',r.message,'error');};
 esc.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_escalate.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(esc)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Escalation Error',r.message,'error');};
 if(doc)doc.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(doc)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
 document.querySelectorAll('.complaint-action').forEach(b=>b.onclick=async function(){let notes='';if(['await_citizen','resolve'].includes(this.dataset.action)){const x=await Swal.fire({title:this.dataset.action==='resolve'?'Resolve complaint?':'Request citizen information?',input:'textarea',inputLabel:'Details / reason',showCancelButton:true,inputValidator:v=>!v?'Details are required':undefined});if(!x.isConfirmed)return;notes=x.value;}else{const x=await Swal.fire({title:this.dataset.action.replace('_',' ')+' complaint?',showCancelButton:true});if(!x.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('submission_id','<?= $id ?>');fd.append('action',this.dataset.action);fd.append('notes',notes);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Complaint Workflow',r.message,'error');});

 document.querySelectorAll('.doc-visibility-btn').forEach(btn=>btn.addEventListener('click',async()=>{
   const fd=new FormData();fd.append('csrf_token',document.querySelector('input[name=csrf_token]').value);fd.append('scope',btn.dataset.scope);fd.append('document_id',btn.dataset.id);fd.append('visibility',btn.dataset.next);
   const x=await fetch('../shared/ajax_document_visibility.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(z=>z.json());
   if(x.success)location.reload();else Swal.fire('Attachment Visibility',x.message,'error');
 }));

 // Ollama AI Complaint Assistant Handlers
 const btnAiComp = document.getElementById('btnAiAnalyzeComplaint');
 if (btnAiComp) {
   btnAiComp.addEventListener('click', async () => {
     btnAiComp.disabled = true;
     const origHtml = btnAiComp.innerHTML;
     btnAiComp.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyzing with Ollama...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', '<?= $id ?>');
       fd.append('action', 'analyze');
       const res = await fetch('ajax_ai_complaint.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Analysis failed. Make sure Ollama is running.', 'error');
         return;
       }
       Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Ollama Analysis Complete', timer: 1500, showConfirmButton: false});
       location.reload();
     } catch (err) {
       Swal.fire('Ollama AI', 'Failed to connect to server: ' + err.message, 'error');
     } finally {
       btnAiComp.disabled = false;
       btnAiComp.innerHTML = origHtml;
     }
   });
 }

 const btnApplyRoute = document.getElementById('btnApplyAiRouting');
 if (btnApplyRoute) {
   btnApplyRoute.addEventListener('click', async () => {
     const cat = btnApplyRoute.dataset.cat;
     const urgency = btnApplyRoute.dataset.urgency;
     const office = btnApplyRoute.dataset.office;
     const conf = await Swal.fire({
       title: 'Apply AI Complaint Routing?',
       text: `Set Urgency to "${urgency}", Category to "${cat}", and Route to "${office}"?`,
       icon: 'question',
       showCancelButton: true,
       confirmButtonText: 'Yes, apply'
     });
     if (!conf.isConfirmed) return;

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('submission_id', '<?= $id ?>');
     fd.append('action', 'apply_routing');
     fd.append('category', cat);
     fd.append('urgency', urgency);
     fd.append('office', office);

     const res = await fetch('ajax_ai_complaint.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     }).then(x => x.json());

     if (res.success) {
       await Swal.fire('Applied', res.message, 'success');
       location.reload();
     } else {
       Swal.fire('Error', res.message, 'error');
     }
   });
 }

 const btnDraftUp = document.getElementById('btnAiDraftUpdate');
 if (btnDraftUp) {
   btnDraftUp.addEventListener('click', async () => {
     const typeSelect = document.querySelector('#caseUpdateForm select[name="update_type"]');
     const updateType = typeSelect ? typeSelect.value : 'Progress Update';
     const txtArea = document.querySelector('#caseUpdateForm textarea[name="update_text"]');
     btnDraftUp.disabled = true;
     const origHtml = btnDraftUp.innerHTML;
     btnDraftUp.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Drafting...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', '<?= $id ?>');
       fd.append('action', 'draft_update');
       fd.append('update_type', updateType);
       const res = await fetch('ajax_ai_complaint.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('AI Update Drafter', res.message || 'Draft generation failed.', 'error');
         return;
       }
       if (txtArea && res.data && res.data.update_text) {
         txtArea.value = res.data.update_text;
         Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Update Draft Generated', timer: 1500, showConfirmButton: false});
       }
     } catch (err) {
       Swal.fire('AI Drafter Error', err.message, 'error');
     } finally {
       btnDraftUp.disabled = false;
       btnDraftUp.innerHTML = origHtml;
     }
   });
 }

 const btnDraftEsc = document.getElementById('btnAiDraftEscalate');
 if (btnDraftEsc) {
   btnDraftEsc.addEventListener('click', async () => {
     const lvlSelect = document.querySelector('#escalationForm select[name="escalation_level"]');
     const level = lvlSelect ? lvlSelect.value : 'High';
     const reasonArea = document.querySelector('#escalationForm textarea[name="reason"]');
     btnDraftEsc.disabled = true;
     const origHtml = btnDraftEsc.innerHTML;
     btnDraftEsc.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Drafting...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', '<?= $id ?>');
       fd.append('action', 'draft_escalation');
       fd.append('escalation_level', level);
       const res = await fetch('ajax_ai_complaint.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('AI Escalation Drafter', res.message || 'Draft generation failed.', 'error');
         return;
       }
       if (reasonArea && res.data && res.data.reason) {
         reasonArea.value = res.data.reason;
         Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Escalation Reason Generated', timer: 1500, showConfirmButton: false});
       }
     } catch (err) {
       Swal.fire('AI Drafter Error', err.message, 'error');
     } finally {
       btnDraftEsc.disabled = false;
       btnDraftEsc.innerHTML = origHtml;
     }
   });
 }

  // ARTA Tier Update Handler
  const artaForm = document.getElementById('artaForm');
  if (artaForm) {
    artaForm.onsubmit = async e => {
      e.preventDefault();
      const res = await fetch('ajax_arta_update.php', { method: 'POST', body: new FormData(artaForm) }).then(z => z.json());
      if (res.success) {
        await Swal.fire('ARTA Tier Updated', res.message, 'success');
        location.reload();
      } else {
        Swal.fire('Error', res.message, 'error');
      }
    };
  }

  // Evidence Proof Upload Trigger & Modal
  document.querySelectorAll('.btn-upload-proof').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.type;
      document.getElementById('evidencePhotoType').value = type;
      document.getElementById('evidenceModalTitle').textContent = type === 'before' ? 'Upload Before Incident Photo' : 'Upload After Resolution Proof Photo';
      const myModal = new bootstrap.Modal(document.getElementById('evidenceModal'));
      myModal.show();
    });
  });

  const evidenceForm = document.getElementById('evidenceForm');
  if (evidenceForm) {
    evidenceForm.onsubmit = async e => {
      e.preventDefault();
      const res = await fetch('ajax_upload_evidence.php', { method: 'POST', body: new FormData(evidenceForm) }).then(z => z.json());
      if (res.success) {
        await Swal.fire('Evidence Uploaded', res.message, 'success');
        location.reload();
      } else {
        Swal.fire('Upload Error', res.message, 'error');
      }
    };
  }
});

window.openPhotoModal = function(url, title) {
  if (!url) return;
  const modalImg = document.getElementById('photoPreviewModalImg');
  const modalTitle = document.getElementById('photoPreviewModalTitle');
  const newTab = document.getElementById('photoPreviewNewTab');
  const downloadBtn = document.getElementById('photoPreviewDownload');
  if (modalImg) modalImg.src = url;
  if (modalTitle && title) modalTitle.textContent = title;
  if (newTab) newTab.href = url;
  if (downloadBtn) downloadBtn.href = url;
  const m = new bootstrap.Modal(document.getElementById('photoPreviewModal'));
  m.show();
};
</script>

<!-- ARTA TIER ADJUSTMENT MODAL -->
<div class="modal fade" id="artaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="artaForm">
        <?= csrfField() ?>
        <input type="hidden" name="submission_id" value="<?= $id ?>">
        <div class="modal-header py-2.5 px-3.5" style="background: #0F2137; border-bottom: 2px solid #a97900; color: #ffffff;">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-warning fs-5"></i>
            <div>
              <h6 class="modal-title fw-bold mb-0 text-white" style="font-size: 0.95rem;">ARTA RA 11032 Statutory Tier</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Anti-Red Tape Act Prescribed Turnaround Standards</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Statutory Classification *</label>
            <select class="form-select form-select-sm" name="arta_tier" required>
              <option value="Simple (3 Days)" <?= ($c['arta_tier'] ?? '') === 'Simple (3 Days)' ? 'selected' : '' ?>>Simple Transaction (Max 3 Working Days)</option>
              <option value="Complex (7 Days)" <?= ($c['arta_tier'] ?? '') === 'Complex (7 Days)' ? 'selected' : '' ?>>Complex Transaction (Max 7 Working Days)</option>
              <option value="Highly Technical (20 Days)" <?= ($c['arta_tier'] ?? '') === 'Highly Technical (20 Days)' ? 'selected' : '' ?>>Highly Technical Case (Max 20 Working Days)</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Urgency Level</label>
            <select class="form-select form-select-sm" name="urgency_level">
              <?php foreach(['Low','Normal','High','Urgent'] as $u): ?>
                <option value="<?= $u ?>" <?= ($c['urgency_level'] ?? 'Normal') === $u ? 'selected' : '' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <small class="text-muted d-block" style="font-size: 0.72rem;">Updating this classification automatically recalculates the statutory resolution target deadline.</small>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i> Update ARTA Tier</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EVIDENCE PHOTO UPLOAD MODAL -->
<div class="modal fade" id="evidenceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="evidenceForm" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="submission_id" value="<?= $id ?>">
        <input type="hidden" name="photo_type" id="evidencePhotoType" value="after">
        <div class="modal-header py-2.5 px-3.5" style="background: #0F2137; border-bottom: 2px solid #a97900; color: #ffffff;">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-camera text-warning fs-5"></i>
            <div>
              <h6 class="modal-title fw-bold mb-0 text-white" id="evidenceModalTitle" style="font-size: 0.95rem;">Upload Evidence Photo</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Resolution Proof Protocol · City of Manila</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Select Evidence Image (JPG, PNG, WEBP) *</label>
            <input type="file" class="form-control form-control-sm" name="evidence_photo" accept="image/*" required>
          </div>
          <small class="text-muted d-block" style="font-size: 0.72rem;">The uploaded photo will be logged with an audit timestamp and displayed in the Before/After resolution protocol.</small>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-upload me-1"></i> Upload Photo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- PHOTO PREVIEW / LIGHTBOX MODAL -->
<div class="modal fade" id="photoPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden; background: #0f172a;">
      <div class="modal-header py-2.5 px-3.5 border-bottom border-secondary text-white" style="background: #09101d;">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-image text-warning fs-5"></i>
          <div>
            <h6 class="modal-title fw-bold mb-0 text-white" id="photoPreviewModalTitle">Complaint Evidence Photo</h6>
            <small class="text-white-50" style="font-size: 0.72rem;">Incident Evidence Inspection · Manila CEPFMS</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a href="#" id="photoPreviewNewTab" target="_blank" class="btn btn-sm btn-outline-light py-1 px-2" style="font-size: 0.75rem;" title="Open raw photo in new tab">
            <i class="bi bi-box-arrow-up-right me-1"></i> New Tab
          </a>
          <a href="#" id="photoPreviewDownload" download class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.75rem;" title="Download photo">
            <i class="bi bi-download me-1"></i> Download
          </a>
          <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body p-0 text-center d-flex align-items-center justify-content-center" style="min-height: 400px; max-height: 80vh; background: #050b14;">
        <img id="photoPreviewModalImg" src="" alt="Enlarged Evidence" style="max-height: 75vh; max-width: 100%; object-fit: contain; box-shadow: 0 10px 30px rgba(0,0,0,0.8);">
      </div>
      <div class="modal-footer py-2 px-3 border-top border-secondary text-white-50 d-flex justify-content-between" style="background: #09101d; font-size: 0.8rem;">
        <span><i class="bi bi-shield-check text-success me-1"></i> Prima Facie Evidence Attached to Complaint</span>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
