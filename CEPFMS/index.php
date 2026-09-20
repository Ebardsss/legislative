<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

redirect(
    isLoggedIn()
        ? appUrl('dashboard.php')
        : appUrl('login.php')
);
