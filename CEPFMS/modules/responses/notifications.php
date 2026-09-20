<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.notifications.view');

$pdo=db();$pageTitle='Citizen Notifications';$activeMenu='responses';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$activeSubmissions=$pdo->query(
 "SELECT id,reference_number,submission_type,title,status
  FROM cef_submissions
  WHERE deleted_at IS NULL
    AND status NOT IN ('Rejected','Duplicate','Withdrawn','Closed')
  ORDER BY updated_at DESC
  LIMIT 300"
)->fetchAll();

$rows=$pdo->query(
 "SELECT n.*,s.reference_number submission_reference,r.response_reference,
         (SELECT COUNT(*) FROM cef_notification_recipients nr WHERE nr.notification_id=n.id) recipient_count,
         (SELECT COUNT(*) FROM cef_notification_recipients nr WHERE nr.notification_id=n.id AND nr.delivery_status='Sent') sent_count,
         (SELECT COUNT(*) FROM cef_notification_recipients nr WHERE nr.notification_id=n.id AND nr.delivery_status='Pending') pending_count
  FROM cef_notifications n
  LEFT JOIN cef_submissions s ON s.id=n.submission_id
  LEFT JOIN cef_responses r ON r.id=n.response_id
  ORDER BY COALESCE(n.scheduled_at,n.created_at) DESC,n.id DESC"
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-bell"></i> Citizen Communication Delivery</div><h1>Citizen Notifications</h1><p>Audit secure portal publication, shared-account notification delivery, and external email/phone records that remain Pending until a real provider is configured.</p></div><div class="d-flex gap-2"><?php if(cefHasPermission('cepfms.notifications.manage')): ?><button class="btn btn-primary" id="btnManualNotice"><i class="bi bi-bell-fill"></i> New Citizen Notice</button><?php endif; ?><a class="btn btn-outline-secondary" href="index.php">Response Management</a></div></div>

<div class="card cef-card"><div class="table-responsive"><table class="table cef-table mb-0"><thead><tr><th>Notification</th><th>Submission / Response</th><th>Subject</th><th>Schedule</th><th>Recipients</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No citizen notifications found.</td></tr><?php endif; ?>
<?php foreach($rows as $n): ?><tr><td><span class="cef-code"><?= e($n['notification_reference']) ?></span><div class="small text-muted"><?= e($n['notification_type']) ?></div></td><td><?= e($n['submission_reference']?:'—') ?><div class="small text-muted"><?= e($n['response_reference']?:'') ?></div></td><td><?= e($n['subject']) ?></td><td><?= $n['scheduled_at']?formatDateTime($n['scheduled_at']):'Immediate' ?></td><td><?= (int)$n['sent_count'] ?>/<?= (int)$n['recipient_count'] ?> sent<div class="small text-muted"><?= (int)$n['pending_count'] ?> pending</div></td><td><span class="cef-status <?= $n['status']==='Sent'?'good':($n['status']==='Cancelled'?'bad':'warn') ?>"><?= e($n['status']) ?></span></td><td class="text-end"><?php if(cefHasPermission('cepfms.notifications.manage')&&!in_array($n['status'],['Sent','Cancelled'],true)): ?><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary notification-action" data-id="<?= (int)$n['id'] ?>" data-mode="process">Process</button><button class="btn btn-outline-danger notification-action" data-id="<?= (int)$n['id'] ?>" data-mode="cancel">Cancel</button></div><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>


</main></div>
<?php if(cefHasPermission('cepfms.notifications.manage')): ?>
<div class="modal fade" id="manualNoticeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 580px;"><div class="modal-content shadow"><form id="manualNoticeForm"><?= csrfField() ?><div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-bell-fill me-1"></i> New Citizen Notice</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><label class="form-label small fw-bold mb-1">Citizen Submission</label><select class="form-select form-select-sm mb-2" name="submission_id" required><option value="">Select submission</option><?php foreach($activeSubmissions as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['reference_number'].' · '.$s['title'].' · '.$s['status']) ?></option><?php endforeach; ?></select><div class="row g-2"><div class="col-md-6"><label class="form-label small fw-bold mb-1">Notice Type</label><select class="form-select form-select-sm" name="notification_type"><option>Status Update</option><option>Clarification Reminder</option><option>Assignment Update</option><option>Service Update</option><option>Resolution Update</option></select></div><div class="col-md-6"><label class="form-label small fw-bold mb-1">Optional Schedule</label><input type="datetime-local" class="form-control form-control-sm" name="scheduled_at"></div></div><label class="form-label small fw-bold mt-2 mb-1">Subject</label><input class="form-control form-control-sm mb-2" name="subject" required><label class="form-label small fw-bold mb-1">Message</label><textarea class="form-control form-control-sm" name="message" rows="4" style="resize: vertical; min-height: 90px; max-height: 200px;" required></textarea></div><div class="modal-footer py-2 px-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Queue Notice</button></div></form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const manualForm=document.getElementById('manualNoticeForm'),manualModal=new bootstrap.Modal(document.getElementById('manualNoticeModal'));
 document.getElementById('btnManualNotice')?.addEventListener('click',()=>{manualForm.reset();manualModal.show();});
 manualForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_manual_notification.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(manualForm)}).then(x=>x.json());if(r.success){await Swal.fire('Citizen Notice',r.message,'success');location.reload();}else Swal.fire('Citizen Notice',r.message,'error');};
 document.querySelectorAll('.notification-action').forEach(b=>b.onclick=async function(){const c=await Swal.fire({title:(this.dataset.mode==='cancel'?'Cancel':'Process')+' notification?',showCancelButton:true});if(!c.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('notification_id',this.dataset.id);fd.append('mode',this.dataset.mode);const r=await fetch('ajax_notification.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Notification',r.message,'success');location.reload();}else Swal.fire('Notification',r.message,'error');});
});
</script><?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
