<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    redirect(appUrl('login.php'));
}

if(!verifyCsrfToken($_POST['csrf_token']??null)){
    setFlash('danger','Your login form expired. Please try again.');
    redirect(appUrl('login.php'));
}

$email=strtolower(trim((string)($_POST['email']??'')));
$password=(string)($_POST['password']??'');
$ip=cepfmsLoginIp();

if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){
    setFlash('warning','Enter a valid email address and password.');
    redirect(appUrl('login.php'));
}

if(cepfmsLoginRateLimited($email,$ip,5,15)){
    cepfmsLogActivity(
        null,
        'CEPFMS Login Rate Limited',
        'Login temporarily blocked after repeated failed attempts.'
    );
    setFlash(
        'danger',
        'Too many failed sign-in attempts. Please wait about 15 minutes before trying again.'
    );
    redirect(appUrl('login.php'));
}

try{
    $pdo=db();

    $stmt=$pdo->prepare(
        'SELECT
            u.id,u.full_name,u.email,u.password,u.role_id,u.status,
            u.deleted_at,r.name role_name
         FROM users u
         JOIN roles r ON r.id=u.role_id
         WHERE LOWER(u.email)=:email
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':email'=>$email]);
    $user=$stmt->fetch();

    if(!$user||!password_verify($password,(string)$user['password'])){
        cepfmsRecordLoginAttempt($email,$ip,false);
        cepfmsLogActivity(null,'CEPFMS Login Failed','Invalid sign-in attempt.');
        setFlash('danger','Invalid email address or password.');
        redirect(appUrl('login.php'));
    }

    if(strcasecmp((string)$user['status'],'Active')!==0){
        cepfmsRecordLoginAttempt($email,$ip,false);
        cepfmsLogActivity(
            (int)$user['id'],
            'CEPFMS Login Blocked',
            'Inactive shared account attempted sign-in.'
        );
        setFlash('danger','This shared account is inactive.');
        redirect(appUrl('login.php'));
    }

    if(!cepfmsHasSystemAccess((int)$user['id'])){
        cepfmsRecordLoginAttempt($email,$ip,false);
        cepfmsLogActivity(
            (int)$user['id'],
            'CEPFMS Access Denied',
            'Shared user does not have active CEPFMS system access.'
        );
        setFlash('danger','Your account does not currently have access to CEPFMS.');
        redirect(appUrl('login.php'));
    }

    cepfmsRecordLoginAttempt($email,$ip,true);

    session_regenerate_id(true);

    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['full_name']=(string)$user['full_name'];
    $_SESSION['email']=(string)$user['email'];
    $_SESSION['role_id']=(int)$user['role_id'];
    $_SESSION['role']=(string)$user['role_name'];
    $_SESSION['role_name']=(string)$user['role_name'];
    $_SESSION['logged_in']=true;
    $_SESSION['last_activity']=time();
    $_SESSION['session_rotated_at']=time();
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));

    $_SESSION['user']=[
        'id'=>(int)$user['id'],
        'full_name'=>(string)$user['full_name'],
        'email'=>(string)$user['email'],
        'role_id'=>(int)$user['role_id'],
        'role'=>(string)$user['role_name'],
        'role_name'=>(string)$user['role_name'],
    ];

    $pdo->prepare(
        'UPDATE users
         SET last_login_at=NOW()
         WHERE id=:id'
    )->execute([':id'=>(int)$user['id']]);

    unset($_SESSION['auth_expired']);

    cepfmsLogActivity(
        (int)$user['id'],
        'CEPFMS Login',
        'Successful CEPFMS sign-in.'
    );

    setFlash('success','Welcome to CEPFMS.');
    redirect(appUrl('dashboard.php'));
}catch(Throwable $e){
    error_log('[CEPFMS Login] '.$e->getMessage());

    setFlash(
        'danger',
        APP_DEBUG
            ? 'Login error: '.$e->getMessage()
            : 'Unable to sign in right now.'
    );

    redirect(appUrl('login.php'));
}
