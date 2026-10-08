<?php
declare(strict_types=1);
// Usage: php database/demo-seed.php — loads the plan's demo data (5 farmers, 3 factories, 10 supplies, 5 demands). Safe to re-run.
// All demo accounts use the password "demo12345". Phones: farmers +9647501000001..5, factories +9647502000001..3.
require dirname(__DIR__) . '/app/bootstrap.php';

const PW = 'demo12345';
$hash = password_hash(PW, PASSWORD_DEFAULT);
$u = static fn(string $code) => (int) Database::one('SELECT id FROM waste_types WHERE code = ?', [$code])['id'];
$unit = static fn(string $code) => (int) Database::one('SELECT id FROM units WHERE code = ?', [$code])['id'];
$mk = static function (string $role, string $name, string $phone) use ($hash): int {
    $e = UserRepository::findByLogin($phone);
    if ($e) return (int) $e['id'];
    $id = UserRepository::create(['role_id' => UserRepository::roleId($role), 'full_name' => $name, 'phone' => $phone, 'email' => null, 'password_hash' => $hash, 'preferred_language' => 'ku']);
    Database::query('INSERT INTO notification_preferences (user_id) VALUES (?)', [$id]);
    if ($role === 'farmer') Database::query('INSERT INTO farmer_profiles (user_id) VALUES (?)', [$id]);
    return $id;
};

if (Database::one("SELECT id FROM supply_listings WHERE description = 'demo-seed' LIMIT 1")) {
    echo "demo data already loaded\n";
    exit(0);
}

// name, phone, place, lat, lng
$farmers = [
    ['ئارام حەمە', '+9647501000001', 'هەڵەبجە', 35.1778, 45.9861], ['شیلان ئەحمەد', '+9647501000002', 'خورماڵ', 35.3000, 46.0500],
    ['کاوە محەمەد', '+9647501000003', 'سیروان', 35.1200, 46.1000], ['هێمن سەعید', '+9647501000004', 'سلێمانی', 35.5600, 45.4300],
    ['ڕێژنە عومەر', '+9647501000005', 'دەربەندیخان', 35.1100, 45.7000],
];
$f = [];
foreach ($farmers as $i => [$n, $p, $place, $lat, $lng]) {
    $id = $mk('farmer', $n, $p);
    $farm = Database::insert('INSERT INTO farms (farmer_user_id, name, area_donum, address, latitude, longitude) VALUES (?,?,?,?,?,?)', [$id, "کێڵگەی $place", 20 + $i * 7, $place, $lat, $lng]);
    $f[] = [$id, $farm, $place, $lat, $lng];
}
$factories = [['کارگەی ئاسمان', '+9647502000001', 'هەڵەبجە - ناوچەی پیشەسازی', 35.2000, 45.9800], ['کارگەی بەفرین', '+9647502000002', 'سلێمانی - سەرچنار', 35.5500, 45.4000], ['کارگەی زاگرۆس', '+9647502000003', 'کەلار', 34.6300, 45.3200]];
$b = [];
foreach ($factories as [$n, $p, $addr, $lat, $lng]) {
    $id = $mk('buyer', $n, $p);
    $org = Database::insert("INSERT INTO organizations (owner_user_id, organization_type, name, phone, address, latitude, longitude, status) VALUES (?,?,?,?,?,?,?, 'verified')", [$id, 'factory', $n, $p, $addr, $lat, $lng]);
    $b[] = [$id, $org, $addr, $lat, $lng];
}

// farmer index, waste code, unit, qty, grade, price type, price, days until available_from
$supplies = [
    [0, 'pomegranate_peel', 'ton', 5, 'A', 'fixed', 20000, 0], [0, 'pomegranate_seed', 'kg', 800, 'B', 'negotiable', null, 0],
    [1, 'walnut_shell', 'ton', 3, 'A', 'fixed', 35000, 0], [1, 'pomegranate_peel', 'ton', 2, 'B', 'fixed', 15000, 2],
    [2, 'olive_pomace', 'ton', 12, 'B', 'negotiable', null, 0], [2, 'olive_branches', 'ton', 4, 'C', 'free', null, 1],
    [3, 'grape_pomace', 'ton', 6, 'A', 'fixed', 18000, 0], [3, 'vine_prunings', 'ton', 8, 'C', 'free', null, 0],
    [4, 'straw', 'ton', 20, 'B', 'fixed', 9000, 0], [4, 'mixed_crop_residue', 'ton', 10, 'ungraded', 'negotiable', null, 3],
];
foreach ($supplies as [$fi, $code, $uc, $qty, $grade, $pt, $price, $delay]) {
    [$uid, $farm, $place, $lat, $lng] = $f[$fi];
    Database::insert(
        "INSERT INTO supply_listings (supplier_user_id, farm_id, waste_type_id, unit_id, quantity, available_quantity, quality_grade, price_type, price_per_unit, available_from,
            address, latitude, longitude, source_type, description, status) VALUES (?,?,?,?,?,?,?,?,?, CURDATE() + INTERVAL ? DAY, ?,?,?, 'manual', 'demo-seed', 'active')",
        [$uid, $farm, $u($code), $unit($uc), $qty, $qty, $grade, $pt, $pt === 'free' ? 0 : $price, $delay, $place, $lat, $lng]
    );
}
// buyer index, waste code, unit, qty, min quality, max price, days until required_until
$demands = [[0, 'pomegranate_peel', 'ton', 4, 'A', 25000, 30], [0, 'walnut_shell', 'ton', 2, 'any', 40000, 20], [1, 'olive_pomace', 'ton', 10, 'B', 12000, 45], [1, 'grape_pomace', 'ton', 5, 'any', 20000, 30], [2, 'straw', 'ton', 15, 'any', 10000, 60]];
foreach ($demands as [$bi, $code, $uc, $qty, $q, $max, $days]) {
    [$uid, $org, $addr, $lat, $lng] = $b[$bi];
    Database::insert(
        "INSERT INTO buyer_demands (buyer_user_id, organization_id, waste_type_id, unit_id, required_quantity, remaining_quantity, minimum_quality, max_price_per_unit,
            required_until, delivery_required, address, latitude, longitude, status) VALUES (?,?,?,?,?,?,?,?, CURDATE() + INTERVAL ? DAY, 1, ?,?,?, 'active')",
        [$uid, $org, $u($code), $unit($uc), $qty, $qty, $q, $max, $days, $addr, $lat, $lng]
    );
}
echo "demo data loaded: 5 farmers, 3 factories, 10 supplies, 5 demands (password: " . PW . ")\n";
