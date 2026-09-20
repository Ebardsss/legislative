<?php
declare(strict_types=1);

require_once __DIR__.'/cepfms_helpers.php';

function cepfmsFinalRbacInstalled(): bool
{
    static $cached=null;
    if($cached!==null)return $cached;

    try{
        if(!tableExists('cepfms_schema_migrations'))return $cached=false;

        $q=db()->prepare(
            'SELECT COUNT(*)
             FROM cepfms_schema_migrations
             WHERE migration_key=:key'
        );
        $q->execute([':key'=>'004_admin_rbac_security']);

        return $cached=(int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[CEPFMS RBAC migration check] '.$e->getMessage());
        return $cached=false;
    }
}

function cepfmsLegacyPermissionRoles(string $permission): array
{
    $admin=[ROLE_ADMIN];
    $staff=[ROLE_ADMIN,ROLE_STAFF];
    $committee=[ROLE_ADMIN,ROLE_STAFF,ROLE_COMMITTEE];

    return match($permission){
        'cepfms.dashboard.view',
        'cepfms.feedback.view',
        'cepfms.proposals.view',
        'cepfms.complaints.view',
        'cepfms.moderation.view',
        'cepfms.responses.view',
        'cepfms.notifications.view',
        'cepfms.analytics.view',
        'cepfms.workflow.view',
        'cepfms.reports.view' => $committee,

        'cepfms.feedback.manage',
        'cepfms.proposals.manage',
        'cepfms.complaints.manage',
        'cepfms.moderation.manage',
        'cepfms.responses.manage',
        'cepfms.notifications.manage',
        'cepfms.analytics.manage' => $staff,

        'cepfms.responses.approve',
        'cepfms.configuration.manage',
        'cepfms.activity_logs.view',
        'cepfms.users.manage',
        'cepfms.system_health.view' => $admin,

        default => $admin,
    };
}

function cepfmsUserHasPermission(int $userId,string $permission): bool
{
    if($userId<=0)return false;

    if(!cepfmsFinalRbacInstalled()){
        return in_array(
            normalizeRole(currentRole()),
            array_map('normalizeRole',cepfmsLegacyPermissionRoles($permission)),
            true
        );
    }

    $systemId=cepfmsSystemId();
    if(!$systemId)return false;

    try{
        $q=db()->prepare(
            'SELECT COUNT(*)
             FROM users u
             JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system_access
              AND usa.status="Active"
             JOIN role_permissions rp
               ON rp.role_id=u.role_id
             JOIN permissions p
               ON p.id=rp.permission_id
              AND p.system_id=:system_permission
              AND p.code=:permission
             WHERE u.id=:user
               AND u.status="Active"
               AND u.deleted_at IS NULL'
        );
        $q->execute([
            ':system_access'=>$systemId,
            ':system_permission'=>$systemId,
            ':permission'=>$permission,
            ':user'=>$userId,
        ]);

        return (int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[CEPFMS permission check] '.$e->getMessage());
        return false;
    }
}

function cefHasPermission(string $permission): bool
{
    return isAuthenticated()
        && cepfmsUserHasPermission(currentUserId(),$permission);
}

function requireCefPermission(string $permission): void
{
    requireLogin();

    if(cefHasPermission($permission))return;

    cepfmsLogActivity(
        currentUserId(),
        'CEPFMS Permission Denied',
        'Denied permission '.$permission.' for '.
        ($_SERVER['REQUEST_METHOD']??'GET').' '.
        ($_SERVER['REQUEST_URI']??'unknown request').'.'
    );

    if(isAjaxRequest()){
        jsonResponse(
            false,
            'You do not have permission to perform this CEPFMS action.',
            [],
            403
        );
    }

    http_response_code(403);
    $requiredPermission=$permission;

    $page=__DIR__.'/../pages/403.php';
    if(is_file($page)){
        include $page;
    }else{
        echo '<h1>403 - Access Denied</h1>';
    }
    exit;
}

function cepfmsPermissionCodesForCurrentUser(): array
{
    if(!isAuthenticated())return [];

    $known=[
        'cepfms.dashboard.view',
        'cepfms.feedback.view','cepfms.feedback.manage',
        'cepfms.proposals.view','cepfms.proposals.manage',
        'cepfms.complaints.view','cepfms.complaints.manage',
        'cepfms.moderation.view','cepfms.moderation.manage',
        'cepfms.responses.view','cepfms.responses.manage','cepfms.responses.approve',
        'cepfms.notifications.view','cepfms.notifications.manage',
        'cepfms.analytics.view','cepfms.analytics.manage',
        'cepfms.workflow.view','cepfms.reports.view',
        'cepfms.configuration.manage','cepfms.activity_logs.view',
        'cepfms.users.manage','cepfms.system_health.view'
    ];

    if(!cepfmsFinalRbacInstalled()){
        return array_values(array_filter($known,'cefHasPermission'));
    }

    try{
        $q=db()->prepare(
            'SELECT DISTINCT p.code
             FROM users u
             JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system_access
              AND usa.status="Active"
             JOIN role_permissions rp ON rp.role_id=u.role_id
             JOIN permissions p
               ON p.id=rp.permission_id
              AND p.system_id=:system_permission
             WHERE u.id=:user
               AND u.status="Active"
               AND u.deleted_at IS NULL
             ORDER BY p.code'
        );
        $q->execute([
            ':system_access'=>cepfmsSystemId(),
            ':system_permission'=>cepfmsSystemId(),
            ':user'=>currentUserId(),
        ]);

        return $q->fetchAll(PDO::FETCH_COLUMN);
    }catch(Throwable $e){
        error_log('[CEPFMS permission list] '.$e->getMessage());
        return [];
    }
}

function cepfmsLoginHash(string $value): string
{
    return hash('sha256',strtolower(trim($value)));
}

function cepfmsLoginIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45);
}

