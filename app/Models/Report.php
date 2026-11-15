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
 * 举报。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find() 返回行数组，create() 返回 id，列表返回行数组的数组。
 */
class Report extends BaseModel
{
    protected $table = 'reports';

    use Concerns\ReturnsRows;

    public const STATUS_PENDING  = 0;
    public const STATUS_HANDLED  = 1;
    public const STATUS_REJECTED = 2;

    public static function categories(): array
    {
        return [
            'porn'      => '色情低俗',
            'violence'  => '暴力血腥',
            'politics'  => '违规政治',
            'copyright' => '侵权盗版',
            'spam'      => '垃圾广告',
            'minor'     => '涉及未成年人',
            'other'     => '其他',
        ];
    }

    public static function categoryLabel(string $key): string
    {
        return self::categories()[$key] ?? '其他';
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_HANDLED  => '已处理',
            self::STATUS_REJECTED => '已驳回',
            default               => '待处理',
        };
    }

    public static function statusTone(int $status): string
    {
        return match ($status) {
            self::STATUS_HANDLED  => 'success',
            self::STATUS_REJECTED => 'muted',
            default               => 'warning',
        };
    }

    public static function create(array $data): int
    {
        $data['status'] = self::STATUS_PENDING;
        $data['created_at'] = now();

        $model = new self();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    /**
     * 后台举报列表。
     *
     * 一张举报要同时 JOIN 图片、用户、相册三张表，保留原生 SQL。
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $where[] = 'r.status = :st';
            $params['st'] = (int) $filters['status'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('reports') . ' r WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = DB::fetchAll(
            'SELECT r.*, i.name AS image_name, i.sha1 AS image_sha1, i.pathname AS image_path,
                    u.name AS reporter_name, a.name AS album_name
             FROM ' . DB::table('reports') . ' r
             LEFT JOIN ' . DB::table('images') . ' i ON i.id = r.image_id
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = r.reporter_id
             LEFT JOIN ' . DB::table('albums') . ' a ON a.id = r.album_id
             WHERE ' . $whereSql . ' ORDER BY r.status ASC, r.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
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

    public static function handle(int $id, int $status, int $handlerId, string $note): void
    {
        static::query()->where('id', $id)->update([
            'status'      => $status,
            'handler_id'  => $handlerId,
            'handle_note' => mb_substr($note, 0, 500),
            'handled_at'  => now(),
        ]);
    }

    public static function countPending(): int
    {
        return (int) static::query()->where('status', self::STATUS_PENDING)->count();
    }

    public static function find(int $id): ?array
    {
        return self::row(static::query()->find($id));
    }

    /**
     * 删除一条举报记录。举报是独立记录，不牵连图片本身，
     * 所以这里只清 reports 表，图片的下线状态由处理接口单独控制。
     *
     *原先叫 delete()，但 Eloquent 的 delete() 是实例方法，静态同名会直接报致命错误，
     * 所以改成 deleteById()，调用方已同步改名。
     */
    public static function deleteById(int $id): bool
    {
        return static::query()->where('id', $id)->delete() > 0;
    }
}
