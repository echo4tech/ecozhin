<?php
declare(strict_types=1);
// Usage: php database/create-admin.php "Full Name" +9647500000000 'password'
require dirname(__DIR__) . '/app/bootstrap.php';

[, $name, $phone, $password] = $argv + [null, null, null, null];
if (!$name || !$phone || !$password || strlen($password) < 8) {
    fwrite(STDERR, "Usage: php database/create-admin.php NAME PHONE PASSWORD(min 8)\n");
    exit(1);
}
$id = UserRepository::create([
    'role_id' => UserRepository::roleId('admin'), 'full_name' => $name, 'phone' => $phone,
    'email' => null, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'preferred_language' => 'ku',
]);
echo "admin created: id=$id\n";
