<?php
declare(strict_types=1);

if(ob_get_level()===0)ob_start();

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/functions.php';

if(session_status()===PHP_SESSION_NONE){
    $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')
        || (int)($_SERVER['SERVER_PORT']??0)===443;

    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    ini_set('session.use_trans_sid','0');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime'=>SESSION_LIFETIME,
        'path'=>'/',
        'secure'=>$https,
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
    session_start();
}

if(!headers_sent()){
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header(
        "Content-Security-Policy: default-src 'self'; ".
        "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; ".
        "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; ".
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; ".
        "img-src 'self' data: https:; ".
        "connect-src 'self'; ".
        "object-src 'none'; ".
        "base-uri 'self'; ".
        "form-action 'self'; ".
        "frame-ancestors 'self';"
    );

    if(!empty($_SESSION['citizen_user_id'])){
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    if(
        (!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')
        || (int)($_SERVER['SERVER_PORT']??0)===443
    ){
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if(!empty($_SESSION['citizen_user_id'])){
    $last=(int)($_SESSION['last_activity']??0);

    if($last&&time()-$last>SESSION_LIFETIME){
        portalDestroySession();

        if(isAjaxRequest()){
            jsonResponse(false,'Your session expired. Please sign in again.',[
                'session_expired'=>true,
                'login_url'=>appUrl('login.php'),
            ],401);
        }

        redirect(appUrl('login.php?expired=1'));
    }

    $_SESSION['last_activity']=time();

    if(time()-(int)($_SESSION['session_rotated_at']??0)>=1800){
        session_regenerate_id(true);
        $_SESSION['session_rotated_at']=time();
    }
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['citizen_user_id']);
}

function currentUserId(): int
{
    return (int)($_SESSION['citizen_user_id']??0);
}

function currentUser(): ?array
{
    if(!isLoggedIn())return null;

    return [
        'id'=>currentUserId(),
        'full_name'=>(string)($_SESSION['citizen_full_name']??''),
        'email'=>(string)($_SESSION['citizen_email']??''),
        'role_name'=>(string)($_SESSION['citizen_role_name']??''),
    ];
}

function portalAccountRow(int $userId): ?array
{
    try{
        $q=db()->prepare(
            'SELECT u.id,u.username,u.full_name,u.email,u.phone,
                    u.password,u.status,u.deleted_at,
                    r.name role_name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE u.id=:user
             LIMIT 1'
        );
        $q->execute([':user'=>$userId]);
        $row=$q->fetch();

        return $row?:null;
    }catch(Throwable $e){
        error_log('[Citizen Portal account row] '.$e->getMessage());
        return null;
    }
}

function portalHasAccess(int $userId): bool
{
    $systemId=portalSystemId();
    if(!$systemId)return false;

    try{
        $q=db()->prepare(
            'SELECT COUNT(*)
             FROM users u
             JOIN roles r ON r.id=u.role_id
             JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system
              AND usa.status="Active"
             JOIN role_permissions rp ON rp.role_id=u.role_id
             JOIN permissions p
               ON p.id=rp.permission_id
              AND p.system_id=:permission_system
              AND p.code="public_portal.access"
             WHERE u.id=:user
               AND u.status="Active"
               AND u.deleted_at IS NULL
               AND r.name IN ("Public User","Registered Stakeholder")'
        );
        $q->execute([
            ':system'=>$systemId,
            ':permission_system'=>$systemId,
            ':user'=>$userId,
        ]);

        return (int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[Citizen Portal access] '.$e->getMessage());
        return false;
    }
}

function requireLogin(): void
{
    if(!isLoggedIn()){
        if(isAjaxRequest()){
            jsonResponse(false,'Please sign in to continue.',[
                'session_expired'=>true,
                'login_url'=>appUrl('login.php'),
            ],401);
        }

        setFlash('warning','Please sign in to continue.');
        redirect(appUrl('login.php'));
    }

    $userId=currentUserId();
    $account=portalAccountRow($userId);

    if(
        !$account
        || $account['deleted_at']!==null
        || $account['status']!=='Active'
        || !in_array($account['role_name'],[ROLE_PUBLIC,ROLE_STAKEHOLDER],true)
        || !portalHasAccess($userId)
    ){
        portalDestroySession();

        if(isAjaxRequest()){
            jsonResponse(false,'Your Citizen Portal access is no longer active.',[],403);
        }

        redirect(appUrl('login.php?access=denied'));
    }

    $security=portalSecurityState($userId);
    if(!$security){
        portalDestroySession();

        if(isAjaxRequest()){
            jsonResponse(false,'Citizen Portal security setup is incomplete.',[],503);
        }

        redirect(appUrl('login.php?setup=required'));
    }

    $sessionVersion=(int)($_SESSION['citizen_session_version']??0);
    if($sessionVersion!==0 && $sessionVersion!==(int)$security['session_version']){
        portalDestroySession();

        if(isAjaxRequest()){
            jsonResponse(false,'Your account security changed. Please sign in again.',[
                'session_expired'=>true,
                'login_url'=>appUrl('login.php'),
            ],401);
        }

        redirect(appUrl('login.php?security=changed'));
    }

    if($sessionVersion===0){
        $_SESSION['citizen_session_version']=(int)$security['session_version'];
    }

    // Refresh display identity from the database instead of trusting stale session text.
    $_SESSION['citizen_full_name']=(string)$account['full_name'];
    $_SESSION['citizen_email']=(string)$account['email'];
    $_SESSION['citizen_role_name']=(string)$account['role_name'];
}
