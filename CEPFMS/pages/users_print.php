<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.users.manage');

$q=db()->prepare(
    'SELECT u.full_name,u.email,u.username,u.status,r.name role_name,
            o.name office_name,d.name department_name,
            COALESCE(usa.status,"No Row") access_status,
            COALESCE(usa.access_level,"Standard") access_level,
            u.last_login_at
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN offices o ON o.id=u.office_id
     LEFT JOIN departments d ON d.id=u.department_id
     LEFT JOIN user_system_access usa
       ON usa.user_id=u.id AND usa.system_id=:system
     WHERE u.deleted_at IS NULL
     ORDER BY r.id,u.full_name'
);
$q->execute([':system'=>cepfmsSystemId()]);$rows=$q->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>CEPFMS Users</title><style>body{font:10px Arial;margin:20px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:10px;margin-bottom:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:5px}th{background:#eee}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Shared User / CEPFMS Access Registry</h2><div><?= date('F j, Y g:i A') ?></div></div><table><thead><tr><th>User</th><th>Role</th><th>Office/Department</th><th>Account</th><th>CEPFMS Access</th><th>Last Login</th></tr></thead><tbody><?php foreach($rows as $u): ?><tr><td><?= e($u['full_name']) ?><br><?= e($u['email']) ?></td><td><?= e($u['role_name']) ?></td><td><?= e($u['office_name']?:'—') ?><br><?= e($u['department_name']?:'') ?></td><td><?= e($u['status']) ?></td><td><?= e($u['access_status'].' · '.$u['access_level']) ?></td><td><?= e($u['last_login_at']?:'—') ?></td></tr><?php endforeach; ?></tbody></table></body></html>
