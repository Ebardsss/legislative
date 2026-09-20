<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$now = time();
$lastActivity = (int)($_SESSION['last_activity'] ?? 0);

if (
    $lastActivity > 0 &&
    ($now - $lastActivity) > SESSION_IDLE_TIMEOUT
) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookie['path'],
            $cookie['domain'],
            $cookie['secure'],
            $cookie['httponly']
        );
    }

    session_destroy();

    session_name(SESSION_NAME);
    session_start();

    $_SESSION['auth_expired'] = true;
}

if (!empty($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = $now;
}
