<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.users.manage');

$id=(int)($_GET['id']??0);
if($id<=0)jsonResponse(false,'Invalid user.');

try{
    $q=db()->prepare(
        'SELECT u.id,u.username,u.full_name,u.email,u.phone,u.role_id,u.office_id,u.department_id,
                u.status,COALESCE(usa.status,"Inactive") cepfms_access_status,
                COALESCE(usa.access_level,"Standard") access_level
         FROM users u
         LEFT JOIN user_system_access usa
           ON usa.user_id=u.id AND usa.system_id=:system
         WHERE u.id=:user
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $q->execute([':system'=>cepfmsSystemId(),':user'=>$id]);
    $user=$q->fetch();

    if(!$user)jsonResponse(false,'Shared user not found.');
    jsonResponse(true,'User loaded.',['user'=>$user]);
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to load user.');
}
