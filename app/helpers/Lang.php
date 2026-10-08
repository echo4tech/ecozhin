<?php
declare(strict_types=1);

final class Lang
{
    public const SUPPORTED = ['ku', 'ar', 'en'];
    private static array $strings = [];

    public static function current(): string
    {
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::SUPPORTED, true)) {
            $_SESSION['lang'] = $_GET['lang'];
        }
        return $_SESSION['lang'] ?? 'ku';
    }

    public static function dir(): string
    {
        return self::current() === 'en' ? 'ltr' : 'rtl';
    }

    public static function t(string $key): string
    {
        $lang = self::current();
        self::$strings[$lang] ??= json_decode((string) file_get_contents(BASE_PATH . "/lang/$lang.json"), true) ?: [];
        return self::$strings[$lang][$key] ?? $key;
    }

    public static function strings(): array
    {
        self::t('app.name');
        return self::$strings[self::current()];
    }
}

function t(string $key): string { return Lang::t($key); }
function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
