<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function appUrl(string $path = ''): string
{
    $path = trim($path);

    return $path === ''
        ? rtrim(APP_URL, '/')
        : rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function citizenPortalUrl(string $path = 'index.php'): string
{
    $portalBase = rtrim(dirname(APP_URL), '/');
    return $portalBase . '/citizen_portal/' . ltrim($path, '/');
}

function vendorAsset(string $localPath, string $cdnFallback): string
{
    $full = APP_ROOT . '/assets/vendor/' . ltrim($localPath, '/');

    return is_file($full)
        ? appUrl('assets/vendor/' . ltrim($localPath, '/'))
        : $cdnFallback;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function csrfToken(): string
{
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        e(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool
{
    $session = $_SESSION['csrf_token'] ?? '';

    return is_string($token)
        && is_string($session)
        && $token !== ''
        && hash_equals($session, $token);
}

function requireCsrf(): void
{
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        return;
    }

    if (isAjaxRequest()) {
        jsonResponse(false, 'Your form token expired. Refresh the page and try again.', [], 419);
    }

    http_response_code(419);
    exit('419 - Invalid or expired CSRF token.');
}

function isAjaxRequest(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))
        === 'xmlhttprequest';
}

function jsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode(
        array_merge(
            ['success' => $success, 'message' => $message],
            $data
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function clean(mixed $value): string
{
    return trim((string)$value);
}

function normalizeRole(string $role): string
{
    $role = strtoupper(trim($role));
    $role = preg_replace('/^ROLE_/', '', $role) ?? $role;
    $role = str_replace([' ', '-'], '_', $role);

    if (in_array($role, ['ADMINISTRATOR', 'SYSTEM_ADMIN'], true)) {
        return 'ADMIN';
    }

    if (in_array($role, ['LEGISLATIVE_STAFF', 'SECRETARIAT', 'CLERK'], true)) {
        return 'STAFF';
    }

    if (in_array($role, ['COMMITTEE_MEMBER', 'COMMITTEE_CHAIR'], true)) {
        return 'COMMITTEE';
    }

    if ($role === 'REGISTERED_STAKEHOLDER') {
        return 'STAKEHOLDER';
    }

    if ($role === 'PUBLIC_USER') {
        return 'PUBLIC';
    }

    return $role;
}

function currentUser(): array
{
    $nested = $_SESSION['user'] ?? [];
    if (!is_array($nested)) {
        $nested = [];
    }

    return [
        'id' => (int)(
            $_SESSION['user_id']
            ?? $nested['id']
            ?? 0
        ),
        'full_name' => (string)(
            $_SESSION['full_name']
            ?? $nested['full_name']
            ?? ''
        ),
        'email' => (string)(
            $_SESSION['email']
            ?? $nested['email']
            ?? ''
        ),
        'role_id' => (int)(
            $_SESSION['role_id']
            ?? $nested['role_id']
            ?? 0
        ),
        'role' => (string)(
            $_SESSION['role']
            ?? $_SESSION['role_name']
            ?? $nested['role']
            ?? $nested['role_name']
            ?? ''
        ),
    ];
}

function currentUserId(): int
{
    return currentUser()['id'];
}

function currentUserName(): string
{
    return currentUser()['full_name'] ?: 'System User';
}

function currentUserEmail(): string
{
    return currentUser()['email'];
}

function currentRole(): string
{
    return currentUser()['role'];
}

function currentRoleLabel(): string
{
    $role=currentRole();
    return $role!==''?$role:'Shared User';
}

function isAdmin(): bool
{
    return normalizeRole(currentRole()) === normalizeRole(ROLE_ADMIN);
}

function tableExists(string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table'
        );

        $stmt->execute([':table' => $table]);

        return $cache[$table] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        error_log('[CEPFMS tableExists] ' . $exception->getMessage());
        return $cache[$table] = false;
    }
}

function countTableRows(string $table): int
{
    if (!tableExists($table)) {
        return 0;
    }

    $allowed = [
        'cef_submissions',
        'cef_feedback_submissions',
        'cef_proposals',
        'cef_complaints',
        'cef_moderation_reviews',
        'cef_assignments',
        'cef_responses',
        'cef_analytics_snapshots',
        'cef_notifications',
        'cef_legislative_referrals',
    ];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    return (int)db()
        ->query("SELECT COUNT(*) FROM `$table`")
        ->fetchColumn();
}

function formatDate(?string $date): string
{
    if (!$date) {
        return 'Not recorded';
    }

    $time = strtotime($date);
    return $time ? date('M j, Y', $time) : $date;
}

function formatDateTime(?string $date): string
{
    if (!$date) {
        return 'Not recorded';
    }

    $time = strtotime($date);
    return $time ? date('M j, Y g:i A', $time) : $date;
}

function handleUpload(array $file, string $folder): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Choose a valid file to upload.'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'The file exceeds the allowed upload size.'];
    }

    $original = basename((string)($file['name'] ?? 'upload'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_UPLOAD_EXT, true)) {
        return ['success' => false, 'message' => 'This file type is not allowed.'];
    }

    $safeFolder = trim(
        preg_replace('/[^a-zA-Z0-9_\/-]/', '', $folder) ?? '',
        '/'
    );

    $targetDir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR);
    if ($safeFolder !== '') {
        $targetDir .= DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $safeFolder);
    }

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['success' => false, 'message' => 'Unable to create the upload directory.'];
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $extension;
    $target = $targetDir . DIRECTORY_SEPARATOR . $stored;

    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        return ['success' => false, 'message' => 'Unable to save the uploaded file.'];
    }

    return [
        'success' => true,
        'file_name' => $original,
        'stored_name' => $stored,
        'file_path' => ($safeFolder !== '' ? $safeFolder . '/' : '') . $stored,
        'size' => $size,
    ];
}


function paginate(int $total,int $page,int $perPage=DEFAULT_PAGE_SIZE): array
{
    $perPage=max(1,min(200,$perPage));
    $pages=max(1,(int)ceil(max(0,$total)/$perPage));
    $page=max(1,min($pages,$page));

    return [
        'total'=>$total,
        'page'=>$page,
        'perPage'=>$perPage,
        'pages'=>$pages,
        'offset'=>($page-1)*$perPage,
    ];
}

function renderPagination(
    array $info,
    string $baseUrl,
    array $query=[]
): string {
    if(($info['pages']??1)<=1)return '';

    $current=(int)$info['page'];
    $pages=(int)$info['pages'];
    $start=max(1,$current-2);
    $end=min($pages,$current+2);

    $makeUrl=static function(int $page) use($baseUrl,$query): string {
        $params=array_filter(
            array_merge($query,['page'=>$page]),
            static fn($value): bool => $value!==null&&$value!==''
        );
        return $baseUrl.'?'.http_build_query($params);
    };

    $html='<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $html.='<li class="page-item '.($current<=1?'disabled':'').'"><a class="page-link" href="'.
        e($makeUrl(max(1,$current-1))).'">Previous</a></li>';

    for($i=$start;$i<=$end;$i++){
        $html.='<li class="page-item '.($i===$current?'active':'').'"><a class="page-link" href="'.
            e($makeUrl($i)).'">'.$i.'</a></li>';
    }

    $html.='<li class="page-item '.($current>=$pages?'disabled':'').'"><a class="page-link" href="'.
        e($makeUrl(min($pages,$current+1))).'">Next</a></li>';
    $html.='</ul></nav>';

    return $html;
}
