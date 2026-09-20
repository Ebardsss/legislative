<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cepfms_helpers.php';

function logActivity(string $action, string $details = '', ?int $userId = null): void
{
    cepfmsLogActivity(
        $userId ?? (currentUserId() ?: null),
        $action,
        $details
    );
}
