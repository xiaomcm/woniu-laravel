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
 * 用户反馈。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent：
 * 单表增删改查走查询构造器，带表别名的分页 JOIN 保留原生 SQL。
 * 对外契约不变：find/create 返回值是数组，列表返回行数组的数组。
 */
class Feedback extends BaseModel
{
    protected $table = 'feedbacks';

    use Concerns\ReturnsRows;

    public const STATUS_NEW     = 0;
    public const STATUS_READ    = 1;
    public const STATUS_REPLIED = 2;

    public static function kinds(): array
    {
        return ['suggestion' => '功能建议', 'bug' => '问题反馈', 'other' => '其他'];
    }

    public static function create(array $data): int
    {
        $data['status'] = self::STATUS_NEW;
        $data['created_at'] = now();

        $model = new self();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    /**
     * 后台反馈列表。
     *
     * 要 LEFT JOIN 用户表拿昵称，且带表别名 f / u，保留原生 SQL。
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $where[] = 'f.status = :st';
            $params['st'] = (int) $filters['status'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('feedbacks') . ' f WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = DB::fetchAll(
            'SELECT f.*, u.name AS user_name
             FROM ' . DB::table('feedbacks') . ' f
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = f.user_id
             WHERE ' . $whereSql . ' ORDER BY f.status ASC, f.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'     => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public static function find(int $id): ?array
    {
        return self::row(static::query()->find($id));
    }

    public static function reply(int $id, string $reply): void
    {
        static::query()->where('id', $id)->update([
            'reply'      => $reply,
            'status'     => self::STATUS_REPLIED,
            'replied_at' => now(),
        ]);
    }

    /**
     * 标记为已读。只处理还没读过的，已回复的不再改状态。
     */
    public static function markRead(int $id): void
    {
        static::query()
            ->where('id', $id)
            ->where('status', self::STATUS_NEW)
            ->update(['status' => self::STATUS_READ]);
    }

    public static function countNew(): int
    {
        return (int) static::query()->where('status', self::STATUS_NEW)->count();
    }
}
