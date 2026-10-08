<?php
declare(strict_types=1);

final class SupplyRepository
{
    public const MARKET_STATUSES = ['active', 'partially_sold'];

    private const SELECT = 'SELECT s.*, w.name_ku AS waste_name_ku, w.name_ar AS waste_name_ar, w.name_en AS waste_name_en,
            w.code AS waste_code, w.agricultural_product_id, un.code AS unit_code, un.name_ku AS unit_name_ku,
            u.full_name AS supplier_name,
            (SELECT file_path FROM supply_images i WHERE i.supply_listing_id = s.id ORDER BY i.is_primary DESC, i.id LIMIT 1) AS primary_image/*EXTRA*/
        FROM supply_listings s
        JOIN waste_types w ON w.id = s.waste_type_id
        JOIN units un ON un.id = s.unit_id
        JOIN users u ON u.id = s.supplier_user_id';

    public static function find(int $id): ?array
    {
        return Database::one(str_replace('/*EXTRA*/', '', self::SELECT) . ' WHERE s.id = ?', [$id]);
    }

    public static function images(int $id): array
    {
        return Database::all('SELECT id, file_path, is_primary FROM supply_images WHERE supply_listing_id = ? ORDER BY is_primary DESC, id', [$id]);
    }

    /** Public marketplace search. Returns [rows, total]. */
    public static function search(array $f, bool $mineOnly = false, ?int $userId = null): array
    {
        $where = [];
        $p = [];
        if ($mineOnly) {
            $where[] = 's.supplier_user_id = ?';
            $p[] = $userId;
            if (!empty($f['status'])) {
                $where[] = 's.status = ?';
                $p[] = $f['status'];
            }
        } else {
            $where[] = "s.status IN ('active','partially_sold')";
            $where[] = '(s.available_until IS NULL OR s.available_until >= CURDATE())';
            $where[] = 's.available_quantity > 0';
        }
        foreach (['waste_type_id' => 's.waste_type_id', 'product_id' => 'w.agricultural_product_id', 'quality' => 's.quality_grade'] as $k => $col) {
            if (!empty($f[$k])) {
                $where[] = "$col = ?";
                $p[] = $f[$k];
            }
        }
        if (isset($f['min_price']) && $f['min_price'] !== '') { $where[] = 's.price_per_unit >= ?'; $p[] = $f['min_price']; }
        if (isset($f['max_price']) && $f['max_price'] !== '') { $where[] = '(s.price_per_unit <= ? OR s.price_type = \'free\')'; $p[] = $f['max_price']; }
        if (!empty($f['min_quantity'])) { $where[] = 's.available_quantity * COALESCE(un.conversion_to_kg,1) >= ?'; $p[] = $f['min_quantity']; }
        if (!empty($f['available_by'])) { $where[] = 's.available_from <= ?'; $p[] = $f['available_by']; }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            $where[] = '(w.name_ku LIKE ? OR w.name_ar LIKE ? OR w.name_en LIKE ? OR s.description LIKE ? OR s.address LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like);
        }

        $distance = null;
        $hasGeo = isset($f['lat'], $f['lng']) && is_numeric($f['lat']) && is_numeric($f['lng']);
        if ($hasGeo) {
            $distance = '(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(s.latitude)) * COS(RADIANS(s.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(s.latitude)))))';
        }
        $select = self::SELECT;
        $selectParams = [];
        if ($distance) {
            $select = str_replace('/*EXTRA*/', ", $distance AS distance_km", $select);
            $selectParams = [$f['lat'], $f['lng'], $f['lat']];
            $where[] = 's.latitude IS NOT NULL AND s.longitude IS NOT NULL';
            if (!empty($f['radius_km'])) {
                $where[] = "$distance <= ?";
                array_push($p, $f['lat'], $f['lng'], $f['lat'], $f['radius_km']);
            }
        }

        $select = str_replace('/*EXTRA*/', '', $select);
        $order = match ($f['sort'] ?? 'newest') {
            'price_asc' => 's.price_per_unit IS NULL, s.price_per_unit ASC, s.id DESC',
            'quantity_desc' => 's.available_quantity * COALESCE(un.conversion_to_kg,1) DESC, s.id DESC',
            'nearest' => $distance ? 'distance_km ASC, s.id DESC' : 's.id DESC',
            default => 's.id DESC',
        };

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countSql = 'SELECT COUNT(*) c FROM supply_listings s JOIN waste_types w ON w.id = s.waste_type_id JOIN units un ON un.id = s.unit_id' . $whereSql;
        $total = (int) Database::one($countSql, $p)['c'];

        [$per, $offset] = Fields::page();
        $rows = Database::all($select . $whereSql . " ORDER BY $order LIMIT $per OFFSET $offset", array_merge($selectParams, $p));
        return [$rows, $total];
    }

    public static function create(int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO supply_listings (supplier_user_id, farm_id, waste_type_id, unit_id, quantity, available_quantity, quality_grade,
                moisture_percent, price_type, price_per_unit, available_from, available_until, address, latitude, longitude, source_type, description, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $d['farm_id'], $d['waste_type_id'], $d['unit_id'], $d['quantity'], $d['quantity'], $d['quality_grade'],
             $d['moisture_percent'], $d['price_type'], $d['price_per_unit'], $d['available_from'], $d['available_until'],
             $d['address'], $d['latitude'], $d['longitude'], $d['source_type'], $d['description'], $d['status']]
        );
    }

    public static function update(int $id, array $d): void
    {
        // quantity change keeps already-reserved/sold amount: available = new quantity - (old quantity - old available)
        Database::query(
            'UPDATE supply_listings SET farm_id=?, waste_type_id=?, unit_id=?, available_quantity = ? - (quantity - available_quantity), quantity=?,
                quality_grade=?, moisture_percent=?, price_type=?, price_per_unit=?, available_from=?, available_until=?, address=?, latitude=?, longitude=?, description=?
             WHERE id=?',
            [$d['farm_id'], $d['waste_type_id'], $d['unit_id'], $d['quantity'], $d['quantity'], $d['quality_grade'], $d['moisture_percent'],
             $d['price_type'], $d['price_per_unit'], $d['available_from'], $d['available_until'], $d['address'], $d['latitude'], $d['longitude'], $d['description'], $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::query('UPDATE supply_listings SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function addImage(int $id, string $path, bool $primary): int
    {
        return Database::insert('INSERT INTO supply_images (supply_listing_id, file_path, is_primary) VALUES (?,?,?)', [$id, $path, (int) $primary]);
    }

    public static function imageCount(int $id): int
    {
        return (int) Database::one('SELECT COUNT(*) c FROM supply_images WHERE supply_listing_id = ?', [$id])['c'];
    }
}
