<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('pages/profile.php'));
requireCsrf();

$pdo=db();
$userId=currentUserId();

$name=clean($_POST['full_name']??'');
$username=strtolower(clean($_POST['username']??''));
$email=strtolower(clean($_POST['email']??''));
$phone=clean($_POST['phone']??'');
$address=clean($_POST['address']??'');
$district=clean($_POST['district']??'');
$barangay=clean($_POST['barangay']??'');
$preferred=clean($_POST['preferred_contact']??'Portal');

if(strlen($name)<3){
    setFlash('danger','Please enter your full name.');
    redirect(appUrl('pages/profile.php'));
}

if(!preg_match('/^[a-z0-9._-]{4,40}$/',$username)){
    setFlash('danger','Username must be 4-40 characters using letters, numbers, dot, underscore or dash.');
    redirect(appUrl('pages/profile.php'));
}

if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
    setFlash('danger','Please enter a valid email address.');
    redirect(appUrl('pages/profile.php'));
}

if(!in_array($preferred,['Portal','Email','Phone'],true)){
    setFlash('danger','Invalid contact preference.');
    redirect(appUrl('pages/profile.php'));
}

try{
    $pdo->beginTransaction();

    $dup=$pdo->prepare(
        'SELECT COUNT(*)
         FROM users
         WHERE id<>:user
           AND deleted_at IS NULL
           AND (LOWER(email)=:email OR username=:username)'
    );
    $dup->execute([
        ':user'=>$userId,
        ':email'=>$email,
        ':username'=>$username,
    ]);

    if((int)$dup->fetchColumn()>0){
        throw new RuntimeException('That email or username is already being used by another account.');
    }

    $profileQ=$pdo->prepare(
        'SELECT id,privacy_consent_at,terms_accepted_at
         FROM citizen_portal_profiles
         WHERE user_id=:user
         LIMIT 1
         FOR UPDATE'
    );
    $profileQ->execute([':user'=>$userId]);
    $profile=$profileQ->fetch();

    if(!$profile){
        if(empty($_POST['privacy_consent'])||empty($_POST['terms_acceptance'])){
            throw new RuntimeException('Please accept the privacy notice and Citizen Portal terms.');
        }

        $pdo->prepare(
            'INSERT INTO citizen_portal_profiles
             (user_id,address,district,barangay,preferred_contact,
              privacy_consent_at,terms_accepted_at,created_at,updated_at)
             VALUES(:user,:address,:district,:barangay,:preferred,
              NOW(),NOW(),NOW(),NOW())'
        )->execute([
            ':user'=>$userId,
            ':address'=>$address?:null,
            ':district'=>$district?:null,
            ':barangay'=>$barangay?:null,
            ':preferred'=>$preferred,
        ]);
    }else{
        $pdo->prepare(
            'UPDATE citizen_portal_profiles
             SET address=:address,
                 district=:district,
                 barangay=:barangay,
                 preferred_contact=:preferred,
                 updated_at=NOW()
             WHERE user_id=:user'
        )->execute([
            ':address'=>$address?:null,
            ':district'=>$district?:null,
            ':barangay'=>$barangay?:null,
            ':preferred'=>$preferred,
            ':user'=>$userId,
        ]);
    }

    $pdo->prepare(
        'UPDATE users
         SET username=:username,
             full_name=:name,
             email=:email,
             phone=:phone,
             updated_at=NOW()
         WHERE id=:user'
    )->execute([
        ':username'=>$username,
        ':name'=>$name,
        ':email'=>$email,
        ':phone'=>$phone?:null,
        ':user'=>$userId,
    ]);

    // Keep the linked PHCMS stakeholder contact profile aligned.
    $pdo->prepare(
        'UPDATE stakeholders
         SET full_name=:name,
             email=:email,
             phone=:phone,
             address=:address,
             updated_at=NOW()
         WHERE user_id=:user'
    )->execute([
        ':name'=>$name,
        ':email'=>$email,
        ':phone'=>$phone?:null,
        ':address'=>$address?:null,
        ':user'=>$userId,
    ]);

    portalMarkProfileUpdated($userId);

    portalLog(
        $userId,
        'Citizen Profile Updated',
        'Citizen updated profile and contact information.'
    );

    $pdo->commit();

    $_SESSION['citizen_full_name']=$name;
    $_SESSION['citizen_email']=$email;

    setFlash('success','Your profile was updated.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();

    error_log('[Citizen Portal profile update] '.$e->getMessage());

    setFlash(
        'danger',
        APP_DEBUG
            ? $e->getMessage()
            : (
                str_contains($e->getMessage(),'already being used')
                || str_contains($e->getMessage(),'accept')
                    ? $e->getMessage()
                    : 'Unable to update your profile right now.'
            )
    );
}

redirect(appUrl('pages/profile.php'));
