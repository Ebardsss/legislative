<?php
declare(strict_types=1);

require_once __DIR__.'/../config/app.php';

function e(?string $value): string
{
    return htmlspecialchars($value??'',ENT_QUOTES,'UTF-8');
}

function clean(?string $value): string
{
    return trim(strip_tags($value??''));
}

function redirect(string $url): never
{
    header('Location: '.$url);
    exit;
}

function appUrl(string $path=''): string
{
    return rtrim(APP_URL,'/').'/'.ltrim($path,'/');
}

function setFlash(string $type,string $message): void
{
    $_SESSION['flash'][]=['type'=>$type,'message'=>$message];
}

function getFlashMessages(): array
{
    $messages=$_SESSION['flash']??[];
    unset($_SESSION['flash']);
    return $messages;
}

function csrfToken(): string
{
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'.e(csrfToken()).'">';
}

function verifyCsrf(?string $token=null): bool
{
    $token=$token??($_POST['csrf_token']??$_SERVER['HTTP_X_CSRF_TOKEN']??'');
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'],$token);
}

function requireCsrf(): void
{
    if(verifyCsrf())return;
    http_response_code(403);
    if(isAjaxRequest()){
        jsonResponse(false,'Your form expired. Refresh the page and try again.',[],403);
    }
    exit('Your form expired. Refresh the page and try again.');
}

function isAjaxRequest(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
}

function jsonResponse(bool $success,string $message='',array $data=[],int $status=200): never
{
    if(ob_get_level()>0)ob_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(['success'=>$success,'message'=>$message],$data),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function formatDate(?string $date,string $format='M d, Y'): string
{
    if(!$date)return '—';
    $time=strtotime($date);
    return $time?date($format,$time):'—';
}

function formatDateTime(?string $date,string $format='M d, Y h:i A'): string
{
    return formatDate($date,$format);
}

function portalSystemId(): ?int
{
    static $id=null;
    static $loaded=false;
    if($loaded)return $id;
    $loaded=true;
    try{
        $q=db()->query("SELECT id FROM systems WHERE code='public_portal' LIMIT 1");
        $value=$q->fetchColumn();
        $id=$value!==false?(int)$value:null;
    }catch(Throwable $e){
        error_log('[Citizen Portal system id] '.$e->getMessage());
    }
    return $id;
}

function portalLog(?int $userId,string $action,string $details=''): void
{
    try{
        $systemId=portalSystemId();
        if(!$systemId)return;
        db()->prepare(
            'INSERT INTO activity_logs
             (user_id,system_id,action,details,ip_address,user_agent,created_at)
             VALUES(:user,:system,:action,:details,:ip,:agent,NOW())'
        )->execute([
            ':user'=>$userId,
            ':system'=>$systemId,
            ':action'=>$action,
            ':details'=>$details?:null,
            ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null,
            ':agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,
        ]);
    }catch(Throwable $e){
        error_log('[Citizen Portal activity] '.$e->getMessage());
    }
}

function portalLoginHash(string $value): string
{
    return hash('sha256',strtolower(trim($value)));
}

function portalIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45);
}

function portalRateLimited(string $email,string $ip,int $limit=5,int $minutes=15): bool
{
    try{
        $cutoff=date('Y-m-d H:i:s',time()-($minutes*60));
        $q=db()->prepare(
            'SELECT
               SUM(email_hash=:email_hash) email_failures,
               SUM(ip_hash=:ip_hash) ip_failures
             FROM citizen_portal_login_attempts
             WHERE was_successful=0
               AND attempted_at>=:cutoff'
        );
        $q->execute([
            ':email_hash'=>portalLoginHash($email),
            ':ip_hash'=>portalLoginHash($ip),
            ':cutoff'=>$cutoff,
        ]);
        $r=$q->fetch()?:[];
        return (int)($r['email_failures']??0)>=$limit
            || (int)($r['ip_failures']??0)>=$limit;
    }catch(Throwable $e){
        error_log('[Citizen Portal rate limit] '.$e->getMessage());
        return false;
    }
}

