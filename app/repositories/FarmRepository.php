<?php
declare(strict_types=1);

final class FarmRepository
{
    public static function forFarmer(int $userId): array
    {
        return Database::all('SELECT * FROM farms WHERE farmer_user_id = ? ORDER BY id DESC', [$userId]);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM farms WHERE id = ?', [$id]);
    }

    public static function create(int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO farms (farmer_user_id, village_id, name, area_donum, address, latitude, longitude) VALUES (?,?,?,?,?,?,?)',
            [$userId, $d['village_id'], $d['name'], $d['area_donum'], $d['address'], $d['latitude'], $d['longitude']]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::query(
            'UPDATE farms SET village_id=?, name=?, area_donum=?, address=?, latitude=?, longitude=? WHERE id=?',
            [$d['village_id'], $d['name'], $d['area_donum'], $d['address'], $d['latitude'], $d['longitude'], $id]
        );
    }
}
