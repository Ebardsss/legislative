<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.users.manage');

$pdo=db();$pageTitle='User Management';$activeMenu='users';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');$roleId=(int)($_GET['role_id']??0);
$access=clean($_GET['access']??'');$page=max(1,(int)($_GET['page']??1));$perPage=25;

$where=['u.deleted_at IS NULL'];$params=[];
if($search!==''){
    $where[]='(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.username LIKE :s3)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
}
if($roleId>0){$where[]='u.role_id=:role';$params[':role']=$roleId;}
if($access!==''){
    if($access==='No Row')$where[]='usa.id IS NULL';
    else {$where[]='COALESCE(usa.status,"Inactive")=:access';$params[':access']=$access;}
}

$systemId=cepfmsSystemId();
$countSql=
    'SELECT COUNT(*)
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN user_system_access usa
       ON usa.user_id=u.id AND usa.system_id=:system
     WHERE '.implode(' AND ',$where);

$countParams=array_merge([':system'=>$systemId],$params);
$c=$pdo->prepare($countSql);$c->execute($countParams);
$total=(int)$c->fetchColumn();$pg=paginate($total,$page,$perPage);

$sql=
    'SELECT u.id,u.username,u.full_name,u.email,u.phone,u.role_id,u.office_id,u.department_id,
            u.status,u.created_at,u.last_login_at,r.name role_name,
            o.name office_name,d.name department_name,
            usa.id access_id,COALESCE(usa.status,"No Row") cepfms_access_status,
            COALESCE(usa.access_level,"Standard") access_level
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN offices o ON o.id=u.office_id
     LEFT JOIN departments d ON d.id=u.department_id
     LEFT JOIN user_system_access usa
       ON usa.user_id=u.id AND usa.system_id=:system
     WHERE '.implode(' AND ',$where).'
     ORDER BY FIELD(r.name,"Administrator","Legislative Staff","Committee Member","Registered Stakeholder","Public User"),
              u.full_name
     LIMIT '.$pg['perPage'].' OFFSET '.$pg['offset'];

$q=$pdo->prepare($sql);$q->execute($countParams);$rows=$q->fetchAll();

$roles=$pdo->query('SELECT id,name FROM roles ORDER BY id')->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$departments=$pdo->query("SELECT id,name,office_id FROM departments WHERE status='Active' ORDER BY name")->fetchAll();

$activeAdmins=cepfmsActiveAdministratorCount();

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head">
<div><div class="cef-eyebrow"><i class="bi bi-people-fill"></i> Step 9 · Shared Account Administration</div><h1>User Management</h1><p>Manage the shared legislative user registry and explicit CEPFMS access. Role changes affect the shared account across legislative subsystems; CEPFMS access remains subsystem-specific.</p></div>
<div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="users_print.php"><i class="bi bi-printer"></i> Print</a><button class="btn btn-primary" id="btnNewUser"><i class="bi bi-person-plus"></i> New User</button></div>
</div>

<div class="alert alert-warning">
<strong>Shared-account warning:</strong> changing a user's Role, Office, Department, account status, or password changes that shared account for the entire legislative platform. The <strong>CEPFMS Access</strong> field controls only this subsystem.
</div>