function portalRecordLoginAttempt(string $email,string $ip,bool $success): void
{
    try{
        $pdo=db();
        $pdo->prepare(
            'INSERT INTO citizen_portal_login_attempts
             (email_hash,ip_hash,was_successful,attempted_at)
             VALUES(:email,:ip,:success,NOW())'
        )->execute([
            ':email'=>portalLoginHash($email),
            ':ip'=>portalLoginHash($ip),
            ':success'=>$success?1:0,
        ]);

        if($success){
            $pdo->prepare(
                'DELETE FROM citizen_portal_login_attempts
                 WHERE was_successful=0
                   AND (email_hash=:email_hash OR ip_hash=:ip_hash)'
            )->execute([
                ':email_hash'=>portalLoginHash($email),
                ':ip_hash'=>portalLoginHash($ip),
            ]);
        }

        $pdo->exec(
            'DELETE FROM citizen_portal_login_attempts
             WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY)'
        );
    }catch(Throwable $e){
        error_log('[Citizen Portal login attempt] '.$e->getMessage());
    }
}

function portalPasswordIsStrong(string $password): bool
{
    return strlen($password)>=10
        && preg_match('/[A-Z]/',$password)===1
        && preg_match('/[a-z]/',$password)===1
        && preg_match('/[0-9]/',$password)===1;
}

function portalDestroySession(): void
{
    $_SESSION=[];

    if(ini_get('session.use_cookies')){
        $params=session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time()-42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    if(session_status()===PHP_SESSION_ACTIVE){
        session_destroy();
    }
}

function portalSecurityState(int $userId): ?array
{
    try{
        $pdo=db();

        $pdo->prepare(
            'INSERT IGNORE INTO citizen_portal_account_security
             (user_id,session_version,created_at,updated_at)
             VALUES(:user,1,NOW(),NOW())'
        )->execute([':user'=>$userId]);

        $q=$pdo->prepare(
            'SELECT user_id,session_version,password_changed_at,
                    last_profile_update_at,portal_access_deactivated_at,
                    created_at,updated_at
             FROM citizen_portal_account_security
             WHERE user_id=:user
             LIMIT 1'
        );
        $q->execute([':user'=>$userId]);

        $row=$q->fetch();
        return $row?:null;
    }catch(Throwable $e){
        error_log('[Citizen Portal security state] '.$e->getMessage());
        return null;
    }
}

function portalBumpSessionVersion(
    int $userId,
    bool $passwordChanged=false,
    bool $portalDeactivated=false
): int {
    $pdo=db();

    $state=portalSecurityState($userId);
    if(!$state){
        throw new RuntimeException('Citizen Portal account-security migration is not installed.');
    }

    $sets=[
        'session_version=session_version+1',
        'updated_at=NOW()',
    ];

    if($passwordChanged){
        $sets[]='password_changed_at=NOW()';
    }

    if($portalDeactivated){
        $sets[]='portal_access_deactivated_at=NOW()';
    }

    $pdo->prepare(
        'UPDATE citizen_portal_account_security
         SET '.implode(',',$sets).'
         WHERE user_id=:user'
    )->execute([':user'=>$userId]);

    $q=$pdo->prepare(
        'SELECT session_version
         FROM citizen_portal_account_security
         WHERE user_id=:user
         LIMIT 1'
    );
    $q->execute([':user'=>$userId]);

    return max(1,(int)$q->fetchColumn());
}

function portalMarkProfileUpdated(int $userId): void
{
    $state=portalSecurityState($userId);
    if(!$state)return;

    db()->prepare(
        'UPDATE citizen_portal_account_security
         SET last_profile_update_at=NOW(),updated_at=NOW()
         WHERE user_id=:user'
    )->execute([':user'=>$userId]);
}

