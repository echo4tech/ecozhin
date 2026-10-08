<?php
declare(strict_types=1);
// Usage: php -S 127.0.0.1:8000 router.php &  then  php tests/api_test.php [base_url]
// Needs a seeded DB and an admin: phone +9647500000001 / adminpass1 (see database/create-admin.php).
$base = $argv[1] ?? 'http://127.0.0.1:8000';
require __DIR__ . '/helpers.php';

$suffix = (string) random_int(10000000, 99999999);
$phone = "+96475$suffix";

$c = client($base);
[$code, $r] = $c('POST', '/auth/login', ['login' => 'x', 'password' => 'y'], false);
check('POST without CSRF rejected (419)', $code === 419, [$code, $r]);

[$code, $r] = $c('GET', '/auth/me');
check('me unauthenticated -> 401', $code === 401, $r);

[$code, $r] = $c('POST', '/auth/register', ['full_name' => 'A', 'phone' => 'abc', 'password' => 'short', 'role' => 'admin']);
check('register validation + admin role blocked (422)', $code === 422 && isset($r['errors']['role'], $r['errors']['phone'], $r['errors']['password']), $r);

[$code, $r] = $c('POST', '/auth/register', ['full_name' => 'Test Farmer', 'phone' => $phone, 'password' => 'secret123', 'role' => 'farmer']);
check('register farmer (201)', $code === 201 && $r['data']['role'] === 'farmer' && !isset($r['data']['password_hash']), $r);

[$code, $r] = $c('GET', '/auth/me');
check('me after register', $code === 200 && $r['data']['phone'] === $phone, $r);

[$code, $r] = $c('POST', '/auth/register', ['full_name' => 'Dup', 'phone' => $phone, 'password' => 'secret123', 'role' => 'buyer']);
check('duplicate phone rejected', $code === 422, $r);

[$code, $r] = $c('POST', '/waste-types', ['code' => 'x_y', 'name_ku' => 'x']);
check('farmer cannot create waste type (403)', $code === 403, $r);

[$code] = $c('POST', '/auth/logout');
[$code, $r] = $c('GET', '/auth/me');
check('logout clears session', $code === 401, $r);

[$code, $r] = $c('POST', '/auth/login', ['login' => $phone, 'password' => 'wrongpass']);
check('wrong password -> 401', $code === 401, $r);
[$code, $r] = $c('POST', '/auth/login', ['login' => $phone, 'password' => 'secret123']);
check('login ok', $code === 200, $r);

[$code, $r] = $c('GET', '/units');
check('units seeded', $code === 200 && count($r['data']) >= 4, $r);
[$code, $r] = $c('GET', '/waste-types?product_id=3');
check('waste types filter by product', $code === 200 && count($r['data']) === 2, $r);
[$code, $r] = $c('GET', '/geo/districts?governorate_id=4');
check('districts filter', $code === 200 && count($r['data']) === 2, $r);
[$code, $r] = $c('GET', '/settings/public');
check('public settings hide private', $code === 200 && isset($r['data']['platform_name']) && !isset($r['data']['service_fee_percent']), $r);
[$code, $r] = $c('GET', '/nope');
check('unknown route 404', $code === 404, $r);

$a = client($base);
[$code, $r] = $a('POST', '/auth/login', ['login' => '+9647500000001', 'password' => 'adminpass1']);
check('admin login', $code === 200 && $r['data']['role'] === 'admin', $r);
$wcode = 'test_' . $suffix;
[$code, $r] = $a('POST', '/waste-types', ['code' => $wcode, 'name_ku' => 'تێست', 'agricultural_product_id' => 1, 'default_unit_id' => 2]);
check('admin creates waste type', $code === 201, $r);
$id = $r['data']['id'] ?? 0;
[$code, $r] = $a('POST', '/waste-types', ['code' => $wcode, 'name_ku' => 'تێست']);
check('duplicate waste code rejected', $code === 422, $r);
[$code, $r] = $a('POST', '/waste-types', ['code' => 'bad_ref', 'name_ku' => 'x', 'default_unit_id' => 999]);
check('unknown unit rejected', $code === 422, $r);
[$code, $r] = $a('PUT', "/waste-types/$id", ['name_ku' => 'نوێ', 'active' => false]);
check('admin updates waste type', $code === 200, $r);
[$code, $r] = $a('GET', '/waste-types');
check('inactive waste type hidden from catalog', !in_array($id, array_column($r['data'], 'id'), true), $r);
[$code, $r] = $a('POST', '/products', ['name_ku' => 'سێو', 'name_en' => 'Apple', 'category' => 'fruit']);
check('admin creates product', $code === 201, $r);

// rate limit: 5 attempts / 15 min per ip+login
$rl = client($base);
$last = 0;
for ($i = 0; $i < 7; $i++) { [$last] = $rl('POST', '/auth/login', ['login' => "rl$suffix", 'password' => 'nopenope']); }
check('login rate limited (429)', $last === 429, $last);

echo $fail ? "\n$fail FAILED\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
