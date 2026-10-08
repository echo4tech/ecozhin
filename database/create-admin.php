<?php
declare(strict_types=1);
// Usage: php database/create-admin.php "Full Name" +9647500000000 'password'   (safe to re-run: updates the password if the phone already exists)
require dirname(__DIR__) . '/app/bootstrap.php';

[, $name, $phone, $password] = $argv + [null, null, null, null];
if (!$name || !$phone || !$password || strlen($password) < 8) {
    fwrite(STDERR, "Usage: php database/create-admin.php NAME PHONE PASSWORD(min 8 chars)\n");
    exit(1);
}
if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
    fwrite(STDERR, "Phone must be 7-15 digits, optionally starting with +\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = UserRepository::findByLogin($phone);
if ($existing) {
    Database::query('UPDATE users SET password_hash = ?, role_id = ?, status = \'active\' WHERE id = ?', [$hash, UserRepository::roleId('admin'), $existing['id']]);
    echo "existing user {$existing['id']} ($phone) is now an active admin with the new password\n";
    exit(0);
}
$id = UserRepository::create([
    'role_id' => UserRepository::roleId('admin'), 'full_name' => $name, 'phone' => $phone,
    'email' => null, 'password_hash' => $hash, 'preferred_language' => 'ku',
]);
echo "admin created: id=$id login=$phone\n";
