<?php
declare(strict_types=1);

final class MasterDataRepository
{
    public static function units(): array
    {
        return Database::all('SELECT id, code, name_ku, name_en, conversion_to_kg FROM units ORDER BY id');
    }

    public static function products(bool $onlyActive = true): array
    {
        return Database::all('SELECT id, name_ku, name_ar, name_en, category, active FROM agricultural_products' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY name_ku');
    }

    public static function wasteTypes(bool $onlyActive = true, ?int $productId = null): array
    {
        $where = [];
        $params = [];
        if ($onlyActive) {
            $where[] = 'w.active = 1';
        }
        if ($productId) {
            $where[] = 'w.agricultural_product_id = ?';
            $params[] = $productId;
        }
        return Database::all(
            'SELECT w.id, w.code, w.agricultural_product_id, w.name_ku, w.name_ar, w.name_en, w.description, w.default_unit_id, w.recyclable, w.active
             FROM waste_types w' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY w.id',
            $params
        );
    }

    public static function wasteType(int $id): ?array
    {
        return Database::one('SELECT * FROM waste_types WHERE id = ?', [$id]);
    }

    public static function createWasteType(array $d): int
    {
        return Database::insert(
            'INSERT INTO waste_types (agricultural_product_id, code, name_ku, name_ar, name_en, description, default_unit_id, recyclable) VALUES (?,?,?,?,?,?,?,?)',
            [$d['agricultural_product_id'], $d['code'], $d['name_ku'], $d['name_ar'], $d['name_en'], $d['description'], $d['default_unit_id'], $d['recyclable']]
        );
    }

    public static function updateWasteType(int $id, array $d): void
    {
        Database::query(
            'UPDATE waste_types SET agricultural_product_id=?, name_ku=?, name_ar=?, name_en=?, description=?, default_unit_id=?, recyclable=?, active=? WHERE id=?',
            [$d['agricultural_product_id'], $d['name_ku'], $d['name_ar'], $d['name_en'], $d['description'], $d['default_unit_id'], $d['recyclable'], $d['active'], $id]
        );
    }

    public static function createProduct(array $d): int
    {
        return Database::insert(
            'INSERT INTO agricultural_products (name_ku, name_ar, name_en, category) VALUES (?,?,?,?)',
            [$d['name_ku'], $d['name_ar'], $d['name_en'], $d['category']]
        );
    }

    public static function governorates(): array
    {
        return Database::all('SELECT id, name_ku, name_ar, name_en FROM governorates ORDER BY id');
    }

    public static function districts(?int $governorateId): array
    {
        return $governorateId
            ? Database::all('SELECT id, governorate_id, name_ku, name_ar, name_en FROM districts WHERE governorate_id = ? ORDER BY id', [$governorateId])
            : Database::all('SELECT id, governorate_id, name_ku, name_ar, name_en FROM districts ORDER BY id');
    }

    public static function publicSettings(): array
    {
        $out = [];
        foreach (Database::all('SELECT setting_key, setting_value, value_type FROM settings WHERE is_public = 1') as $r) {
            $out[$r['setting_key']] = match ($r['value_type']) {
                'int' => (int) $r['setting_value'],
                'float' => (float) $r['setting_value'],
                'bool' => (bool) $r['setting_value'],
                default => $r['setting_value'],
            };
        }
        return $out;
    }
}
