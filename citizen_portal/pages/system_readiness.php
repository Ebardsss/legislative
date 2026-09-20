<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

redirect(appUrl('pages/system_health.php'));
