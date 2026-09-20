<?php
declare(strict_types=1);

require_once __DIR__.'/includes/auth.php';

$userId=currentUserId()?:null;
if($userId){
    cepfmsLogActivity($userId,'CEPFMS Logout','User signed out from the shared legislative session.');
}

$_SESSION=[];

if(ini_get('session.use_cookies')){
    $cookie=session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time()-42000,
        $cookie['path'],
        $cookie['domain'],
        $cookie['secure'],
        $cookie['httponly']
    );
}

session_destroy();

if(!headers_sent()){
    header('Clear-Site-Data: "cache"');
}

redirect(appUrl('login.php'));
