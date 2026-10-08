<?php
declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        session_name('ecozhin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
