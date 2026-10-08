<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify(): void
    {
        if (in_array(Request::method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || $sent === '' || !hash_equals(self::token(), $sent)) {
            Response::error('Invalid CSRF token', 419);
        }
    }
}
