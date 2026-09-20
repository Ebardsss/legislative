<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.users.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.',[],405);
requireCsrf();

$pdo=db();
$id=(int)($_POST['user_id']??0);
$name=clean($_POST['full_name']??'');
$username=clean($_POST['username']??'');
$email=strtolower(clean($_POST['email']??''));
$phone=clean($_POST['phone']??'');
$roleId=(int)($_POST['role_id']??0);
$officeId=(int)($_POST['office_id']??0)?:null;
$departmentId=(int)($_POST['department_id']??0)?:null;
$status=clean($_POST['status']??'Active');
$accessStatus=clean($_POST['cepfms_access_status']??'Inactive');
$accessLevel=clean($_POST['access_level']??'Standard');
$password=(string)($_POST['password']??'');

if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$roleId<=0){
    jsonResponse(false,'Name, valid email, and role are required.');
}
if(!in_array($status,['Active','Inactive'],true))jsonResponse(false,'Invalid account status.');
if(!in_array($accessStatus,['Active','Inactive'],true))jsonResponse(false,'Invalid CEPFMS access status.');
if(!in_array($accessLevel,['Administrator','Staff','Committee','Stakeholder','Standard'],true)){
    jsonResponse(false,'Invalid CEPFMS access level.');
}
if($password!==''&&(
    strlen($password)<10
    ||!preg_match('/[A-Z]/',$password)
    ||!preg_match('/[a-z]/',$password)
    ||!preg_match('/[0-9]/',$password)
)){
    jsonResponse(false,'Password must have at least 10 characters, uppercase, lowercase, and a number.');
}
if($id===0&&$password==='')jsonResponse(false,'Password is required for a new user.');

try{
    $roleQ=$pdo->prepare('SELECT name FROM roles WHERE id=:id');
    $roleQ->execute([':id'=>$roleId]);$roleName=(string)($roleQ->fetchColumn()?:'');
    if($roleName==='')jsonResponse(false,'Selected role was not found.');

    if($officeId){
        $q=$pdo->prepare("SELECT COUNT(*) FROM offices WHERE id=:id AND status='Active'");
        $q->execute([':id'=>$officeId]);
        if((int)$q->fetchColumn()===0)jsonResponse(false,'Selected office is unavailable.');
    }
    if($departmentId){
        $q=$pdo->prepare(
            "SELECT COUNT(*) FROM departments
             WHERE id=:id AND status='Active'
               AND (:office_is_null=1 OR office_id=:office_id)"
        );
        $q->execute([
            ':id'=>$departmentId,
            ':office_is_null'=>$officeId===null?1:0,
            ':office_id'=>$officeId,
        ]);
        if((int)$q->fetchColumn()===0)jsonResponse(false,'Selected department does not match the selected office.');
    }

    $dup=$pdo->prepare(
        'SELECT COUNT(*)
         FROM users
         WHERE deleted_at IS NULL
           AND (LOWER(email)=:email OR (:username_enabled=1 AND username=:username))
           AND id<>:id'
    );
    $dup->execute([
        ':email'=>$email,
        ':username_enabled'=>$username!==''?1:0,
        ':username'=>$username,
        ':id'=>$id,
    ]);
    if((int)$dup->fetchColumn()>0)jsonResponse(false,'Email or username is already used by another shared account.');

    $accessLevel=match($roleName){
        'Administrator'=>'Administrator',
        'Legislative Staff'=>'Staff',
        'Committee Member'=>'Committee',
        'Registered Stakeholder'=>'Stakeholder',
        default=>'Standard',
    };

    $pdo->beginTransaction();

    /*
     * Lock the current active CEPFMS Administrator set so two concurrent
     * demotion/deactivation requests cannot both remove the last administrators.
     */
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

    if($id>0&&cepfmsWouldRemoveFinalAdministrator(
        $id,$roleId,$status,$accessStatus
    )){
        $pdo->rollBack();
        jsonResponse(false,'This change would remove the final active CEPFMS Administrator. Keep at least one active Administrator with CEPFMS access.');
    }

    if($id>0){
        $oldQ=$pdo->prepare(
            'SELECT u.*,r.name role_name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE u.id=:id AND u.deleted_at IS NULL
             FOR UPDATE'
        );
        $oldQ->execute([':id'=>$id]);$old=$oldQ->fetch();
        if(!$old){$pdo->rollBack();jsonResponse(false,'Shared user not found.');}

        $sql=
            'UPDATE users
             SET username=:username,full_name=:name,email=:email,phone=:phone,
                 role_id=:role,office_id=:office,department_id=:department,
                 status=:status,updated_at=NOW()';
        $params=[
            ':username'=>$username?:null,':name'=>$name,':email'=>$email,
            ':phone'=>$phone?:null,':role'=>$roleId,':office'=>$officeId,
            ':department'=>$departmentId,':status'=>$status,':id'=>$id,
        ];
        if($password!==''){
            $sql.=',password=:password';
            $params[':password']=password_hash($password,PASSWORD_DEFAULT);
        }
        $sql.=' WHERE id=:id';
        $pdo->prepare($sql)->execute($params);
        $userId=$id;
        $event='CEPFMS Shared User Updated';
    }else{
        $pdo->prepare(
            'INSERT INTO users
             (username,full_name,email,phone,password,role_id,office_id,department_id,
              status,created_at,updated_at)
             VALUES(:username,:name,:email,:phone,:password,:role,:office,:department,
              :status,NOW(),NOW())'
        )->execute([
            ':username'=>$username?:null,':name'=>$name,':email'=>$email,
            ':phone'=>$phone?:null,
            ':password'=>password_hash($password,PASSWORD_DEFAULT),
            ':role'=>$roleId,':office'=>$officeId,':department'=>$departmentId,
            ':status'=>$status,
        ]);
        $userId=(int)$pdo->lastInsertId();
        $event='CEPFMS Shared User Created';
    }

    $pdo->prepare(
        'DELETE FROM user_roles
         WHERE user_id=:user AND is_primary=1'
    )->execute([':user'=>$userId]);

    $pdo->prepare(
        'INSERT INTO user_roles
         (user_id,role_id,is_primary,assigned_by,assigned_at)
         VALUES(:user,:role,1,:by,NOW())
         ON DUPLICATE KEY UPDATE
           is_primary=1,assigned_by=VALUES(assigned_by),assigned_at=NOW()'
    )->execute([
        ':user'=>$userId,':role'=>$roleId,':by'=>currentUserId()
    ]);

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
        ':level'=>$accessLevel,':status'=>$accessStatus,':by'=>currentUserId()
    ]);

    cepfmsLogActivity(
        currentUserId(),
        $event,
        'User #'.$userId.' · '.$name.' · role '.$roleName.
        ' · account '.$status.' · CEPFMS '.$accessStatus.'.'
    );

    $pdo->commit();

    jsonResponse(true,$id>0?'Shared user updated.':'Shared user created.');
}catch(PDOException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    if((string)$e->getCode()==='23000')jsonResponse(false,'Email or username already exists.');
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save shared user.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save shared user.');
}
