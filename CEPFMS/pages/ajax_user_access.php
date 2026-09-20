<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.users.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.',[],405);
requireCsrf();

$pdo=db();$userId=(int)($_POST['user_id']??0);$mode=clean($_POST['mode']??'');
if(!in_array($mode,['grant','revoke'],true))jsonResponse(false,'Invalid access action.');

try{
    $pdo->beginTransaction();

    $adminLock=$pdo->prepare(
        'SELECT u.id
         FROM users u
         JOIN roles r ON r.id=u.role_id
         JOIN user_system_access usa
           ON usa.user_id=u.id
          AND usa.system_id=:system
          AND usa.status="Active"
         WHERE r.name="Administrator"
           AND u.status="Active"
           AND u.deleted_at IS NULL
         FOR UPDATE'
    );
    $adminLock->execute([':system'=>cepfmsSystemId()]);
    $adminLock->fetchAll();

    $q=$pdo->prepare(
        'SELECT u.id,u.full_name,u.status,r.name role_name,
                COALESCE(usa.access_level,"Standard") access_level,
                COALESCE(usa.status,"Inactive") access_status
         FROM users u
         JOIN roles r ON r.id=u.role_id
         LEFT JOIN user_system_access usa
           ON usa.user_id=u.id AND usa.system_id=:system
         WHERE u.id=:user AND u.deleted_at IS NULL'
    );
    $q->execute([':system'=>cepfmsSystemId(),':user'=>$userId]);$u=$q->fetch();
    if(!$u){$pdo->rollBack();jsonResponse(false,'Shared user not found.');}

    $newStatus=$mode==='grant'?'Active':'Inactive';

    if(
        $mode==='revoke'
        && $u['role_name']==='Administrator'
        && $u['status']==='Active'
        && $u['access_status']==='Active'
        && cepfmsActiveAdministratorCount($userId)===0
    ){
        $pdo->rollBack();
        jsonResponse(false,'Cannot revoke access from the final active CEPFMS Administrator.');
    }

    $level=match($u['role_name']){
        'Administrator'=>'Administrator',
        'Legislative Staff'=>'Staff',
        'Committee Member'=>'Committee',
        'Registered Stakeholder'=>'Stakeholder',
        default=>'Standard',
    };

    $pdo->prepare(
        'INSERT INTO user_system_access
         (user_id,system_id,access_level,status,granted_by,granted_at,updated_at)
         VALUES(:user,:system,:level,:status,:by,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
           access_level=VALUES(access_level),
           status=VALUES(status),
           granted_by=VALUES(granted_by),
           updated_at=NOW()'
    )->execute([
        ':user'=>$userId,':system'=>cepfmsSystemId(),
        ':level'=>$level,':status'=>$newStatus,':by'=>currentUserId()
    ]);

    cepfmsLogActivity(
        currentUserId(),
        $mode==='grant'?'CEPFMS User Access Granted':'CEPFMS User Access Revoked',
        'User #'.$userId.' · '.$u['full_name'].'.'
    );

    $pdo->commit();
    jsonResponse(true,$mode==='grant'?'CEPFMS access granted.':'CEPFMS access revoked.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update CEPFMS access.');
}
