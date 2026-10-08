<?php
declare(strict_types=1);

final class Request
{
    private static ?array $body = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $pos = strpos($path, '/api');
        $path = $pos === false ? $path : substr($path, $pos + 4);
        return '/' . trim($path, '/');
    }

    public static function body(): array
    {
        if (self::$body === null) {
            $raw = file_get_contents('php://input') ?: '';
            $json = json_decode($raw, true);
            self::$body = is_array($json) ? $json : $_POST;
        }
        return self::$body;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return self::body()[$key] ?? $_GET[$key] ?? $default;
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
