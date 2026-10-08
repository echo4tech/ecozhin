<?php
declare(strict_types=1);

final class DemandRepository
{
    private const SELECT = 'SELECT d.*, w.name_ku AS waste_name_ku, w.name_ar AS waste_name_ar, w.name_en AS waste_name_en, un.code AS unit_code,
            o.name AS organization_name
        FROM buyer_demands d
        JOIN waste_types w ON w.id = d.waste_type_id
        JOIN units un ON un.id = d.unit_id
        LEFT JOIN organizations o ON o.id = d.organization_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE d.id = ?', [$id]);
    }

    public static function list(?int $buyerId, array $f): array
    {
        $where = [];
        $p = [];
        if ($buyerId !== null) {
            $where[] = 'd.buyer_user_id = ?';
            $p[] = $buyerId;
            if (!empty($f['status'])) { $where[] = 'd.status = ?'; $p[] = $f['status']; }
        } else {
            $where[] = "d.status IN ('active','partially_filled')";
            $where[] = '(d.required_until IS NULL OR d.required_until >= CURDATE())';
        }
        if (!empty($f['waste_type_id'])) { $where[] = 'd.waste_type_id = ?'; $p[] = $f['waste_type_id']; }
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::one('SELECT COUNT(*) c FROM buyer_demands d' . $w, $p)['c'];
        [$per, $offset] = Fields::page();
        return [Database::all(self::SELECT . $w . " ORDER BY d.id DESC LIMIT $per OFFSET $offset", $p), $total];
    }

    public static function create(int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO buyer_demands (buyer_user_id, organization_id, waste_type_id, unit_id, required_quantity, remaining_quantity, minimum_quality,
                max_price_per_unit, required_from, required_until, delivery_required, latitude, longitude, address, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $d['organization_id'], $d['waste_type_id'], $d['unit_id'], $d['required_quantity'], $d['required_quantity'], $d['minimum_quality'],
             $d['max_price_per_unit'], $d['required_from'], $d['required_until'], $d['delivery_required'], $d['latitude'], $d['longitude'], $d['address'], 'draft']
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::query(
            'UPDATE buyer_demands SET organization_id=?, waste_type_id=?, unit_id=?, remaining_quantity = ? - (required_quantity - remaining_quantity), required_quantity=?,
                minimum_quality=?, max_price_per_unit=?, required_from=?, required_until=?, delivery_required=?, latitude=?, longitude=?, address=? WHERE id=?',
            [$d['organization_id'], $d['waste_type_id'], $d['unit_id'], $d['required_quantity'], $d['required_quantity'], $d['minimum_quality'],
             $d['max_price_per_unit'], $d['required_from'], $d['required_until'], $d['delivery_required'], $d['latitude'], $d['longitude'], $d['address'], $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::query('UPDATE buyer_demands SET status = ? WHERE id = ?', [$status, $id]);
    }
}
