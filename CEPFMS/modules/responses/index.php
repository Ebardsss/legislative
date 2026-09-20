<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.view');

$pdo=db();$pageTitle='Response Management';$activeMenu='responses';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');
$type=clean($_GET['submission_type']??'');
$responseStatus=clean($_GET['response_status']??'');

$where=[
    "s.deleted_at IS NULL",
    "s.moderation_status='Validated'",
    "s.status NOT IN ('Rejected','Duplicate','Withdrawn','Closed')"
];
$params=[];
if($search!==''){
    $where[]='(s.reference_number LIKE :s1 OR s.title LIKE :s2 OR r.response_reference LIKE :s3 OR r.subject LIKE :s4)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;$params[':s4']=$like;
}
if($type!==''){$where[]='s.submission_type=:type';$params[':type']=$type;}
if($responseStatus!==''){
    if($responseStatus==='No Response')$where[]='r.id IS NULL';
    else {$where[]='r.status=:response_status';$params[':response_status']=$responseStatus;}
}

$q=$pdo->prepare(
 "SELECT s.id submission_id,s.reference_number,s.submission_type,s.title,s.status submission_status,
         s.priority_level,s.created_at,c.name category_name,
         r.id response_id,r.response_reference,r.response_type,r.subject,r.status response_status,
         r.updated_at response_updated_at,
         drafter.full_name drafted_name,
         (SELECT COUNT(*) FROM cef_assignments a WHERE a.submission_id=s.id AND a.status<>'Cancelled') assignment_count,
         (SELECT COUNT(*) FROM cef_followups f WHERE f.submission_id=s.id) followup_count
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  LEFT JOIN cef_responses r
    ON r.id=(
      SELECT r2.id FROM cef_responses r2
      WHERE r2.submission_id=s.id
      ORDER BY r2.created_at DESC,r2.id DESC LIMIT 1
    )
  LEFT JOIN users drafter ON drafter.id=r.drafted_by
  WHERE ".implode(' AND ',$where)."
  ORDER BY
    CASE WHEN r.id IS NULL THEN 0 ELSE 1 END,
    FIELD(r.status,'Returned','Draft','Under Review','Approved','Delivered','Cancelled'),
    s.updated_at DESC,s.id DESC"
);
$q->execute($params);$rows=$q->fetchAll();

