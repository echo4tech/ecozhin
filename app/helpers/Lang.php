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
        return self::load(self::current())[$key] ?? $key;
    }

    /** Strings for a language; missing keys fall back to English so a gap never shows a raw key. */
    private static function load(string $lang): array
    {
        return self::$strings[$lang] ??= array_merge(
            json_decode((string) file_get_contents(BASE_PATH . '/lang/en.json'), true) ?: [],
            $lang === 'en' ? [] : (json_decode((string) file_get_contents(BASE_PATH . "/lang/$lang.json"), true) ?: [])
        );
    }

    public static function strings(): array
    {
        return self::load(self::current());
    }
}

function t(string $key): string { return Lang::t($key); }
function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
