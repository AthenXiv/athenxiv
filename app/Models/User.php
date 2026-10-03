<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Model;
use Athenaeum\Core\Str;

/**
 * Accounts. Nicknames are intentionally NOT unique — the public `uid` is what
 * identifies a person ("@U7K4M2QF"), so two 张三 can coexist safely.
 */
final class User extends Model
{
    protected static string $table = 'users';

    protected static array $booleans = [];

    public const ROLES = ['user', 'editor', 'admin'];

    public const STATUSES = ['active', 'suspended', 'banned'];

    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', mb_strtolower(trim($email)));
    }

    public static function findByUid(string $uid): ?array
    {
        return self::findBy('uid', strtoupper(trim($uid)));
    }

    /** @param array<string,mixed> $data */
    public static function register(array $data): int
    {
        $email = mb_strtolower(trim((string) $data['email']));
        $nickname = trim((string) ($data['nickname'] ?? '')) ?: explode('@', $email)[0];

        return self::create([
            'uid'           => self::generateUid(),
            'email'         => $email,
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'nickname'      => $nickname,
            'display_name'  => $data['display_name'] ?? $nickname,
            'affiliation'   => $data['affiliation'] ?? null,
            'locale'        => $data['locale'] ?? null,
            'role'          => $data['role'] ?? 'user',
            'status'        => $data['status'] ?? 'active',
            'bio'           => $data['bio'] ?? null,
            'created_at'    => Database::instance()->now(),
        ]);
    }

    /** Admin-side account creation (same thing, but explicit). */
    public static function createByAdmin(array $data): int
    {
        return self::register($data);
    }

    public static function generateUid(): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $uid = Str::uid('U', 8);
            if (self::findByUid($uid) === null) {
                return $uid;
            }
        }
        return Str::uid('U', 12);
    }

    public static function updatePassword(int $id, string $plain): void
    {
        self::update($id, ['password_hash' => password_hash($plain, PASSWORD_DEFAULT)]);
    }

    public static function touchLogin(int $id, string $ip): void
    {
        self::update($id, [
            'last_login_at' => Database::instance()->now(),
            'last_login_ip' => $ip,
        ]);
    }

    public static function setStatus(int $id, string $status): void
    {
        self::update($id, ['status' => in_array($status, self::STATUSES, true) ? $status : 'active']);
    }

    /** Avatar URL or null; falls back to a generated initial in the view. */
    public static function avatarUrl(array $user): ?string
    {
        $path = trim((string) ($user['avatar_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        return storage_url('avatars', $path);
    }

    public static function profileUrl(array $user): string
    {
        return url('user.show', ['uid' => $user['uid']]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function links(int $userId): array
    {
        return UserLink::forUser($userId);
    }

    public static function search(string $term, int $limit = 30): array
    {
        $like = '%' . $term . '%';
        // Distinct placeholders: MySQL refuses to bind one named parameter twice
        // (SQLite allows it, so this only fails in production).
        return Database::instance()->select(
            'SELECT * FROM {{users}} WHERE email LIKE :q1 OR nickname LIKE :q2 OR uid LIKE :q3'
            . ' OR display_name LIKE :q4 ORDER BY id DESC LIMIT ' . (int) $limit,
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]
        );
    }

    /** Delete a user and their content (admin "hard" action). */
    public static function purge(int $id): void
    {
        $db = Database::instance();
        $papers = Paper::all(['uploader_id' => $id]);
        foreach ($papers as $paper) {
            Paper::purge((int) $paper['id']);
        }
        $db->delete('user_links', 'user_id = :id', ['id' => $id]);
        $db->delete('users', 'id = :id', ['id' => $id]);
    }

    /** Absolute path of the user's avatar on disk, if any. */
    public static function avatarPath(array $user): ?string
    {
        $relative = trim((string) ($user['avatar_path'] ?? ''));
        if ($relative === '') {
            return null;
        }
        $full = Config::path('uploads', 'avatars/' . $relative);
        return is_file($full) ? $full : null;
    }
}