$stats=$pdo->query(
 "SELECT
   (SELECT COUNT(*) FROM cef_submissions WHERE moderation_status='Validated' AND deleted_at IS NULL AND status NOT IN ('Rejected','Duplicate','Withdrawn')) eligible,
   (SELECT COUNT(*) FROM cef_responses WHERE status='Draft') drafts,
   (SELECT COUNT(*) FROM cef_responses WHERE status='Under Review') review_count,
   (SELECT COUNT(*) FROM cef_responses WHERE status='Approved') approved,
   (SELECT COUNT(*) FROM cef_responses WHERE status='Delivered') delivered,
   (SELECT COUNT(*) FROM cef_notifications WHERE status IN ('Ready','Scheduled','Partially Sent')) notification_queue"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-reply-all"></i> Step 6 · Official Citizen Communication</div><h1>Response Management</h1><p>Draft, review, approve, publish, notify, and follow up on official responses for validated citizen feedback, proposals, and complaints.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="notifications.php"><i class="bi bi-bell"></i> Notifications</a><a class="btn btn-outline-secondary" href="report.php"><i class="bi bi-bar-chart"></i> Report</a></div></div>

<div class="cef-flow"><div><strong>1. Validated</strong><small>Moderation released</small></div><div><strong>2. Draft</strong><small>Prepare official response</small></div><div><strong>3. Review</strong><small>Quality / accuracy check</small></div><div><strong>4. Approve</strong><small>Administrator authorization</small></div><div><strong>5. Publish</strong><small>Secure citizen tracking</small></div><div><strong>6. Follow Up</strong><small>Two-way clarification</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Eligible Records',$stats['eligible']??0,'bi-inboxes'],
 ['Drafts',$stats['drafts']??0,'bi-pencil-square'],
 ['Under Review',$stats['review_count']??0,'bi-search'],
 ['Approved',$stats['approved']??0,'bi-check2-circle'],
 ['Delivered',$stats['delivered']??0,'bi-send-check'],
 ['Notification Queue',$stats['notification_queue']??0,'bi-bell']
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Submission / response reference, title, subject"></div>
<div class="col-xl-3"><label class="form-label small">Submission Type</label><select class="form-select form-select-sm" name="submission_type"><option value="">All</option><?php foreach(cefSubmissionTypes() as $x): ?><option <?= $type===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">Response Status</label><select class="form-select form-select-sm" name="response_status"><option value="">All</option><option <?= $responseStatus==='No Response'?'selected':'' ?>>No Response</option><?php foreach(['Draft','Returned','Under Review','Approved','Delivered','Cancelled'] as $x): ?><option <?= $responseStatus===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card cef-card"><div class="card-header">Validated Submission Response Queue</div><div class="table-responsive"><table class="table table-hover cef-table mb-0"><thead><tr><th>Submission</th><th>Type / Category</th><th>Submission Status</th><th>Latest Response</th><th>Drafted By</th><th>Follow-Ups</th><th>Response Status</th><th class="text-end">Action</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No response-eligible records found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><span class="cef-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['priority_level']) ?> · <?= formatDateTime($r['created_at']) ?></div></td>
<td><?= e(cefSubmissionTypeLabel($r['submission_type'])) ?><div class="small text-muted"><?= e($r['category_name']?:'Unclassified') ?></div></td>
<td><?= e($r['submission_status']) ?><div class="small text-muted"><?= (int)$r['assignment_count'] ?> assignment(s)</div></td>
<td><?php if($r['response_id']): ?><span class="cef-code"><?= e($r['response_reference']) ?></span><div><?= e($r['subject']) ?></div><?php else: ?><span class="text-muted">No response draft</span><?php endif; ?></td>
<td><?= e($r['drafted_name']?:'—') ?></td>
<td><?= (int)$r['followup_count'] ?></td>
<td><?= $r['response_status']?'<span class="cef-status '.e(cefStatusClass($r['response_status'])).'">'.e($r['response_status']).'</span>':'—' ?></td>
<td class="text-end"><?php if($r['response_id']): ?><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['response_id'] ?>"><i class="bi bi-eye"></i></a><?php elseif(cefHasPermission('cepfms.responses.manage')): ?><button class="btn btn-sm btn-primary new-response" data-submission="<?= (int)$r['submission_id'] ?>" data-reference="<?= e($r['reference_number']) ?>" data-title="<?= e($r['title']) ?>"><i class="bi bi-pencil-square"></i> Draft</button><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>

</main></div>

<?php if(cefHasPermission('cepfms.responses.manage')): ?>
<div class="modal fade" id="draftModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 580px;"><div class="modal-content shadow"><form id="draftForm"><?= csrfField() ?><input type="hidden" name="response_id" value="0"><input type="hidden" name="submission_id" id="draftSubmissionId"><div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-reply-fill me-1"></i> New Official Response Draft</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><div class="cef-alert mb-2 py-2 px-3 small" id="draftContext"></div><div class="row g-2 mb-2"><div class="col-md-7"><label class="form-label small fw-bold mb-1">Response Type</label><select class="form-select form-select-sm" name="response_type"><option>Acknowledgement</option><option>Progress Update</option><option>Clarification Request</option><option selected>Official Response</option><option>Resolution Notice</option><option>Final Response</option></select></div><div class="col-md-5 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-warning w-100" id="btnAiDraftNew"><i class="bi bi-stars"></i> Draft with Ollama</button></div></div><label class="form-label small fw-bold mb-1">Subject</label><input class="form-control form-control-sm mb-2" name="subject" required><label class="form-label small fw-bold mb-1">Response Body</label><textarea class="form-control form-control-sm" name="body" rows="5" style="resize: vertical; min-height: 110px; max-height: 240px;" required></textarea></div><div class="modal-footer py-2 px-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save Draft</button></div></form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('draftForm'),modal=new bootstrap.Modal(document.getElementById('draftModal'));
 document.querySelectorAll('.new-response').forEach(b=>b.onclick=()=>{form.reset();document.getElementById('draftSubmissionId').value=b.dataset.submission;document.getElementById('draftContext').innerHTML='<strong>'+b.dataset.reference+'</strong><br>'+b.dataset.title;modal.show();});
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_draft.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.response_id;else Swal.fire('Response Draft',r.message,'error');};

 const btnAiNew = document.getElementById('btnAiDraftNew');
 if (btnAiNew && form) {
   btnAiNew.addEventListener('click', async () => {
     const subId = document.getElementById('draftSubmissionId').value;
     const rType = form.querySelector('select[name=response_type]').value;
     if (!subId) { Swal.fire('Notice', 'No submission selected.', 'warning'); return; }
     btnAiNew.disabled = true;
     const origHtml = btnAiNew.innerHTML;
     btnAiNew.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Drafting...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', subId);
       fd.append('response_type', rType);
       const res = await fetch('ajax_ai_draft.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Failed to generate draft. Make sure Ollama is running.', 'error');
         return;
       }
       form.querySelector('input[name=subject]').value = res.subject;
       form.querySelector('textarea[name=body]').value = res.body;
       Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Draft generated by Ollama', timer: 1500, showConfirmButton: false});
     } catch (err) {
       Swal.fire('Ollama AI', 'Connection error: ' + err.message, 'error');
     } finally {
       btnAiNew.disabled = false;
       btnAiNew.innerHTML = origHtml;
     }
   });
 }
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
