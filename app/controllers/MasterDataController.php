<?php
declare(strict_types=1);

final class MasterDataController
{
    public static function units(): void { Response::ok(MasterDataRepository::units()); }
    public static function products(): void { Response::ok(MasterDataRepository::products()); }
    public static function governorates(): void { Response::ok(MasterDataRepository::governorates()); }
    public static function settings(): void { Response::ok(MasterDataRepository::publicSettings()); }

    public static function districts(): void
    {
        $g = Request::input('governorate_id');
        Response::ok(MasterDataRepository::districts($g ? (int) $g : null));
    }

    public static function wasteTypes(): void
    {
        $p = Request::input('product_id');
        Response::ok(MasterDataRepository::wasteTypes(true, $p ? (int) $p : null));
    }

    private const WASTE_RULES = [
        'name_ku' => 'required|string|max:150',
        'name_ar' => 'string|max:150',
        'name_en' => 'string|max:150',
        'description' => 'string|max:2000',
        'agricultural_product_id' => 'int',
        'default_unit_id' => 'int',
    ];

    private static function wasteData(array $b): array
    {
        $n = static fn($k) => ($b[$k] ?? '') === '' ? null : $b[$k];
        return [
            'agricultural_product_id' => $n('agricultural_product_id') ? (int) $b['agricultural_product_id'] : null,
            'name_ku' => trim((string) $b['name_ku']), 'name_ar' => $n('name_ar'), 'name_en' => $n('name_en'),
            'description' => $n('description'),
            'default_unit_id' => $n('default_unit_id') ? (int) $b['default_unit_id'] : null,
            'recyclable' => isset($b['recyclable']) ? (int) (bool) $b['recyclable'] : 1,
            'active' => isset($b['active']) ? (int) (bool) $b['active'] : 1,
        ];
    }

    private static function checkRefs(array $d): void
    {
        $e = [];
        if ($d['agricultural_product_id'] && !Database::one('SELECT id FROM agricultural_products WHERE id=?', [$d['agricultural_product_id']])) {
            $e['agricultural_product_id'][] = 'Unknown product';
        }
        if ($d['default_unit_id'] && !Database::one('SELECT id FROM units WHERE id=?', [$d['default_unit_id']])) {
            $e['default_unit_id'][] = 'Unknown unit';
        }
        if ($e) {
            throw new ApiException('Validation failed', 422, $e);
        }
    }

    public static function createWasteType(): void
    {
        $admin = Auth::require('admin');
        $b = Request::body();
        $errors = Validator::validate($b, self::WASTE_RULES + ['code' => 'required|string|min:2|max:60']);
        if (!isset($errors['code']) && !preg_match('/^[a-z0-9_]+$/', (string) ($b['code'] ?? ''))) {
            $errors['code'][] = 'code must be lowercase letters, digits, underscore';
        }
        if (!$errors && Database::one('SELECT id FROM waste_types WHERE code = ?', [$b['code']])) {
            $errors['code'][] = 'code already exists';
        }
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        $d = self::wasteData($b) + ['code' => $b['code']];
        self::checkRefs($d);
        $id = MasterDataRepository::createWasteType($d);
        AuditRepository::log((int) $admin['id'], 'CREATE_WASTE_TYPE', 'waste_type', $id, null, $d);
        Response::ok(['id' => $id], 'Waste type created', 201);
    }

    public static function updateWasteType(string $id): void
    {
        $admin = Auth::require('admin');
        $old = MasterDataRepository::wasteType((int) $id);
        if (!$old) {
            throw new ApiException('Not found', 404);
        }
        $b = Request::body();
        $errors = Validator::validate($b, self::WASTE_RULES);
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        $d = self::wasteData($b);
        self::checkRefs($d);
        MasterDataRepository::updateWasteType((int) $id, $d);
        AuditRepository::log((int) $admin['id'], 'UPDATE_WASTE_TYPE', 'waste_type', (int) $id, $old, $d);
        Response::ok(null, 'Waste type updated');
    }

    public static function createProduct(): void
    {
        $admin = Auth::require('admin');
        $b = Request::body();
        $errors = Validator::validate($b, [
            'name_ku' => 'required|string|max:150', 'name_ar' => 'string|max:150',
            'name_en' => 'string|max:150', 'category' => 'string|max:100',
        ]);
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        $n = static fn($k) => ($b[$k] ?? '') === '' ? null : $b[$k];
        $d = ['name_ku' => trim((string) $b['name_ku']), 'name_ar' => $n('name_ar'), 'name_en' => $n('name_en'), 'category' => $n('category')];
        $id = MasterDataRepository::createProduct($d);
        AuditRepository::log((int) $admin['id'], 'CREATE_PRODUCT', 'agricultural_product', $id, null, $d);
        Response::ok(['id' => $id], 'Product created', 201);
    }
}
