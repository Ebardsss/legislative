<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('pages/account_security.php'));
requireCsrf();

$pdo=db();
$userId=currentUserId();
$password=(string)($_POST['current_password']??'');
$confirmation=strtoupper(trim((string)($_POST['confirmation']??'')));

if($confirmation!=='DEACTIVATE'){
    setFlash('danger','Type DEACTIVATE exactly to confirm.');
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

    if($hash===false||!password_verify($password,(string)$hash)){
        throw new RuntimeException('Current password is incorrect.');
    }

    $systemId=portalSystemId();
    if(!$systemId){
        throw new RuntimeException('Citizen Portal system registration is missing.');
    }

    $pdo->prepare(
        'UPDATE user_system_access
         SET status="Inactive",updated_at=NOW()
         WHERE user_id=:user
           AND system_id=:system'
    )->execute([
        ':user'=>$userId,
        ':system'=>$systemId,
    ]);

    portalBumpSessionVersion($userId,false,true);

    portalLog(
        $userId,
        'Citizen Portal Access Deactivated',
        'Citizen deactivated Citizen Portal access. Shared legislative account and records were retained.'
    );

    $pdo->commit();

    portalDestroySession();

    session_name(SESSION_NAME);
    session_start();
    setFlash('success','Citizen Portal access was deactivated. Your shared account and records were not deleted.');
    redirect(appUrl('login.php'));
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();

    error_log('[Citizen Portal deactivation] '.$e->getMessage());

    setFlash(
        'danger',
        $e->getMessage()==='Current password is incorrect.'
            ? $e->getMessage()
            : (APP_DEBUG?$e->getMessage():'Unable to deactivate Citizen Portal access.')
    );

    redirect(appUrl('pages/account_security.php'));
}
