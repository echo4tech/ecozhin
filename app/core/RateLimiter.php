<?php
declare(strict_types=1);

final class RateLimiter
{
    /** Returns false (and records nothing) when the bucket has exceeded $max hits in $seconds. */
    public static function hit(string $bucket, int $max, int $seconds): bool
    {
        Database::query('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        $row = Database::one(
            'SELECT COUNT(*) c FROM rate_limits WHERE bucket = ? AND created_at > (NOW() - INTERVAL ? SECOND)',
            [$bucket, $seconds]
        );
        if ((int) $row['c'] >= $max) {
            return false;
        }
        Database::query('INSERT INTO rate_limits (bucket) VALUES (?)', [$bucket]);
        return true;
    }
}
