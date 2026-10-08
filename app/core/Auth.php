<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        return UserRepository::find((int) $id);
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['csrf']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function require(string ...$roles): array
    {
        $user = self::user();
        if (!$user || $user['status'] !== 'active') {
            Response::error('Authentication required', 401);
        }
        if ($roles && !in_array($user['role'], $roles, true)) {
            Response::error('Forbidden', 403);
        }
        return $user;
    }
}
