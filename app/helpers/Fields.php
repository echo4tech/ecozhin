<?php
declare(strict_types=1);

/** Small helpers for turning request input into DB-ready values. */
final class Fields
{
    /** '' / missing -> null, otherwise the value. */
    public static function nullable(array $in, string $key): mixed
    {
        $v = $in[$key] ?? null;
        return ($v === '' || $v === null) ? null : $v;
    }

    public static function nullableNum(array $in, string $key): int|float|null
    {
        $v = self::nullable($in, $key);
        return $v === null ? null : $v + 0;
    }

    public static function page(): array
    {
        $per = max(1, min(100, (int) Request::input('per_page', 20)));
        $page = max(1, (int) Request::input('page', 1));
        return [$per, ($page - 1) * $per, $page];
    }

    public static function ensureExists(string $table, int $id, string $field, array &$errors, string $extra = ''): void
    {
        if (!Database::one("SELECT id FROM $table WHERE id = ? $extra", [$id])) {
            $errors[$field][] = 'Unknown value';
        }
    }
}
