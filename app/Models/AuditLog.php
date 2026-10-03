<?php

declare(strict_types=1);

namespace Athenaeum\Models;

use Athenaeum\Core\Auth;
use Athenaeum\Core\Database;
use Athenaeum\Core\Model;

/**
 * Audit trail for every privileged action (审核 / 下架 / 封禁 / 破例上传 …).
 */
final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    protected static bool $timestamps = false;

    public static function record(
        string $action,
        ?string $targetType = null,
        int|string|null $targetId = null,
        array $meta = []
    ): void {
        try {
            $actor = Auth::user();
            self::create([
                'actor_id'    => $actor['id'] ?? null,
                'actor_uid'   => $actor['uid'] ?? null,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId === null ? null : (string) $targetId,
                'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip'          => \Athenaeum\Core\App::request()->ip(),
                'created_at'  => Database::instance()->now(),
            ]);
        } catch (\Throwable $e) {
            \Athenaeum\Core\Logger::warning('audit log failed: ' . $e->getMessage());
        }
    }

    /** @return array{items:array,total:int,page:int,perPage:int,pages:int} */
    public static function paginateAll(int $page = 1, int $perPage = 50, ?string $action = null): array
    {
        $db = Database::instance();
        $clause = '';
        $params = [];
        if ($action !== null && $action !== '') {
            $clause = ' WHERE action = :action';
            $params['action'] = $action;
        }
        $total = (int) $db->scalar('SELECT COUNT(*) FROM {{audit_logs}}' . $clause, $params);
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $items = $db->select(
            'SELECT * FROM {{audit_logs}}' . $clause . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
    }

    public static function label(string $action): string
    {
        $key = 'audit.' . $action;
        $translated = __($key);
        return $translated === $key ? $action : $translated;
    }
}
