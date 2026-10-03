<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * Academic / social profiles a user can publish on their page
 * (ORCID, Google Scholar, GitHub, Mastodon, 微博 …).
 */
final class UserLink extends Model
{
    protected static string $table = 'user_links';

    protected static bool $timestamps = false;

    /**
     * Platform registry: key → [label, icon, url template, validation pattern].
     * `{value}` is replaced by what the user typed; when the pattern is set the
     * user may also paste a full URL and we keep it as-is.
     */
    public const PLATFORMS = [
        'orcid'           => ['label' => 'ORCID iD',            'icon' => 'orcid',    'template' => 'https://orcid.org/{value}',                  'pattern' => '/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/i'],
        'google_scholar'  => ['label' => 'Google Scholar',      'icon' => 'scholar',  'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'philpeople'      => ['label' => 'PhilPeople',          'icon' => 'book',     'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'philpapers'      => ['label' => 'PhilPapers',          'icon' => 'book',     'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'researchgate'    => ['label' => 'ResearchGate',        'icon' => 'flask',    'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'academia'        => ['label' => 'Academia.edu',        'icon' => 'cap',      'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'ssrn'            => ['label' => 'SSRN',                'icon' => 'doc',      'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'arxiv'           => ['label' => 'arXiv',               'icon' => 'doc',      'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'github'          => ['label' => 'GitHub',              'icon' => 'code',     'template' => 'https://github.com/{value}',                 'pattern' => '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/'],
        'gitlab'          => ['label' => 'GitLab',              'icon' => 'code',     'template' => 'https://gitlab.com/{value}',                 'pattern' => '/^[A-Za-z0-9_.\-]{1,60}$/'],
        'website'         => ['label' => 'Personal website',    'icon' => 'globe',    'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'blog'            => ['label' => 'Blog',                'icon' => 'globe',    'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'mastodon'        => ['label' => 'Mastodon',            'icon' => 'mastodon', 'template' => '{value}',                                    'pattern' => '/^https?:\/\/\S+$/i'],
        'bluesky'         => ['label' => 'Bluesky',             'icon' => 'cloud',    'template' => 'https://bsky.app/profile/{value}',           'pattern' => '/^[A-Za-z0-9.\-]+$/'],
        'twitter'         => ['label' => 'X / Twitter',         'icon' => 'x',        'template' => 'https://x.com/{value}',                      'pattern' => '/^@?[A-Za-z0-9_]{1,15}$/'],
        'threads'         => ['label' => 'Threads',             'icon' => 'chat',     'template' => 'https://www.threads.net/@{value}',           'pattern' => '/^@?[A-Za-z0-9_.]+$/'],
        'linkedin'        => ['label' => 'LinkedIn',            'icon' => 'linkedin', 'template' => 'https://www.linkedin.com/in/{value}',        'pattern' => '/^[A-Za-z0-9\-_%]+$/'],
        'youtube'         => ['label' => 'YouTube',             'icon' => 'video',    'template' => 'https://www.youtube.com/@{value}',           'pattern' => '/^@?[A-Za-z0-9_\-\.]+$/'],
        'telegram'        => ['label' => 'Telegram',            'icon' => 'send',     'template' => 'https://t.me/{value}',                       'pattern' => '/^[A-Za-z0-9_]{3,64}$/'],
        'wikipedia'       => ['label' => 'Wikipedia',           'icon' => 'book',     'template' => 'https://{value}',                            'pattern' => '/^[A-Za-z\-]+\.wikipedia\.org\/wiki\/\S+$/'],
        'email'           => ['label' => 'Public e-mail',       'icon' => 'mail',     'template' => 'mailto:{value}',                             'pattern' => '/^[^@\s]+@[^@\s]+\.[^@\s]+$/'],
    ];

    public static function platforms(): array
    {
        return self::PLATFORMS;
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return self::all(['user_id' => $userId], 'sort_order ASC, id ASC');
    }

    /** Resolve a user value into a clickable absolute URL. */
    public static function resolveUrl(string $platform, string $value): string
    {
        $value = trim($value);
        $meta = self::PLATFORMS[$platform] ?? null;
        if (preg_match('#^https?://#i', $value) || str_starts_with($value, 'mailto:')) {
            return $value;
        }
        if ($meta === null) {
            return $value;
        }
        return str_replace('{value}', rawurlencode($value), $meta['template']);
    }

    public static function validate(string $platform, string $value): bool
    {
        $meta = self::PLATFORMS[$platform] ?? null;
        if ($meta === null) {
            return false;
        }
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $value)) {
            return (bool) filter_var($value, FILTER_VALIDATE_URL);
        }
        if (str_starts_with($value, 'mailto:')) {
            return (bool) filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL);
        }
        if (isset($meta['pattern'])) {
            return (bool) preg_match($meta['pattern'], $value);
        }
        return true;
    }

    /** Replace-all semantics: the profile form submits the whole set. */
    public static function syncForUser(int $userId, array $submitted): void
    {
        $db = Database::instance();
        $db->delete('user_links', 'user_id = :id', ['id' => $userId]);
        $position = 0;
        foreach ($submitted as $platform => $value) {
            $value = trim((string) $value);
            if ($value === '' || !isset(self::PLATFORMS[$platform])) {
                continue;
            }
            self::create([
                'user_id'    => $userId,
                'platform'   => $platform,
                'label'      => null,
                'url'        => self::resolveUrl($platform, $value),
                'sort_order' => $position++,
                'created_at' => $db->now(),
            ]);
        }
    }

    /** Raw stored value (for re-populating the edit form). */
    public static function rawValue(string $platform, string $url): string
    {
        $meta = self::PLATFORMS[$platform] ?? null;
        if ($meta === null) {
            return $url;
        }
        $template = $meta['template'];
        if (!str_contains($template, '{value}')) {
            return $url;
        }
        [$prefix, $suffix] = explode('{value}', $template, 2);
        if ($prefix !== '' && str_starts_with($url, $prefix)) {
            $url = substr($url, strlen($prefix));
        }
        if ($suffix !== '' && str_ends_with($url, $suffix)) {
            $url = substr($url, 0, -strlen($suffix));
        }
        return rawurldecode($url);
    }
}
