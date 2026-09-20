<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('login.php'));
requireCsrf();

$email=strtolower(clean($_POST['email']??''));
$password=(string)($_POST['password']??'');
$ip=portalIp();

if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){
    setFlash('danger','Enter your email and password.');
    redirect(appUrl('login.php'));
}

if(portalRateLimited($email,$ip,5,15)){
    setFlash('danger','Too many failed sign-in attempts. Please wait about 15 minutes and try again.');
    redirect(appUrl('login.php'));
}

try{
    $q=db()->prepare(
        'SELECT u.id,u.full_name,u.email,u.password,u.status,u.deleted_at,r.name role_name
         FROM users u
         JOIN roles r ON r.id=u.role_id
         WHERE LOWER(u.email)=:email
         LIMIT 1'
    );
    $q->execute([':email'=>$email]);
    $u=$q->fetch();

    $valid=$u
        && $u['deleted_at']===null
        && $u['status']==='Active'
        && in_array($u['role_name'],[ROLE_PUBLIC,ROLE_STAKEHOLDER],true)
        && password_verify($password,(string)$u['password']);

    if(!$valid){
        portalRecordLoginAttempt($email,$ip,false);
        portalLog($u?(int)$u['id']:null,'Citizen Portal Login Failed','Invalid or ineligible sign-in attempt.');
        setFlash('danger','Invalid email or password.');
        redirect(appUrl('login.php'));
    }

    if(!portalHasAccess((int)$u['id'])){
        portalRecordLoginAttempt($email,$ip,false);
        portalLog((int)$u['id'],'Citizen Portal Access Denied','Account does not have active Citizen Portal access.');
        setFlash('danger','Your account does not currently have Citizen Portal access.');
        redirect(appUrl('login.php'));
    }

    $security=portalSecurityState((int)$u['id']);
    if(!$security){
        throw new RuntimeException('Citizen Portal account-security migration is not installed.');
    }

    portalRecordLoginAttempt($email,$ip,true);
    session_regenerate_id(true);

    $_SESSION['citizen_user_id']=(int)$u['id'];
    $_SESSION['citizen_full_name']=(string)$u['full_name'];
    $_SESSION['citizen_email']=(string)$u['email'];
    $_SESSION['citizen_role_name']=(string)$u['role_name'];
    $_SESSION['citizen_session_version']=(int)$security['session_version'];
    $_SESSION['last_activity']=time();
    $_SESSION['session_rotated_at']=time();
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));

    db()->prepare(
        'UPDATE users
         SET last_login_at=NOW()
         WHERE id=:id'
    )->execute([':id'=>(int)$u['id']]);

    portalLog((int)$u['id'],'Citizen Portal Login','Successful citizen portal sign-in.');

    redirect(appUrl('dashboard.php'));
}catch(Throwable $e){
    error_log('[Citizen Portal login] '.$e->getMessage());

    setFlash(
        'danger',
        APP_DEBUG
            ? 'Login error: '.$e->getMessage()
            : 'Unable to sign in right now.'
    );

    redirect(appUrl('login.php'));
}
