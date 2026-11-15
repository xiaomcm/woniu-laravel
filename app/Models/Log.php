<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;
use App\Core\Request;

/**
 * 操作日志。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent：
 * 写入与批量清理走查询构造器，带表别名的多表筛选保留原生 SQL。
 * 对外契约不变：方法名、签名、返回结构一个字没改。
 */
class Log extends BaseModel
{
    protected $table = 'logs';

    use Concerns\ReturnsRows;

    public static function record(?int $userId, string $action, string $detail = ''): void
    {
        try {
            $model = new self();
            $model->forceFill([
                'user_id'    => $userId,
                'action'     => $action,
                'ip'         => Request::ip(),
                'ua'         => Request::ua(),
                'detail'     => $detail,
                'created_at' => now(),
            ]);
            $model->save();
        } catch (\Throwable $e) {


        }
    }

    public static function paginate(int $page, int $perPage): array
    {
        return self::search([], $page, $perPage);
    }

    /**
     * 带筛选的日志分页：关键词命中动作/详情/IP，可按动作、用户、时间范围过滤
     *
     * 要 LEFT JOIN 用户表拿昵称，且带表别名 l / u，保留原生 SQL。
     */
    public static function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(l.action LIKE :kw OR l.detail LIKE :kw OR l.ip LIKE :kw)';
            $params['kw'] = '%' . $keyword . '%';
        }
        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'l.action = :act';
            $params['act'] = $action;
        }
        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'l.user_id = :uid';
            $params['uid'] = $userId;
        }
        $days = (int) ($filters['days'] ?? 0);
        if ($days > 0) {
            $where[] = 'l.created_at >= :since';
            $params['since'] = date('Y-m-d H:i:s', time() - $days * 86400);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('logs') . ' l WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);

        $items = DB::fetchAll(
            'SELECT l.*, u.name AS user_name FROM ' . DB::table('logs') . ' l
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = l.user_id
             WHERE ' . $whereSql . '
             ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * 批量删除：一条 SQL 里做 IN 展开，参数仍然走绑定
     */
    public static function deleteIds(array $ids): int
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        if ($clean === []) {
            return 0;
        }
        return (int) static::query()->whereIn('id', array_values($clean))->delete();
    }

    /**
     * 清空日志：$days = 0 表示全部，否则只清更早的
     */
    public static function clear(int $days = 0): int
    {
        if ($days <= 0) {
            return (int) static::query()->delete();
        }
        return (int) static::query()
            ->where('created_at', '<', date('Y-m-d H:i:s', time() - $days * 86400))
            ->delete();
    }

    /**
     * 动作去重清单，用于筛选下拉
     *
     * GROUP BY 聚合，保留原生 SQL。
     */
    public static function actions(): array
    {
        $rows = DB::fetchAll('SELECT action, COUNT(*) AS total FROM ' . DB::table('logs') . ' GROUP BY action ORDER BY total DESC');
        return $rows;
    }
}
