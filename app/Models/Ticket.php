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
 * 工单。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find() 返回行数组，create() 返回 id，列表返回行数组的数组。
 */
class Ticket extends BaseModel
{
    protected $table = 'tickets';

    use Concerns\ReturnsRows;

    public const STATUS_OPEN       = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_REPLIED    = 2;
    public const STATUS_CLOSED     = 3;

    public static function categories(): array
    {
        return [
            'upload'   => '上传问题',
            'storage'  => '存储异常',
            'account'  => '账号相关',
            'payment'  => '支付与套餐',
            'abuse'    => '内容举报',
            'other'    => '其他',
        ];
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_REPLIED    => '已回复',
            self::STATUS_CLOSED     => '已关闭',
            default                 => '待处理',
        };
    }

    public static function statusTone(int $status): string
    {
        return match ($status) {
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_REPLIED    => 'success',
            self::STATUS_CLOSED     => 'muted',
            default                 => 'warning',
        };
    }

    public static function create(int $userId, string $subject, string $category, int $priority, string $content): int
    {
        $model = new self();
        $model->forceFill([
            'user_id'       => $userId,
            'subject'       => mb_substr($subject, 0, 200),
            'category'      => $category,
            'priority'      => max(0, min(2, $priority)),
            'status'        => self::STATUS_OPEN,
            'last_reply_at' => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $model->save();

        $id = (int) $model->getKey();
        self::reply($id, $userId, false, $content);
        return $id;
    }

    public static function reply(int $ticketId, int $userId, bool $isAdmin, string $content): void
    {
        // 回复表不是本模型的表，走裸 SQL 插入
        DB::insert('ticket_replies', [
            'ticket_id'  => $ticketId,
            'user_id'    => $userId,
            'is_admin'   => $isAdmin ? 1 : 0,
            'content'    => $content,
            'created_at' => now(),
        ]);
        static::query()->where('id', $ticketId)->update([
            'status'        => $isAdmin ? self::STATUS_REPLIED : self::STATUS_PROCESSING,
            'last_reply_at' => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * 工单回复列表。
     *
     * 要 JOIN 用户表拿昵称和头像，保留原生 SQL。
     */
    public static function replies(int $ticketId): array
    {
        $rows = DB::fetchAll(
            'SELECT r.*, u.name AS user_name, u.avatar AS user_avatar
             FROM ' . DB::table('ticket_replies') . ' r
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = r.user_id
             WHERE r.ticket_id = :t ORDER BY r.id ASC',
            ['t' => $ticketId]
        );
        foreach ($rows as &$row) {
            $row['is_admin'] = (int) $row['is_admin'];
        }
        return $rows;
    }

    public static function find(int $id): ?array
    {
        return self::row(static::query()->find($id));
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        return self::row(
            static::query()->where('id', $id)->where('user_id', $userId)->first()
        );
    }

    /**
     * 工单列表。
     *
     * 要 JOIN 用户表做关键字搜索，还要用子查询统计回复数，保留原生 SQL。
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 't.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $where[] = 't.status = :st';
            $params['st'] = (int) $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(t.subject LIKE :kw OR u.name LIKE :kw)';
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value(
            'SELECT COUNT(*) FROM ' . DB::table('tickets') . ' t LEFT JOIN ' . DB::table('users') . ' u ON u.id = t.user_id WHERE ' . $whereSql,
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $rows = DB::fetchAll(
            'SELECT t.*, u.name AS user_name, u.email AS user_email,
                    (SELECT COUNT(*) FROM ' . DB::table('ticket_replies') . ' r WHERE r.ticket_id = t.id) AS reply_count
             FROM ' . DB::table('tickets') . ' t
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = t.user_id
             WHERE ' . $whereSql . ' ORDER BY t.status ASC, t.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
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

    public static function close(int $id): void
    {
        static::query()->where('id', $id)->update([
            'status'     => self::STATUS_CLOSED,
            'updated_at' => now(),
        ]);
    }

    public static function reopen(int $id): void
    {
        static::query()->where('id', $id)->update([
            'status'     => self::STATUS_PROCESSING,
            'updated_at' => now(),
        ]);
    }

    public static function countOpen(): int
    {
        return (int) static::query()->whereIn('status', [0, 1])->count();
    }

    public static function countForUser(int $userId): int
    {
        return (int) static::query()->where('user_id', $userId)->count();
    }

    /**
     * 删除工单，连同它下面的全部回复。
     *
     *原先叫 delete()，但 Eloquent 的 delete() 是实例方法，静态同名会直接报致命错误，
     * 所以改成 deleteById()，调用方已同步改名。
     */
    public static function deleteById(int $id): bool
    {
        return (bool) DB::transaction(function () use ($id): bool {
            DB::delete('ticket_replies', 'ticket_id = :t', ['t' => $id]);
            return static::query()->where('id', $id)->delete() > 0;
        });
    }
}
