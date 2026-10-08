<?php
declare(strict_types=1);
// Usage: php database/check.php — verifies the installation (PHP, extensions, .env, database, tables, seed data, writable folders).
require dirname(__DIR__) . '/app/bootstrap.php';

$bad = 0;
$line = function (bool $ok, string $label, string $hint = '') use (&$bad): void {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . ($ok || $hint === '' ? '' : "\n         -> $hint") . "\n";
    $bad += $ok ? 0 : 1;
};

$line(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION . ' (need 8.1+)');
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'json'] as $ext) {
    $line(extension_loaded($ext), "PHP extension $ext", "enable extension=$ext in php.ini (XAMPP: Config > PHP (php.ini)), then restart Apache");
}
$line(is_file(BASE_PATH . '/.env'), '.env file', 'copy .env.example to .env');
echo 'Database: ' . Env::get('DB_USERNAME') . '@' . Env::get('DB_HOST') . ':' . Env::get('DB_PORT') . '/' . Env::get('DB_DATABASE') . "\n";

try {
    $v = Database::one('SELECT VERSION() v')['v'];
    $line(true, "Connected to MySQL/MariaDB $v");
} catch (Throwable $e) {
    $line(false, 'Database connection: ' . $e->getMessage(), 'start MySQL in the XAMPP Control Panel and run: php database/migrate.php --seed');
    exit(1);
}
$expected = ['roles', 'users', 'organizations', 'waste_types', 'units', 'supply_listings', 'buyer_demands', 'matches', 'offers', 'orders', 'deliveries', 'notifications', 'audit_logs', 'settings'];
$have = array_column(Database::all('SHOW TABLES'), 'Tables_in_' . Env::get('DB_DATABASE'));
$missing = array_diff($expected, $have);
$line(!$missing, count($have) . ' tables present', 'missing: ' . implode(', ', $missing) . ' — run: php database/migrate.php --seed');
if (!$missing) {
    foreach (['roles' => 6, 'units' => 4, 'waste_types' => 10, 'agricultural_products' => 5] as $t => $min) {
        $n = (int) Database::one("SELECT COUNT(*) c FROM $t")['c'];
        $line($n >= $min, "seed data: $t = $n", 'run: php database/migrate.php --seed');
    }
    $admins = (int) Database::one("SELECT COUNT(*) c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='admin' AND u.status='active'")['c'];
    $line($admins > 0, "admin accounts: $admins", 'run: php database/create-admin.php "Admin" +9647500000000 "password"');
}
foreach (['storage/logs', 'assets/uploads'] as $d) {
    @mkdir(BASE_PATH . "/$d", 0775, true);
    $line(is_writable(BASE_PATH . "/$d"), "folder writable: $d", 'give the web server write access');
}
echo $bad ? "\n$bad problem(s) found.\n" : "\nEverything is OK.\n";
exit($bad ? 1 : 0);
