<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('register.php'));
requireCsrf();

$pdo=db();

$name=clean($_POST['full_name']??'');
$username=strtolower(clean($_POST['username']??''));
$email=strtolower(clean($_POST['email']??''));
$phone=clean($_POST['phone']??'');
$address=clean($_POST['address']??'');
$district=clean($_POST['district']??'');
$barangay=clean($_POST['barangay']??'');
$preferred=clean($_POST['preferred_contact']??'Portal');
$password=(string)($_POST['password']??'');
$confirm=(string)($_POST['confirm_password']??'');
$privacy=!empty($_POST['privacy_consent']);
$terms=!empty($_POST['terms_acceptance']);

if(strlen($name)<3) {
    setFlash('danger','Please enter your full name.');
    redirect(appUrl('register.php'));
}
if(!preg_match('/^[a-z0-9._-]{4,40}$/',$username)){
    setFlash('danger','Username must be 4-40 characters using letters, numbers, dot, underscore or dash.');
    redirect(appUrl('register.php'));
}
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
    setFlash('danger','Please enter a valid email address.');
    redirect(appUrl('register.php'));
}
if(!in_array($preferred,['Portal','Email','Phone'],true)){
    setFlash('danger','Invalid contact preference.');
    redirect(appUrl('register.php'));
}
if(strlen($password)<10
    || !preg_match('/[A-Z]/',$password)
    || !preg_match('/[a-z]/',$password)
    || !preg_match('/[0-9]/',$password)){
    setFlash('danger','Password must have at least 10 characters with uppercase, lowercase and a number.');
    redirect(appUrl('register.php'));
}
if($password!==$confirm){
    setFlash('danger','The passwords do not match.');
    redirect(appUrl('register.php'));
}
if(!$privacy||!$terms){
    setFlash('danger','Please accept the privacy notice and portal terms.');
    redirect(appUrl('register.php'));
}

try{
    $dup=$pdo->prepare(
        'SELECT COUNT(*)
         FROM users
         WHERE deleted_at IS NULL
           AND (LOWER(email)=:email OR username=:username)'
    );
    $dup->execute([':email'=>$email,':username'=>$username]);
    if((int)$dup->fetchColumn()>0){
        setFlash('warning','That email or username is already registered. Try signing in instead.');
        redirect(appUrl('login.php'));
    }

    $roleQ=$pdo->prepare('SELECT id FROM roles WHERE name=:name LIMIT 1');
    $roleQ->execute([':name'=>ROLE_PUBLIC]);
    $roleId=(int)$roleQ->fetchColumn();

    $systemId=portalSystemId();

    if(!$roleId||!$systemId){
        throw new RuntimeException('Citizen Portal foundation migration is not installed.');
    }

    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO users
         (username,full_name,email,phone,password,role_id,office_id,department_id,status,created_at,updated_at)
         VALUES(:username,:name,:email,:phone,:password,:role,NULL,NULL,"Active",NOW(),NOW())'
    )->execute([
        ':username'=>$username,
        ':name'=>$name,
        ':email'=>$email,
        ':phone'=>$phone?:null,
        ':password'=>password_hash($password,PASSWORD_DEFAULT),
        ':role'=>$roleId,
    ]);

    $userId=(int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO user_roles
         (user_id,role_id,is_primary,assigned_by,assigned_at)
         VALUES(:user,:role,1,NULL,NOW())'
    )->execute([':user'=>$userId,':role'=>$roleId]);

    $pdo->prepare(
        'INSERT INTO user_system_access
         (user_id,system_id,access_level,status,granted_by,granted_at,updated_at)
         VALUES(:user,:system,"Citizen","Active",NULL,NOW(),NOW())'
    )->execute([':user'=>$userId,':system'=>$systemId]);

    $pdo->prepare(
        'INSERT INTO citizen_portal_profiles
         (user_id,address,district,barangay,preferred_contact,
          privacy_consent_at,terms_accepted_at,created_at,updated_at)
         VALUES(:user,:address,:district,:barangay,:preferred,NOW(),NOW(),NOW(),NOW())'
    )->execute([
        ':user'=>$userId,
        ':address'=>$address?:null,
        ':district'=>$district?:null,
        ':barangay'=>$barangay?:null,
        ':preferred'=>$preferred,
    ]);

    $pdo->prepare(
        'INSERT INTO citizen_portal_account_security
         (user_id,session_version,created_at,updated_at)
         VALUES(:user,1,NOW(),NOW())'
    )->execute([':user'=>$userId]);

    portalLog($userId,'Citizen Portal Registration','New Public User account registered through the citizen portal.');

    $pdo->commit();

    setFlash('success','Registration complete. You can now sign in.');
    redirect(appUrl('login.php'));
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[Citizen Portal registration] '.$e->getMessage());
    setFlash('danger',APP_DEBUG?'Registration error: '.$e->getMessage():'Registration could not be completed. Please try again.');
    redirect(appUrl('register.php'));
}
