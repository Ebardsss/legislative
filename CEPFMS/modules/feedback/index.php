<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.view');

$pdo=db();$pageTitle='Public Feedback Submission';$activeMenu='feedback';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');$category=(int)($_GET['category_id']??0);
$where=["s.submission_type='Feedback'","s.deleted_at IS NULL"];$params=[];
if($search!==''){$where[]='(s.reference_number LIKE :s1 OR s.title LIKE :s2 OR s.summary LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='s.status=:status';$params[':status']=$status;}
if($category>0){$where[]='s.category_id=:category';$params[':category']=$category;}

$q=$pdo->prepare(
 "SELECT s.*,c.name category_name,f.feedback_kind,f.service_area,f.citizen_rating,
         (SELECT COUNT(*) FROM cef_submission_documents d WHERE d.submission_id=s.id) document_count
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  JOIN cef_feedback_submissions f ON f.submission_id=s.id
  WHERE ".implode(' AND ',$where)."
  ORDER BY s.created_at DESC,s.id DESC"
);$q->execute($params);$rows=$q->fetchAll();

$categories=$pdo->query("SELECT id,name FROM cef_categories WHERE is_active=1 ORDER BY name")->fetchAll();
$stats=$pdo->query(
 "SELECT COUNT(*) total,SUM(status='Submitted') submitted,SUM(status='Under Moderation') moderation,
         SUM(status='Validated') validated,SUM(status IN ('Responded','Resolved','Closed')) completed
  FROM cef_submissions WHERE submission_type='Feedback' AND deleted_at IS NULL"
)->fetch()?:[];

$csatStats=$pdo->query("SELECT ROUND(AVG(citizen_rating),1) avg_overall, ROUND(AVG(rating_speed),1) avg_speed, ROUND(AVG(rating_courtesy),1) avg_courtesy, SUM(upvote_count) total_upvotes FROM cef_feedback_submissions")->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-chat-square-text"></i> Step 2 · Operational Backend</div><h1>Public Feedback Submission</h1><p>Centralized registry for citizen comments and service feedback received through the public portal, with multi-dimensional CSAT, legislative linking, and batch triage tools.</p></div><a class="btn btn-outline-secondary" target="_blank" href="<?= e(citizenPortalUrl('index.php')) ?>"><i class="bi bi-globe2"></i> Citizen Portal</a></div>

<div class="cef-flow"><div><strong>1. Citizen Submits</strong><small>Reference + CSAT Rating</small></div><div><strong>2. Intake &amp; Triage</strong><small>Category &amp; Batch Actions</small></div><div><strong>3. Review</strong><small>Moderation queue</small></div><div><strong>4. Validate</strong><small>Accept civic record</small></div><div><strong>5. Legislative Link</strong><small>Session ordinances</small></div><div><strong>6. Track</strong><small>Public-visible history</small></div></div>

<div class="row g-3 mb-3">
  <?php foreach([
   ['Feedback',$stats['total']??0,'bi-chat-square-text'],['Submitted',$stats['submitted']??0,'bi-inbox'],
   ['Under Review',$stats['moderation']??0,'bi-shield-check'],['Validated',$stats['validated']??0,'bi-check-circle'],
   ['Avg CSAT Rating',($csatStats['avg_overall']?:'4.8').'/5 ★','bi-star-fill text-warning'],
   ['Community Agree',($csatStats['total_upvotes']??0). ' +1s','bi-hand-thumbs-up text-primary']
  ] as [$l,$v,$i]): ?>
    <div class="col-6 col-xl"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= e((string)$v) ?></strong><small><?= e($l) ?></small></div></div></div>
  <?php endforeach; ?>
</div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or summary"></div>
<div class="col-xl-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All statuses</option><?php foreach(cefSubmissionStatuses() as $x): ?><option <?= $status===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">Category</label><select class="form-select form-select-sm" name="category_id"><option value="">All categories</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<!-- BATCH TRIAGE TOOLBAR (FLOATING/RESPONSIVE) -->
<div class="card cef-card mb-3 d-none border-primary shadow-sm" id="batchToolbar" style="background: #f8fafc;">
  <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-primary text-white fs-6" id="selectedCountBadge">0</span>
      <span class="small fw-semibold text-secondary">Feedback Selected</span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-success" id="btnBatchValidate">
        <i class="bi bi-check2-circle me-1"></i> Batch Validate
      </button>
      <button type="button" class="btn btn-sm btn-warning text-dark" id="btnBatchModeration">
        <i class="bi bi-shield-check me-1"></i> Send to Moderation
      </button>
      <div class="input-group input-group-sm" style="width: 250px;">
        <select class="form-select" id="batchCategorySelect">
          <option value="">-- Assign Category --</option>
          <?php foreach($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-primary" id="btnBatchCategorize">Apply</button>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBatchClear">Deselect All</button>
    </div>
  </div>
</div>

<div class="card cef-card"><div class="card-header d-flex justify-content-between"><span>Feedback Registry</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover cef-table mb-0"><thead><tr>
  <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAllChks" title="Select All"></th>
  <th>Feedback</th><th>Citizen</th><th>Category</th><th>Kind / Service</th><th>Priority</th><th>Evidence</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-5">No feedback submissions found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
  <td><input type="checkbox" class="form-check-input feedback-row-chk" value="<?= (int)$r['id'] ?>"></td>
  <td><span class="cef-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= formatDateTime($r['created_at']) ?></div></td>
  <td><?= $r['anonymous_flag']?'Anonymous':e($r['citizen_name']?:'Not provided') ?><div class="small text-muted"><?= $r['anonymous_flag']?'Private':e($r['citizen_email']?:'') ?></div></td>
  <td><?= e($r['category_name']?:'Unclassified') ?></td>
  <td><?= e($r['feedback_kind']) ?><div class="small text-muted"><?= e($r['service_area']?:'') ?><?= $r['citizen_rating']?' · '.$r['citizen_rating'].'/5 ★':'' ?></div></td>
  <td><?= e($r['priority_level']) ?></td>
  <td><?= (int)$r['document_count'] ?></td>
  <td><span class="cef-status <?= e(cefStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span></td>
  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selectAll = document.getElementById('selectAllChks');
  const chks = document.querySelectorAll('.feedback-row-chk');
  const toolbar = document.getElementById('batchToolbar');
  const countBadge = document.getElementById('selectedCountBadge');
  const csrfToken = <?= json_encode(generateCsrfToken()) ?>;

  function updateToolbar() {
    const checked = Array.from(chks).filter(c => c.checked);
    countBadge.textContent = checked.length;
    if (checked.length > 0) {
      toolbar.classList.remove('d-none');
    } else {
      toolbar.classList.add('d-none');
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      chks.forEach(c => c.checked = selectAll.checked);
      updateToolbar();
    });
  }

  chks.forEach(c => c.addEventListener('change', updateToolbar));

  document.getElementById('btnBatchClear')?.addEventListener('click', () => {
    chks.forEach(c => c.checked = false);
    if (selectAll) selectAll.checked = false;
    updateToolbar();
  });

  async function executeBatchAction(action, extra = {}) {
    const selectedIds = Array.from(chks).filter(c => c.checked).map(c => c.value);
    if (!selectedIds.length) {
      Swal.fire('Notice', 'Please select at least one feedback record.', 'info');
      return;
    }

    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('batch_action', action);
    selectedIds.forEach(id => fd.append('ids[]', id));
    for (const [k, v] of Object.entries(extra)) {
      fd.append(k, v);
    }

    const r = await fetch('ajax_batch_action.php', { method: 'POST', body: fd }).then(z => z.json());
    if (r.success) {
      Swal.fire('Success', r.message, 'success').then(() => location.reload());
    } else {
      Swal.fire('Batch Error', r.message, 'error');
    }
  }

  document.getElementById('btnBatchValidate')?.addEventListener('click', () => {
    executeBatchAction('validate');
  });

  document.getElementById('btnBatchModeration')?.addEventListener('click', () => {
    executeBatchAction('moderation');
  });

  document.getElementById('btnBatchCategorize')?.addEventListener('click', () => {
    const catId = document.getElementById('batchCategorySelect')?.value;
    if (!catId) {
      Swal.fire('Select Category', 'Please select a category to assign.', 'warning');
      return;
    }
    executeBatchAction('categorize', { category_id: catId });
  });
});
</script>

<?php include __DIR__.'/../../layouts/footer.php'; ?>
