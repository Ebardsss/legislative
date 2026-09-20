<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('pages/account_security.php'));
requireCsrf();

$pdo=db();
$userId=currentUserId();

$current=(string)($_POST['current_password']??'');
$new=(string)($_POST['new_password']??'');
$confirm=(string)($_POST['confirm_password']??'');

if(!portalPasswordIsStrong($new)){
    setFlash('danger','New password must have at least 10 characters with uppercase, lowercase and a number.');
    redirect(appUrl('pages/account_security.php'));
}

if($new!==$confirm){
    setFlash('danger','The new passwords do not match.');
    redirect(appUrl('pages/account_security.php'));
}

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare(
        'SELECT password
         FROM users
         WHERE id=:user
           AND status="Active"
           AND deleted_at IS NULL
         LIMIT 1
         FOR UPDATE'
    );
    $q->execute([':user'=>$userId]);
    $hash=$q->fetchColumn();

    if($hash===false||!password_verify($current,(string)$hash)){
        throw new RuntimeException('Current password is incorrect.');
    }

    if(password_verify($new,(string)$hash)){
        throw new RuntimeException('Your new password must be different from the current password.');
    }

    $pdo->prepare(
        'UPDATE users
         SET password=:password,updated_at=NOW()
         WHERE id=:user'
    )->execute([
        ':password'=>password_hash($new,PASSWORD_DEFAULT),
        ':user'=>$userId,
    ]);

    $newVersion=portalBumpSessionVersion($userId,true,false);

    // Clear previous failed login records for the account.
    $pdo->prepare(
        'DELETE FROM citizen_portal_login_attempts
         WHERE email_hash=:email_hash'
    )->execute([
        ':email_hash'=>portalLoginHash((string)currentUser()['email']),
    ]);

    portalLog(
        $userId,
        'Citizen Password Changed',
        'Citizen changed the Citizen Portal/shared account password.'
    );

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['citizen_session_version']=$newVersion;
    $_SESSION['session_rotated_at']=time();
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));

    setFlash('success','Password changed. Other Citizen Portal sessions using the old password were invalidated.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();

    error_log('[Citizen Portal password change] '.$e->getMessage());

    $safeMessages=[
        'Current password is incorrect.',
        'Your new password must be different from the current password.',
    ];

    setFlash(
        'danger',
        in_array($e->getMessage(),$safeMessages,true)
            ? $e->getMessage()
            : (APP_DEBUG?$e->getMessage():'Unable to change your password right now.')
    );
}

redirect(appUrl('pages/account_security.php'));
