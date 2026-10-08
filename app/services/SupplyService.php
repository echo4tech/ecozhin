<?php
declare(strict_types=1);

final class SupplyService
{
    public const SUPPLIER_ROLES = ['farmer', 'collection_center', 'admin'];

    private const RULES = [
        'waste_type_id' => 'required|int', 'unit_id' => 'required|int',
        'quantity' => 'required|numeric|gt:0', 'quality_grade' => 'in:A,B,C,ungraded',
        'moisture_percent' => 'numeric|gte:0|max:100', 'price_type' => 'in:fixed,negotiable,free,request_offer',
        'price_per_unit' => 'numeric|gte:0', 'available_from' => 'required|date', 'available_until' => 'date',
        'address' => 'string|max:1000', 'latitude' => 'lat', 'longitude' => 'lng', 'description' => 'string|max:2000',
        'farm_id' => 'int',
    ];

    /** Validates input and returns DB-ready fields. */
    public static function prepare(array $in, array $user, string $source = 'manual'): array
    {
        $errors = Validator::validate($in, self::RULES);
        $d = [
            'waste_type_id' => (int) ($in['waste_type_id'] ?? 0),
            'unit_id' => (int) ($in['unit_id'] ?? 0),
            'farm_id' => Fields::nullable($in, 'farm_id') === null ? null : (int) $in['farm_id'],
            'quantity' => $in['quantity'] ?? 0,
            'quality_grade' => $in['quality_grade'] ?? 'ungraded',
            'moisture_percent' => Fields::nullableNum($in, 'moisture_percent'),
            'price_type' => $in['price_type'] ?? 'negotiable',
            'price_per_unit' => Fields::nullableNum($in, 'price_per_unit'),
            'available_from' => $in['available_from'] ?? null,
            'available_until' => Fields::nullable($in, 'available_until'),
            'address' => Fields::nullable($in, 'address'),
            'latitude' => Fields::nullableNum($in, 'latitude'),
            'longitude' => Fields::nullableNum($in, 'longitude'),
            'description' => Fields::nullable($in, 'description'),
            'source_type' => $source,
        ];
        if (($d['latitude'] === null) !== ($d['longitude'] === null)) {
            $errors['latitude'][] = 'latitude and longitude must be given together';
        }
        if ($d['price_type'] === 'fixed' && $d['price_per_unit'] === null) {
            $errors['price_per_unit'][] = 'price_per_unit is required for fixed price';
        }
        if ($d['price_type'] === 'free') {
            $d['price_per_unit'] = 0;
        }
        if ($d['available_until'] && $d['available_from'] && $d['available_until'] < $d['available_from']) {
            $errors['available_until'][] = 'available_until must not be before available_from';
        }
        if (!$errors) {
            Fields::ensureExists('waste_types', $d['waste_type_id'], 'waste_type_id', $errors, 'AND active = 1');
            Fields::ensureExists('units', $d['unit_id'], 'unit_id', $errors);
            if ($d['farm_id'] !== null) {
                $farm = FarmRepository::find($d['farm_id']);
                if (!$farm || ((int) $farm['farmer_user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
                    $errors['farm_id'][] = 'Unknown farm';
                }
            }
        }
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        return $d;
    }

    public static function create(array $user, array $in, string $source = 'manual'): int
    {
        $d = self::prepare($in, $user, $source);
        $d['status'] = 'draft';
        $id = SupplyRepository::create((int) $user['id'], $d);
        AuditRepository::log((int) $user['id'], 'CREATE_SUPPLY', 'supply_listing', $id, null, $d);
        return $id;
    }

    /** Loads a listing the user may modify. */
    public static function owned(array $user, int $id): array
    {
        $s = SupplyRepository::find($id);
        if (!$s || ((int) $s['supplier_user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
            throw new ApiException('Not found', 404);
        }
        return $s;
    }

    public static function update(array $user, int $id, array $in): void
    {
        $s = self::owned($user, $id);
        if (!in_array($s['status'], ['draft', 'active'], true)) {
            throw new ApiException('Only draft or active listings can be edited', 409);
        }
        $d = self::prepare($in, $user);
        $sold = (float) $s['quantity'] - (float) $s['available_quantity'];
        if ((float) $d['quantity'] < $sold) {
            throw new ApiException('Validation failed', 422, ['quantity' => ["quantity cannot be below already committed amount ($sold)"]]);
        }
        SupplyRepository::update($id, $d);
        AuditRepository::log((int) $user['id'], 'UPDATE_SUPPLY', 'supply_listing', $id, ['quantity' => $s['quantity'], 'price_per_unit' => $s['price_per_unit']], $d);
    }

    public static function publish(array $user, int $id): void
    {
        $s = self::owned($user, $id);
        if ($s['status'] !== 'draft') {
            throw new ApiException('Only draft listings can be published', 409);
        }
        SupplyRepository::setStatus($id, 'active');
        AuditRepository::log((int) $user['id'], 'PUBLISH_SUPPLY', 'supply_listing', $id);
    }

    public static function cancel(array $user, int $id): void
    {
        $s = self::owned($user, $id);
        if (!in_array($s['status'], ['draft', 'active'], true)) {
            throw new ApiException('Listing cannot be cancelled in its current state', 409);
        }
        SupplyRepository::setStatus($id, 'cancelled');
        AuditRepository::log((int) $user['id'], 'CANCEL_SUPPLY', 'supply_listing', $id);
    }

    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function addImage(array $user, int $id, ?array $file): array
    {
        $s = self::owned($user, $id);
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ApiException('Validation failed', 422, ['image' => ['An image file is required']]);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new ApiException('Validation failed', 422, ['image' => ['Image must be 5MB or smaller']]);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::MIME[$mime]) || @getimagesize($file['tmp_name']) === false) {
            throw new ApiException('Validation failed', 422, ['image' => ['Only JPG, PNG or WEBP images are allowed']]);
        }
        if (SupplyRepository::imageCount($id) >= 6) {
            throw new ApiException('Validation failed', 422, ['image' => ['Maximum 6 images per listing']]);
        }
        $dir = BASE_PATH . '/assets/uploads/supplies';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::MIME[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            throw new ApiException('Could not store image', 500);
        }
        $path = "/assets/uploads/supplies/$name";
        $imgId = SupplyRepository::addImage($id, $path, SupplyRepository::imageCount($id) === 0);
        return ['id' => $imgId, 'file_path' => $path];
    }
}
