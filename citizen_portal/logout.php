<?php
declare(strict_types=1);

require_once __DIR__.'/includes/auth.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    redirect(isLoggedIn()?appUrl('dashboard.php'):appUrl('index.php'));
}

requireCsrf();

if(isLoggedIn()){
    portalLog(currentUserId(),'Citizen Portal Logout','Citizen signed out.');
}

portalDestroySession();
redirect(appUrl('index.php'));
