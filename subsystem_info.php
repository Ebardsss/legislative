<?php
declare(strict_types=1);

/**
 * subsystem_info.php
 * ------------------------------------------------------------------
 * Legacy Roadmap Router: Automatically redirects any incoming requests
 * to the corresponding active Subsystem dashboard.
 * ------------------------------------------------------------------
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$scheme = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$portalPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/legislative')), '/');
$portalUrl = $scheme . $host . $portalPath;

$targetCode = strtolower(trim((string)($_GET['sys'] ?? $_GET['code'] ?? '')));

$redirectMap = [
    'slmms'   => $portalUrl . '/SLMMS/dashboard.php',
    'cmas'    => $portalUrl . '/CMAS/dashboard.php',
    'lrdms'   => $portalUrl . '/LRDMS/dashboard.php',
    'lahrs'   => $portalUrl . '/LAHRS/dashboard.php',
    'lrpaies' => $portalUrl . '/LRPAIES/dashboard.php',
    'orlms'   => $portalUrl . '/ORLMS/',
    'lacms'   => $portalUrl . '/LACMS/',
    'vqdss'   => $portalUrl . '/vqdss/',
    'lph'     => $portalUrl . '/lph/',
    'cepfms'  => $portalUrl . '/CEPFMS/',
];

if (isset($redirectMap[$targetCode])) {
    header('Location: ' . $redirectMap[$targetCode], true, 302);
    exit;
}

header('Location: ' . $portalUrl . '/index.php', true, 302);
exit;
