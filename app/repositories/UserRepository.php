<?php
declare(strict_types=1);

final class UserRepository
{
    private const COLS = 'u.id, u.full_name, u.phone, u.email, u.preferred_language, u.status, u.password_hash, r.name AS role';

    public static function find(int $id): ?array
    {
        return Database::one('SELECT ' . self::COLS . ' FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL', [$id]);
    }

    public static function findByLogin(string $login): ?array
    {
        return Database::one(
            'SELECT ' . self::COLS . ' FROM users u JOIN roles r ON r.id = u.role_id WHERE (u.phone = ? OR u.email = ?) AND u.deleted_at IS NULL',
            [$login, $login]
        );
    }

    public static function exists(string $phone, ?string $email): bool
    {
        return (bool) Database::one('SELECT id FROM users WHERE phone = ? OR (? IS NOT NULL AND email = ?)', [$phone, $email, $email]);
    }

    public static function roleId(string $name): ?int
    {
        $row = Database::one('SELECT id FROM roles WHERE name = ?', [$name]);
        return $row ? (int) $row['id'] : null;
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO users (role_id, full_name, phone, email, password_hash, preferred_language) VALUES (?,?,?,?,?,?)',
            [$d['role_id'], $d['full_name'], $d['phone'], $d['email'], $d['password_hash'], $d['preferred_language']]
        );
    }

    public static function touchLogin(int $id): void
    {
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function publicView(array $u): array
    {
        unset($u['password_hash']);
        return $u;
    }
}
