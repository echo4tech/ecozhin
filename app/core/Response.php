<?php
declare(strict_types=1);

final class Response
{
    public static function json(bool $success, string $message, array $extra = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        self::json(true, $message, $data === null ? [] : ['data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        self::json(false, $message, $errors ? ['errors' => $errors] : [], $status);
    }
}