function cepfmsLoginRateLimited(
    string $email,
    string $ip,
    int $limit=5,
    int $minutes=15
): bool {
    if(!tableExists('cepfms_login_attempts'))return false;

    try{
        $q=db()->prepare(
            'SELECT
                SUM(email_hash=:email_hash) email_failures,
                SUM(ip_hash=:ip_hash) ip_failures
             FROM cepfms_login_attempts
             WHERE was_successful=0
               AND attempted_at>=:cutoff'
        );
        $q->execute([
            ':email_hash'=>cepfmsLoginHash($email),
            ':ip_hash'=>cepfmsLoginHash($ip),
            ':cutoff'=>date('Y-m-d H:i:s',time()-($minutes*60)),
        ]);
        $r=$q->fetch()?:[];

        return (int)($r['email_failures']??0)>=$limit
            || (int)($r['ip_failures']??0)>=$limit;
    }catch(Throwable $e){
        error_log('[CEPFMS login rate limit] '.$e->getMessage());
        return false;
    }
}

function cepfmsRecordLoginAttempt(
    string $email,
    string $ip,
    bool $success
): void {
    if(!tableExists('cepfms_login_attempts'))return;

    try{
        $pdo=db();

        $pdo->prepare(
            'INSERT INTO cepfms_login_attempts
             (email_hash,ip_hash,was_successful,attempted_at)
             VALUES(:email,:ip,:success,NOW())'
        )->execute([
            ':email'=>cepfmsLoginHash($email),
            ':ip'=>cepfmsLoginHash($ip),
            ':success'=>$success?1:0,
        ]);

        if($success){
            $pdo->prepare(
                'DELETE FROM cepfms_login_attempts
                 WHERE was_successful=0
                   AND (email_hash=:email_hash OR ip_hash=:ip_hash)'
            )->execute([
                ':email_hash'=>cepfmsLoginHash($email),
                ':ip_hash'=>cepfmsLoginHash($ip),
            ]);
        }

        $pdo->exec(
            'DELETE FROM cepfms_login_attempts
             WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY)'
        );
    }catch(Throwable $e){
        error_log('[CEPFMS login attempt] '.$e->getMessage());
    }
}

function cepfmsActiveAdministratorCount(?int $excludeUserId=null): int
{
    $systemId=cepfmsSystemId();
    if(!$systemId)return 0;

    try{
        $sql=
            'SELECT COUNT(*)
             FROM users u
             JOIN roles r ON r.id=u.role_id
             JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system
              AND usa.status="Active"
             WHERE r.name="Administrator"
               AND u.status="Active"
               AND u.deleted_at IS NULL';

        $params=[':system'=>$systemId];

        if($excludeUserId!==null){
            $sql.=' AND u.id<>:exclude_user';
            $params[':exclude_user']=$excludeUserId;
        }

        $q=db()->prepare($sql);
        $q->execute($params);
        return (int)$q->fetchColumn();
    }catch(Throwable $e){
        error_log('[CEPFMS active admin count] '.$e->getMessage());
        return 0;
    }
}

function cepfmsWouldRemoveFinalAdministrator(
    int $userId,
    int $newRoleId,
    string $newAccountStatus,
    string $newAccessStatus
): bool {
    try{
        $q=db()->prepare(
            'SELECT r.name role_name,u.status,
                    COALESCE(usa.status,"Inactive") access_status
             FROM users u
             JOIN roles r ON r.id=u.role_id
             LEFT JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system
             WHERE u.id=:user
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $q->execute([
            ':system'=>cepfmsSystemId(),
            ':user'=>$userId,
        ]);
        $current=$q->fetch();
        if(!$current)return false;

        $nr=db()->prepare('SELECT name FROM roles WHERE id=:id');
        $nr->execute([':id'=>$newRoleId]);
        $newRoleName=(string)($nr->fetchColumn()?:'');

        $currentlyAdmin=
            $current['role_name']==='Administrator'
            && $current['status']==='Active'
            && $current['access_status']==='Active';

        $willRemainAdmin=
            $newRoleName==='Administrator'
            && $newAccountStatus==='Active'
            && $newAccessStatus==='Active';

        return $currentlyAdmin
            && !$willRemainAdmin
            && cepfmsActiveAdministratorCount($userId)===0;
    }catch(Throwable $e){
        error_log('[CEPFMS final admin check] '.$e->getMessage());
        return true;
    }
}
