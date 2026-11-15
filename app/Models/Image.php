<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/**
 * 图片。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent：
 * 简单的增删改查走查询构造器，多表 JOIN / GROUP BY / 聚合保留原生 SQL。
 * 对外契约一个字没改：find*() 返回行数组，create() 返回 id，列表返回行数组的数组。
 */
class Image extends BaseModel
{
    protected $table = 'images';

    use Concerns\ReturnsRows;

    public const STATUS_PENDING  = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    /**
     * 公开列表的密码闸门：图片自身有密码，或所属相册有密码，都不进广场/主页/相关推荐
     */
    private static function publicLockGuard(): string
    {
        static $sql = null;
        if ($sql === null) {
            $albums = DB::table('albums');
            $sql = "(i.password IS NULL OR i.password = '')"
                . " AND (i.album_id IS NULL OR NOT EXISTS ("
                . "SELECT 1 FROM {$albums} x WHERE x.id = i.album_id"
                . " AND x.password IS NOT NULL AND x.password <> ''))";
        }
        return $sql;
    }

    public static function create(array $data): int
    {
        $data['created_at'] = $data['created_at'] ?? now();
        $data['updated_at'] = now();

        $model = new self();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    public static function find(int $id): ?array
    {
        $row = self::row(static::query()->find($id));
        return $row === null ? null : self::cast($row);
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        $row = self::row(
            static::query()->where('id', $id)->where('user_id', $userId)->first()
        );
        return $row === null ? null : self::cast($row);
    }

    public static function findBySha1(string $sha1, int $userId): ?array
    {
        $row = self::row(
            static::query()
                ->where('sha1', $sha1)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first()
        );
        return $row === null ? null : self::cast($row);
    }

    /**
     * 后台图片列表。
     *
     * 过滤条件（回收站 / 用户 / 相册 / 状态 / 关键字）和排序都拼在同一段 WHERE 里，
     * 还要 JOIN 相册和用户两张表并统计总数，整段保留原生 SQL，避免行为漂移。
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['trashed'])) {
            $where[] = 'i.deleted_at IS NOT NULL';
        } else {
            $where[] = 'i.deleted_at IS NULL';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'i.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['album_id'])) {
            if ((int) $filters['album_id'] === -1) {
                $where[] = 'i.album_id IS NULL';
            } else {
                $where[] = 'i.album_id = :album_id';
                $params['album_id'] = (int) $filters['album_id'];
            }
        }
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $where[] = 'i.status = :status';
            $params['status'] = (int) $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(i.name LIKE :kw OR i.original_name LIKE :kw)';
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('images') . ' i WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $sort = ($filters['sort'] ?? '') === 'oldest' ? 'ASC' : 'DESC';

        $items = DB::fetchAll(
            'SELECT i.*, a.name AS album_name, u.name AS user_name
             FROM ' . DB::table('images') . ' i
             LEFT JOIN ' . DB::table('albums') . ' a ON a.id = i.album_id
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = i.user_id
             WHERE ' . $whereSql . ' ORDER BY i.id ' . $sort . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'     => array_map([self::class, 'cast'], $items),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public static function recentForUser(int $userId, int $limit = 12): array
    {
        $rows = static::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit((int) $limit)
            ->get();

        return array_map([self::class, 'cast'], self::rows($rows));
    }

    /**
     * 软删除。
     *
     *原先就是手写 deleted_at 字段，不挂 Laravel 的 SoftDeletes，行为保持原样。
     */
    public static function softDelete(int $id): void
    {
        static::query()->where('id', $id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function restore(int $id): void
    {
        static::query()->where('id', $id)->update([
            'deleted_at' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     *原先叫 forceDelete()，但 Eloquent 父类的 forceDelete() 是实例方法，
     * 静态同名会直接报致命错误（Cannot make non static method ... static），
     * 所以改成 forceDeleteById()，调用方已同步改名。
     */
    public static function forceDeleteById(int $id): void
    {
        static::query()->where('id', $id)->delete();
    }

    public static function countAll(): int
    {
        return (int) static::query()->whereNull('deleted_at')->count();
    }

    public static function countToday(): int
    {
        return (int) static::query()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();
    }

    public static function sumSize(?int $userId = null): int
    {
        $query = static::query()->whereNull('deleted_at');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return (int) $query->sum('size');
    }



    /**
     * 按天统计上传量。
     *
     * GROUP BY DATE(created_at) 保留原生 SQL。
     */
    public static function dailySeries(int $days = 7, ?int $userId = null): array
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[date('Y-m-d', strtotime('-' . $i . ' days'))] = 0;
        }
        $sql = 'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ' . DB::table('images') . '
                WHERE deleted_at IS NULL AND created_at >= :t';
        $params = ['t' => date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'))];
        if ($userId !== null) {
            $sql .= ' AND user_id = :uid';
            $params['uid'] = $userId;
        }
        $sql .= ' GROUP BY DATE(created_at)';
        foreach (DB::fetchAll($sql, $params) as $row) {
            $key = substr((string) $row['d'], 0, 10);
            if (isset($series[$key])) {
                $series[$key] = (int) $row['c'];
            }
        }
        return $series;
    }

    public static function findByKey(string $key): ?array
    {
        $row = self::row(
            static::query()
                ->where('sha1', $key)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first()
        );
        return $row === null ? null : self::cast($row);
    }

    public static function incrementViews(int $id): void
    {
        try {
            static::query()->where('id', $id)->increment('views');
        } catch (\Throwable $e) {
        }
    }

    /**
     * 图片广场。
     *
     * 多表 JOIN + 动态排序，保留原生 SQL。
     */
    public static function explore(array $filters, int $page, int $perPage): array
    {
        $where = ['i.deleted_at IS NULL', 'i.is_public = 1', 'i.status = 1'];
        $params = [];

        // 加密图仍然进广场，但缩略图由服务端换成马赛克 + 锁标，正文内容不出站

        if (!empty($filters['keyword'])) {
            $where[] = '(i.name LIKE :kw OR i.original_name LIKE :kw OR i.tags LIKE :kw)';
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }
        if (!empty($filters['tag'])) {
            $where[] = 'i.tags LIKE :tag';
            $params['tag'] = '%' . $filters['tag'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'i.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }
        if (!empty($filters['album_id'])) {
            $where[] = 'i.album_id = :aid';
            $params['aid'] = (int) $filters['album_id'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('images') . ' i WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);

        $order = match ((string) ($filters['sort'] ?? 'newest')) {
            'hot'   => 'i.views DESC, i.id DESC',
            'oldest' => 'i.id ASC',
            'size'  => 'i.size DESC',
            default => 'i.id DESC',
        };

        $items = DB::fetchAll(
            'SELECT i.*, a.password AS album_password, u.name AS user_name, u.avatar AS user_avatar, a.name AS album_name
             FROM ' . DB::table('images') . ' i
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = i.user_id
             LEFT JOIN ' . DB::table('albums') . ' a ON a.id = i.album_id
             WHERE ' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'     => array_map([self::class, 'cast'], $items),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * 相关推荐。
     *
     * 里面嵌了密码闸门的 NOT EXISTS 子查询，保留原生 SQL。
     */
    public static function related(array $image, int $limit = 6): array
    {
        $rows = DB::fetchAll(
            'SELECT i.* FROM ' . DB::table('images') . ' i
             WHERE i.deleted_at IS NULL AND i.is_public = 1 AND i.status = 1 AND i.id <> :id
               AND ' . self::publicLockGuard() . '
               AND (i.album_id = :aid OR i.user_id = :uid)
             ORDER BY i.views DESC, i.id DESC LIMIT ' . (int) $limit,
            [
                'id'  => (int) $image['id'],
                'aid' => (int) ($image['album_id'] ?? 0),
                'uid' => (int) $image['user_id'],
            ]
        );
        return array_map([self::class, 'cast'], $rows);
    }

    public static function tagCloud(int $limit = 20): array
    {
        $rows = DB::fetchAll(
            'SELECT i.tags FROM ' . DB::table('images') . ' i
             WHERE i.deleted_at IS NULL AND i.is_public = 1 AND i.status = 1
               AND ' . self::publicLockGuard() . '
               AND i.tags IS NOT NULL AND i.tags <> "" ORDER BY i.id DESC LIMIT 3000'
        );
        $counts = [];
        foreach ($rows as $row) {
            foreach (preg_split('/[,，\s]+/u', (string) $row['tags']) as $tag) {
                $tag = trim($tag);
                if ($tag === '' || mb_strlen($tag) > 20) {
                    continue;
                }
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：取出来的值类型必须和旧版完全一样，
     * 不靠 Eloquent 的 $casts，免得两边规则打架。
     */
    public static function cast(array $row): array
    {
        $row['id']         = (int) $row['id'];
        $row['user_id']    = (int) $row['user_id'];
        $row['album_id']   = $row['album_id'] === null ? null : (int) $row['album_id'];
        $row['storage_id'] = (int) $row['storage_id'];
        $row['size']       = (int) $row['size'];
        $row['width']      = (int) $row['width'];
        $row['height']     = (int) $row['height'];
        $row['status']     = (int) $row['status'];
        $row['is_public']  = (int) $row['is_public'];
        $row['views']      = (int) ($row['views'] ?? 0);
        // url 兜底：这一列存的是上传时由存储驱动写入的绝对地址，正常都有值。
        // 但早期版本导入的示例图是空的，页面直接取 $image['url'] 就会渲染成
        // <img src="">（浏览器里是裂图），这里按 pathname 补一个能用的地址。
        if (trim((string) ($row['url'] ?? '')) === '') {
            $pathname = ltrim((string) ($row['pathname'] ?? ''), '/');
            if ($pathname !== '') {
                $row['url'] = url('uploads/' . $pathname);
            }
        }
        return $row;
    }
}
