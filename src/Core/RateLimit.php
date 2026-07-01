<?php

namespace QuietRent\Core;

class RateLimit
{
    private const MAX_ATTEMPTS  = 10;
    private const WINDOW_MINUTES = 15;

    public static function tooManyLoginAttempts(): bool
    {
        $ip      = self::ip();
        $window  = date('Y-m-d H:i:s', strtotime('-' . self::WINDOW_MINUTES . ' minutes'));
        $count   = DB::fetchOne(
            'SELECT COUNT(*) as n FROM login_attempts WHERE ip = ? AND created_at >= ?',
            [$ip, $window]
        );
        return (int)($count['n'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    public static function recordLoginAttempt(): void
    {
        DB::execute('INSERT INTO login_attempts (ip) VALUES (?)', [self::ip()]);
    }

    public static function clearOld(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::WINDOW_MINUTES . ' minutes'));
        DB::execute('DELETE FROM login_attempts WHERE created_at < ?', [$cutoff]);
    }

    private static function ip(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