<div class="row g-3 mb-3">
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-people"></i><div><strong><?= $total ?></strong><small>Matching Users</small></div></div></div>
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-shield-lock"></i><div><strong><?= $activeAdmins ?></strong><small>Active CEPFMS Administrators</small></div></div></div>
<div class="col-md-4"><div class="cef-stat"><i class="bi bi-person-check"></i><div><strong><?= currentUserId() ?></strong><small>Your Shared User ID</small></div></div></div>
</div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Name, email, username"></div>
<div class="col-xl-3"><label class="form-label small">Role</label><select class="form-select form-select-sm" name="role_id"><option value="">All</option><?php foreach($roles as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $roleId===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">CEPFMS Access</label><select class="form-select form-select-sm" name="access"><option value="">All</option><?php foreach(['Active','Inactive','No Row'] as $x): ?><option <?= $access===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card cef-card"><div class="table-responsive"><table class="table cef-table mb-0">
<thead><tr><th>User</th><th>Role</th><th>Office / Department</th><th>Account</th><th>CEPFMS Access</th><th>Last Login</th><th class="text-end">Action</th></tr></thead>
<tbody>
<?php if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No shared users matched the filters.</td></tr><?php endif; ?>
<?php foreach($rows as $u): ?>
<tr>
<td><strong><?= e($u['full_name']) ?></strong><div class="small text-muted"><?= e($u['email']) ?><?= $u['username']?' · @'.e($u['username']):'' ?></div></td>
<td><?= e($u['role_name']) ?></td>
<td><?= e($u['office_name']?:'None') ?><div class="small text-muted"><?= e($u['department_name']?:'') ?></div></td>
<td><span class="cef-status <?= $u['status']==='Active'?'good':'bad' ?>"><?= e($u['status']) ?></span></td>
<td><span class="cef-status <?= $u['cepfms_access_status']==='Active'?'good':'bad' ?>"><?= e($u['cepfms_access_status']) ?></span><div class="small text-muted"><?= e($u['access_level']) ?></div></td>
<td><?= formatDateTime($u['last_login_at']) ?></td>
<td class="text-end">
<div class="btn-group btn-group-sm">
<button class="btn btn-outline-primary edit-user" data-id="<?= (int)$u['id'] ?>"><i class="bi bi-pencil"></i></button>
<?php if($u['cepfms_access_status']==='Active'): ?>
<button class="btn btn-outline-danger access-action" data-id="<?= (int)$u['id'] ?>" data-mode="revoke">Revoke</button>
<?php else: ?>
<button class="btn btn-outline-success access-action" data-id="<?= (int)$u['id'] ?>" data-mode="grant">Grant</button>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="card-body border-top"><?= renderPagination($pg,'users.php',array_filter(['search'=>$search,'role_id'=>$roleId?:null,'access'=>$access])) ?></div>
</div>

</main></div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="userForm">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" id="u_id" value="0">
        
        <!-- Pro Header -->
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-person-gear fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Shared Legislative User</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Configure user identity and explicit CEPFMS access</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <!-- Pro Compact Body -->
        <div class="modal-body p-3.5 bg-white">
          <!-- Section 1: User Identity -->
          <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.68rem; letter-spacing: 0.5px;">ACCOUNT IDENTITY</span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-7">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Full Name *</label>
              <input class="form-control form-control-sm" name="full_name" id="u_name" maxlength="150" placeholder="Juan Dela Cruz" required>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Username</label>
              <input class="form-control form-control-sm" name="username" id="u_username" maxlength="100" placeholder="jdelacruz">
            </div>
            <div class="col-md-7">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Email *</label>
              <input type="email" class="form-control form-control-sm" name="email" id="u_email" maxlength="150" placeholder="user@manila.gov.ph" required>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Phone</label>
              <input class="form-control form-control-sm" name="phone" id="u_phone" maxlength="50" placeholder="0917-000-0000">
            </div>
          </div>

          <!-- Section 2: Role & CEPFMS Access -->
          <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
            <span class="badge bg-primary-subtle text-primary" style="font-size: 0.68rem; letter-spacing: 0.5px;">ROLE & CEPFMS ACCESS</span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Shared Role *</label>
              <select class="form-select form-select-sm" name="role_id" id="u_role" required>
                <?php foreach($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id" id="u_office">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Department</label>
              <select class="form-select form-select-sm" name="department_id" id="u_department">
                <option value="">None</option>
                <?php foreach($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Account Status</label>
              <select class="form-select form-select-sm" name="status" id="u_status">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">CEPFMS Access</label>
              <select class="form-select form-select-sm" name="cepfms_access_status" id="u_access">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Access Level</label>
              <select class="form-select form-select-sm" id="u_level" disabled>
                <option>Administrator</option>
                <option>Staff</option>
                <option>Committee</option>
                <option>Stakeholder</option>
                <option>Standard</option>
              </select>
            </div>
          </div>

          <!-- Section 3: Password -->
          <div class="p-2.5 rounded-3 bg-light border">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-semibold text-secondary mb-0" style="font-size: 0.76rem;">Password</label>
              <span class="text-muted" style="font-size: 0.7rem;">Leave blank to keep existing password</span>
            </div>
            <input type="password" class="form-control form-control-sm" name="password" minlength="10" autocomplete="new-password" placeholder="Enter password (min. 10 chars)">
          </div>
        </div>

        <!-- Pro Footer -->
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Save Shared User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('userForm');
 const modal=new bootstrap.Modal(document.getElementById('userModal'));

 const syncAccessLevel=()=>{
   const text=u_role.options[u_role.selectedIndex]?.text||'';
   u_level.value=text==='Administrator'?'Administrator':
     (text==='Legislative Staff'?'Staff':
     (text==='Committee Member'?'Committee':
     (text==='Registered Stakeholder'?'Stakeholder':'Standard')));
 };
 u_role.addEventListener('change',syncAccessLevel);

 document.getElementById('btnNewUser').onclick=()=>{
   form.reset();u_id.value='0';u_status.value='Active';u_access.value='Active';syncAccessLevel();modal.show();
 };

 document.querySelectorAll('.edit-user').forEach(b=>b.onclick=async function(){
   const r=await fetch('ajax_user_get.php?id='+this.dataset.id,{
     headers:{'X-Requested-With':'XMLHttpRequest'}
   }).then(x=>x.json());
   if(!r.success){Swal.fire('User',r.message,'error');return;}
   const u=r.user;
   u_id.value=u.id;u_name.value=u.full_name||'';u_username.value=u.username||'';
   u_email.value=u.email||'';u_phone.value=u.phone||'';u_role.value=u.role_id||'';
   u_office.value=u.office_id||'';u_department.value=u.department_id||'';
   u_status.value=u.status||'Active';u_access.value=u.cepfms_access_status||'Inactive';
   syncAccessLevel();
   modal.show();
 });

 form.onsubmit=async e=>{
   e.preventDefault();
   const r=await fetch('ajax_user_save.php',{
     method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)
   }).then(x=>x.json());
   if(r.success){await Swal.fire('User',r.message,'success');location.reload();}
   else Swal.fire('User',r.message,'error');
 };

 document.querySelectorAll('.access-action').forEach(b=>b.onclick=async function(){
   const grant=this.dataset.mode==='grant';
   const c=await Swal.fire({
     title:(grant?'Grant':'Revoke')+' CEPFMS access?',
     text:'This changes only access to the Citizen Engagement subsystem.',
     showCancelButton:true
   });
   if(!c.isConfirmed)return;
   const fd=new FormData();
   fd.append('csrf_token','<?= e(csrfToken()) ?>');
   fd.append('user_id',this.dataset.id);
   fd.append('mode',this.dataset.mode);
   const r=await fetch('ajax_user_access.php',{
     method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd
   }).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('User Access',r.message,'error');
 });
});
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
