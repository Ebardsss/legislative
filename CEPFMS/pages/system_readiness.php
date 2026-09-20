<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.system_health.view');

redirect(appUrl('pages/system_health.php'));
