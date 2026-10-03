<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * One-time e-mail codes (registration today, password reset later).
 *
 * Only a hash of the code is stored: a leaked database must not hand out
 * working codes. Codes are short-lived, single-use, and rate-limited per
 * address so the endpoint cannot be used to spam a stranger's inbox.
 */
final class EmailVerification extends Model
{
    protected static string $table = 'email_verifications';

    public const PURPOSE_REGISTER = 'register';
    public const PURPOSE_RESET    = 'reset';

    /** How long a code stays valid. */
    public const TTL_SECONDS = 600;

    /** Minimum delay between two codes for the same address. */
    public const RESEND_SECONDS = 45;

    /** How many codes one address may request per hour. */
    public const MAX_PER_HOUR = 6;

    /** How many wrong guesses before the code is burned. */
    public const MAX_ATTEMPTS = 6;

    /**
     * Issue a code for an address.
     *
     * @return array{ok:bool,code?:string,error?:string,retry_after?:int}
     */
    public static function issue(string $email, string $purpose = self::PURPOSE_REGISTER): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid address'];
        }

        $db = Database::instance();
        $now = time();

        $recent = self::latestFor($email, $purpose);
        if ($recent !== null) {
            $age = $now - strtotime((string) $recent['created_at'] . ' UTC');
            if ($age < self::RESEND_SECONDS) {
                return [
                    'ok'          => false,
                    'error'       => 'too soon',
                    'retry_after' => self::RESEND_SECONDS - $age,
                ];
            }
        }

        $lastHour = (int) $db->scalar(
            'SELECT COUNT(*) FROM {{email_verifications}} WHERE email = :email AND created_at >= :since',
            ['email' => $email, 'since' => gmdate('Y-m-d H:i:s', $now - 3600)]
        );
        if ($lastHour >= self::MAX_PER_HOUR) {
            return ['ok' => false, 'error' => 'rate limited', 'retry_after' => 3600 - ($now % 3600)];
        }

        // Six digits, uniformly random, never "000000"-ish predictable.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($recent !== null) {
            $db->delete('email_verifications', 'id = :id', ['id' => (int) $recent['id']]);
        }
        self::create([
            'email'      => $email,
            'code_hash'  => self::hash($code),
            'purpose'    => $purpose,
            'attempts'   => 0,
            'expires_at' => gmdate('Y-m-d H:i:s', $now + self::TTL_SECONDS),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'ip'         => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        return ['ok' => true, 'code' => $code];
    }

    /**
     * Check a code and consume it on success.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function verify(string $email, string $code, string $purpose = self::PURPOSE_REGISTER): array
    {
        $email = mb_strtolower(trim($email));
        $code = preg_replace('/\D/', '', $code) ?? '';
        $row = self::latestFor($email, $purpose);

        if ($row === null) {
            return ['ok' => false, 'error' => 'no code'];
        }
        if (strtotime((string) $row['expires_at'] . ' UTC') < time()) {
            self::delete((int) $row['id']);
            return ['ok' => false, 'error' => 'expired'];
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            self::delete((int) $row['id']);
            return ['ok' => false, 'error' => 'too many attempts'];
        }

        if (strlen($code) !== 6 || !hash_equals((string) $row['code_hash'], self::hash($code))) {
            self::update((int) $row['id'], ['attempts' => (int) $row['attempts'] + 1]);
            return ['ok' => false, 'error' => 'mismatch'];
        }

        self::delete((int) $row['id']);
        return ['ok' => true];
    }

    /** Drop expired rows (called from the timestamp/cron-ish maintenance path). */
    public static function prune(): int
    {
        // verify() already refuses an expired code, so nothing useful is lost.
        return Database::instance()->delete(
            'email_verifications',
            'expires_at < :now',
            ['now' => gmdate('Y-m-d H:i:s', time())]
        );
    }

    private static function latestFor(string $email, string $purpose): ?array
    {
        return self::db()->selectOne(
            'SELECT * FROM {{email_verifications}} WHERE email = :email AND purpose = :purpose ORDER BY id DESC LIMIT 1',
            ['email' => $email, 'purpose' => $purpose]
        );
    }

    private static function hash(string $code): string
    {
        // A per-install pepper would be better; the application key is already
        // secret and stable, which is enough for a 10-minute code.
        $pepper = (string) Config::get('app.key', 'athenxiv');
        return hash_hmac('sha256', $code, $pepper);
    }
}
