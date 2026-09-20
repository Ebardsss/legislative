<?php
declare(strict_types=1);

define('DB_HOST','127.0.0.1');
define('DB_NAME','legislative_management_db');
define('DB_USER','root');
define('DB_PASS','');
define('DB_CHARSET','utf8mb4');

define('APP_NAME','Legislative Citizen Portal');
define('APP_SHORT_NAME','Citizen Portal');
if (!defined('APP_URL')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $scheme = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
        $targetDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        if (str_starts_with($targetDir, $docRoot)) {
            $webPath = substr($targetDir, strlen($docRoot));
            define('APP_URL', $scheme . $host . rtrim($webPath, '/'));
        }
    }
    if (!defined('APP_URL')) {
        define('APP_URL', 'http://localhost/legislative/citizen_portal');
    }
}
define('APP_ROOT',dirname(__DIR__));
define('APP_TIMEZONE','Asia/Manila');

define('SESSION_NAME','citizen_portal_session');
define('SESSION_LIFETIME',60*60*8);

define('ROLE_STAKEHOLDER','Registered Stakeholder');
define('ROLE_PUBLIC','Public User');

$debugFlag=strtolower(trim((string)(getenv('CITIZEN_PORTAL_DEBUG')?:'0')));
define('APP_DEBUG',in_array($debugFlag,['1','true','yes','on'],true));

date_default_timezone_set(APP_TIMEZONE);

ini_set('session.gc_maxlifetime',(string)SESSION_LIFETIME);
ini_set('session.cookie_lifetime',(string)SESSION_LIFETIME);
ini_set('log_errors','1');
ini_set('error_log',APP_ROOT.'/logs/php_errors.log');

if(APP_DEBUG){
    error_reporting(E_ALL);
    ini_set('display_errors','1');
}else{
    error_reporting(E_ALL);
    ini_set('display_errors','0');
}
