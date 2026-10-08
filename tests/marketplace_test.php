<?php
declare(strict_types=1);
// Phase 3: farms, supply listings, marketplace search, images, buyer demands.
$base = $argv[1] ?? 'http://127.0.0.1:8000';
require __DIR__ . '/helpers.php';

$sfx = (string) random_int(10000000, 99999999);
function signup(string $base, string $role, string $phone): Closure
{
    $c = client($base);
    [$code, $r] = $c('POST', '/auth/register', ['full_name' => "T $role", 'phone' => $phone, 'password' => 'secret123', 'role' => $role]);
    if ($code !== 201) { fwrite(STDERR, "signup failed: " . json_encode($r) . "\n"); exit(2); }
    return $c;
}
$farmer = signup($base, 'farmer', "+96476$sfx");
$farmer2 = signup($base, 'farmer', "+96477$sfx");
$buyer = signup($base, 'buyer', "+96478$sfx");
$anon = client($base);

// Farms
[$code, $r] = $buyer('POST', '/farms', ['name' => 'x']);
check('buyer cannot create farm (403)', $code === 403, $r);
[$code, $r] = $farmer('POST', '/farms', ['name' => 'Khurmal farm', 'area_donum' => 12.5, 'latitude' => 35.3, 'longitude' => 46.0]);
check('farmer creates farm', $code === 201, $r);
$farmId = $r['data']['id'];
[$code, $r] = $farmer('POST', '/farms', ['name' => 'bad', 'latitude' => 120]);
check('invalid latitude rejected', $code === 422, $r);
[$code, $r] = $farmer2('PUT', "/farms/$farmId", ['name' => 'stolen']);
check('other farmer cannot edit farm (404)', $code === 404, $r);

// Supply
$base_s = ['waste_type_id' => 1, 'unit_id' => 2, 'quantity' => 5, 'available_from' => date('Y-m-d'), 'price_type' => 'fixed', 'price_per_unit' => 20000,
    'quality_grade' => 'A', 'latitude' => 35.30, 'longitude' => 46.00, 'address' => 'Halabja road', 'farm_id' => $farmId];
[$code, $r] = $buyer('POST', '/supplies', $base_s);
check('buyer cannot create supply (403)', $code === 403, $r);
[$code, $r] = $farmer('POST', '/supplies', ['quantity' => -1]);
check('supply validation (422)', $code === 422 && isset($r['errors']['waste_type_id'], $r['errors']['quantity']), $r);
[$code, $r] = $farmer('POST', '/supplies', array_merge($base_s, ['price_per_unit' => null]));
check('fixed price needs price (422)', $code === 422, $r);
[$code, $r] = $farmer2('POST', '/supplies', $base_s);
check("can't use someone else's farm (422)", $code === 422 && isset($r['errors']['farm_id']), $r);
[$code, $r] = $farmer('POST', '/supplies', $base_s);
check('farmer creates draft supply', $code === 201, $r);
$sid = $r['data']['id'];

[$code, $r] = $anon('GET', "/supplies/$sid");
check('draft hidden from public (404)', $code === 404, $r);
[$code, $r] = $farmer('GET', "/supplies/$sid");
check('owner sees draft', $code === 200 && $r['data']['status'] === 'draft' && $r['data']['available_quantity'] == 5, $r);
[$code, $r] = $anon('GET', '/supplies?waste_type_id=1&per_page=100');
check('draft not in marketplace', !in_array($sid, array_column($r['data']['items'], 'id')), $r);

[$code, $r] = $farmer2('POST', "/supplies/$sid/publish");
check('non-owner cannot publish (404)', $code === 404, $r);
[$code, $r] = $farmer('POST', "/supplies/$sid/publish");
check('owner publishes', $code === 200, $r);
[$code, $r] = $farmer('POST', "/supplies/$sid/publish");
check('double publish rejected (409)', $code === 409, $r);
[$code, $r] = $anon('GET', "/supplies/$sid");
check('active visible to public, no phone leaked', $code === 200 && !isset($r['data']['phone']) && $r['data']['supplier_name'] === 'T farmer', $r);

