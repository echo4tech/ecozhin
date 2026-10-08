<?php
declare(strict_types=1);
// Usage: php database/migrate.php [--seed]
// Creates the database if needed, then applies schema.sql (idempotent: CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
require dirname(__DIR__) . '/app/bootstrap.php';

$db = Env::get('DB_DATABASE', 'circular_economy');
if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
    fwrite(STDERR, "Invalid DB_DATABASE name\n");
    exit(1);
}
try {
    $root = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306')),
        Env::get('DB_USERNAME', ''), Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "database `$db` ready\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Could not connect/create database: ' . $e->getMessage() . "\nCheck DB_* values in .env and that MySQL is running.\n");
    exit(1);
}

$files = ['schema.sql'];
if (in_array('--seed', $argv, true)) {
    $files[] = 'seeds.sql';
}
foreach ($files as $f) {
    Database::pdo()->exec(file_get_contents(__DIR__ . "/$f"));
    echo "applied $f\n";
}
