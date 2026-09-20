<?php
echo "SCRIPT VERSION: 2\n\n";
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';

header('Content-Type: text/plain');

$files = [
    __DIR__.'/database/migration_001_citizen_portal_foundation.sql',
    __DIR__.'/database/migration_002_account_security.sql',
];

$pdo = db();

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "MISSING: $file\n";
        continue;
    }
    echo "Running: $file\n";
    $sql = file_get_contents($file);

    // Remove any USE `...`; statements since we already connect to the right DB
    $sql = preg_replace('/^\s*USE\s+`?[a-zA-Z0-9_]+`?\s*;/mi', '', $sql);

    try {
        $pdo->exec($sql);
        echo "SUCCESS\n\n";
    } catch (PDOException $e) {
        echo "ERROR: ".$e->getMessage()."\n\n";
    }
}

echo "Done.";