// Search / filters
$far = array_merge($base_s, ['latitude' => 36.20, 'longitude' => 44.00, 'price_per_unit' => 5000, 'quality_grade' => 'C', 'publish' => true, 'farm_id' => null]);
[$code, $r] = $farmer('POST', '/supplies', $far);
$farId = $r['data']['id'];
$ids = fn($path) => array_column(($anon('GET', $path)[1]['data']['items'] ?? []), 'id');
check('filter by quality', in_array($sid, $ids('/supplies?quality=A')) && !in_array($farId, $ids('/supplies?quality=A')));
check('filter by max_price', in_array($farId, $ids('/supplies?max_price=6000')) && !in_array($sid, $ids('/supplies?max_price=6000')));
check('radius filter', in_array($sid, $ids('/supplies?lat=35.3&lng=46.0&radius_km=20')) && !in_array($farId, $ids('/supplies?lat=35.3&lng=46.0&radius_km=20')));
$near = $anon('GET', '/supplies?lat=35.3&lng=46.0&sort=nearest&waste_type_id=1')[1]['data']['items'];
check('nearest sort + distance_km', $near && $near[0]['distance_km'] < 1, $near[0] ?? null);
check('price_asc sort', ($ps = $anon('GET', '/supplies?sort=price_asc&waste_type_id=1&per_page=100')[1]['data']['items']) && $ps[0]['price_per_unit'] <= end($ps)['price_per_unit'], null);
check('text search', in_array($sid, $ids('/supplies?q=' . urlencode('Halabja road'))));
check("LIKE wildcard escaped (q=%)", $ids('/supplies?q=%25') === []);
[$code, $r] = $anon('GET', '/supplies?quality=Z&sort=bogus');
check('bad filters rejected (422)', $code === 422, $r);
[$code, $r] = $anon('GET', '/supplies?per_page=1');
check('pagination', $code === 200 && count($r['data']['items']) === 1 && $r['data']['total'] >= 2, $r);

// Edit / cancel
[$code, $r] = $farmer('PUT', "/supplies/$sid", array_merge($base_s, ['quantity' => 8]));
check('owner edits quantity', $code === 200, $r);
check('available quantity follows edit', $farmer('GET', "/supplies/$sid")[1]['data']['available_quantity'] == 8);
[$code, $r] = $farmer('DELETE', "/supplies/$farId");
check('owner cancels', $code === 200, $r);
check('cancelled leaves marketplace', !in_array($farId, $ids('/supplies?waste_type_id=1&per_page=100')));
[$code, $r] = $farmer('PUT', "/supplies/$farId", $base_s);
check('cancelled cannot be edited (409)', $code === 409, $r);
[$code, $r] = $farmer('GET', '/supplies/mine?status=cancelled');
check('mine lists own incl. cancelled', $code === 200 && in_array($farId, array_column($r['data']['items'], 'id')), $r);

// Images
$png = tempnam(sys_get_temp_dir(), 'img') . '.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$txt = tempnam(sys_get_temp_dir(), 'bad') . '.png';
file_put_contents($txt, '<?php echo 1;');
[$code, $r] = $farmer('POST', "/supplies/$sid/images", ['image' => new CURLFile($txt, 'image/png', 'a.png')]);
check('non-image disguised as png rejected (422)', $code === 422, $r);
[$code, $r] = $farmer2('POST', "/supplies/$sid/images", ['image' => new CURLFile($png, 'image/png', 'a.png')]);
check('non-owner cannot upload (404)', $code === 404, $r);
[$code, $r] = $farmer('POST', "/supplies/$sid/images", ['image' => new CURLFile($png, 'image/png', 'a.png')]);
check('owner uploads image', $code === 201 && str_ends_with($r['data']['file_path'], '.png'), $r);
$img = file_get_contents($base . $r['data']['file_path']);
check('image is served', $img !== false && str_starts_with($img, "\x89PNG"));
check('detail shows image', count($anon('GET', "/supplies/$sid")[1]['data']['images']) === 1);

// Demands
[$code, $r] = $farmer('POST', '/demands', ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 4]);
check('farmer cannot create demand (403)', $code === 403, $r);
[$code, $r] = $buyer('POST', '/demands', ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 0]);
check('demand validation (422)', $code === 422, $r);
[$code, $r] = $buyer('POST', '/demands', ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 4, 'max_price_per_unit' => 25000, 'latitude' => 35.25, 'longitude' => 45.95, 'publish' => true]);
check('buyer creates + publishes demand', $code === 201, $r);
$did = $r['data']['id'];
[$code, $r] = $anon('GET', '/demands');
check('demands require login (401)', $code === 401, $r);
[$code, $r] = $farmer('GET', '/demands?waste_type_id=1&per_page=100');
check('supplier sees open demands', $code === 200 && in_array($did, array_column($r['data']['items'], 'id')), $r);
[$code, $r] = $buyer('POST', '/demands', ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 1]);
$draftD = $r['data']['id'];
[$code, $r] = $farmer('GET', "/demands/$draftD");
check('draft demand hidden from others (404)', $code === 404, $r);
[$code, $r] = $farmer('PUT', "/demands/$did", ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 9]);
check('non-buyer cannot edit demand (403)', $code === 403, $r);
$buyer2 = signup($base, 'buyer', "+96479$sfx");
[$code, $r] = $buyer2('PUT', "/demands/$did", ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 9]);
check('other buyer cannot edit demand (404)', $code === 404, $r);
[$code, $r] = $buyer('PUT', "/demands/$did", ['waste_type_id' => 1, 'unit_id' => 2, 'required_quantity' => 9]);
check('buyer edits demand', $code === 200, $r);
[$code, $r] = $buyer('DELETE', "/demands/$did");
check('buyer cancels demand', $code === 200, $r);
[$code, $r] = $buyer('GET', '/demands/mine?status=cancelled');
check('demands/mine', $code === 200 && in_array($did, array_column($r['data']['items'], 'id')), $r);

echo $fail ? "\n$fail FAILED\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
