<?php
declare(strict_types=1);
// Usage: php database/migrate.php [--seed]   (idempotent: CREATE TABLE IF NOT EXISTS / INSERT IGNORE)
require dirname(__DIR__) . '/app/bootstrap.php';

$files = ['schema.sql'];
if (in_array('--seed', $argv, true)) {
    $files[] = 'seeds.sql';
}
foreach ($files as $f) {
    Database::pdo()->exec(file_get_contents(__DIR__ . "/$f"));
    echo "applied $f\n";
}
