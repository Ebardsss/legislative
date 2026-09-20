<?php
declare(strict_types=1);

if(ob_get_level()===0){
    ob_start();
}

require_once __DIR__.'/functions.php';
require_once __DIR__.'/cepfms_helpers.php';
require_once __DIR__.'/cepfms_security.php';

if(!headers_sent()){
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header(
        "Content-Security-Policy: ".
        "default-src 'self'; ".
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; ".
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; ".
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; ".
        "img-src 'self' data: blob:; ".
        "connect-src 'self' http://127.0.0.1:11434 http://localhost:11434; ".
        "frame-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
    );
}

function isLoggedIn(): bool
{
    return currentUserId()>0;
}

function isAuthenticated(): bool
{
    return isLoggedIn();
}

if(isLoggedIn()){
    $_SESSION['last_activity']=time();

    $lastRotation=(int)($_SESSION['session_rotated_at']??0);
    if($lastRotation===0||(time()-$lastRotation)>=1800){
        session_regenerate_id(true);
        $_SESSION['session_rotated_at']=time();
    }
}

function requireLogin(): void
{
    if(isLoggedIn()){
        if(!cepfmsHasSystemAccess(currentUserId())){
            cepfmsLogActivity(
                currentUserId(),
                'CEPFMS Access Denied',
                'Authenticated shared account does not have active CEPFMS system access.'
            );

            if(isAjaxRequest()){
                jsonResponse(false,'Your account does not have active CEPFMS access.',[],403);
            }

            http_response_code(403);
            exit(
                '<h1>403 - CEPFMS Access Denied</h1>'.
                '<p>Your shared legislative account does not currently have access to CEPFMS.</p>'
            );
        }

        return;
    }

    if(isAjaxRequest()){
        jsonResponse(false,'Your session has expired. Sign in again.',[
            'session_expired'=>true,
            'login_url'=>appUrl('login.php'),
        ],401);
    }

    $query=!empty($_SESSION['auth_expired'])?'?expired=1':'';
    redirect(appUrl('login.php'.$query));
}

function requireRole(array $allowedRoles): void
{
    requireLogin();

    $current=normalizeRole(currentRole());
    $allowed=array_map(
        static fn(mixed $role): string => normalizeRole((string)$role),
        $allowedRoles
    );

    if(in_array($current,$allowed,true))return;

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS Role Denied',
        'Role '.$current.' attempted a restricted CEPFMS action.'
    );

    if(isAjaxRequest()){
        jsonResponse(false,'You do not have permission to perform this CEPFMS action.',[],403);
    }

    http_response_code(403);
    exit(
        '<h1>403 - Access Denied</h1>'.
        '<p>Your account does not have permission to access this page.</p>'.
        '<p><a href="'.e(appUrl('dashboard.php')).'">Return to dashboard</a></p>'
    );
}

function canManage(): bool
{
    if(cepfmsFinalRbacInstalled()){
        foreach([
            'cepfms.feedback.manage',
            'cepfms.proposals.manage',
            'cepfms.complaints.manage',
            'cepfms.moderation.manage',
            'cepfms.responses.manage',
            'cepfms.notifications.manage',
            'cepfms.analytics.manage',
        ] as $permission){
            if(cefHasPermission($permission))return true;
        }
        return false;
    }

    return in_array(
        normalizeRole(currentRole()),
        [normalizeRole(ROLE_ADMIN),normalizeRole(ROLE_STAFF)],
        true
    );
}
