<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * Login throttling: counts failed attempts per IP + e-mail inside a window.
 */
final class LoginAttempt extends Model
{
    protected static string $table = 'login_attempts';

    protected static bool $timestamps = false;

    public static function record(string $ip, string $email, bool $success): void
    {
        $db = Database::instance();
        self::create([
            'ip'         => mb_substr($ip, 0, 45),
            'email'      => mb_strtolower(mb_substr(trim($email), 0, 190)),
            'success'    => $success ? 1 : 0,
            'created_at' => $db->now(),
        ]);
        // Opportunistic pruning keeps the table small on shared hosting.
        if (random_int(1, 20) === 1) {
            $db->delete('login_attempts', 'created_at < :threshold', [
                'threshold' => gmdate('Y-m-d H:i:s', time() - 86400 * 7),
            ]);
        }
    }

    public static function tooMany(string $ip, string $email, int $max, int $windowSeconds): bool
    {
        $threshold = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        $count = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM {{login_attempts}} WHERE success = 0 AND created_at >= :threshold AND (ip = :ip OR email = :email)',
            ['threshold' => $threshold, 'ip' => $ip, 'email' => mb_strtolower(trim($email))]
        );
        return $count >= $max;
    }

    public static function clear(string $ip, string $email): void
    {
        Database::instance()->delete(
            'login_attempts',
            'success = 0 AND (ip = :ip OR email = :email)',
            ['ip' => $ip, 'email' => mb_strtolower(trim($email))]
        );
    }
}
