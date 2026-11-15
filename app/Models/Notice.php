<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

/**
 * 站内公告。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find() 返回行数组，create() 返回 id，列表返回行数组的数组。
 */
class Notice extends BaseModel
{
    protected $table = 'notices';

    use Concerns\ReturnsRows;

    /**
     * 前台展示的公告。
     *
     * 过期时间是三选一（空 / 空串 / 未到期），这里用闭包分组，避免 OR 条件漏掉括号。
     */
    public static function active(int $limit = 5): array
    {
        return self::rows(
            static::query()
                ->where('status', 1)
                ->where(function ($builder): void {
                    $builder->whereNull('expire_at')
                        ->orWhere('expire_at', '')
                        ->orWhere('expire_at', '>', now());
                })
                ->orderByDesc('pinned')
                ->orderByDesc('id')
                ->limit((int) $limit)
                ->get()
        );
    }

    /**
     * 需要以「全站弹窗」形式展示的公告。
     *
     * 条件与 active() 一致（展示中 + 未过期），额外要求 popup = 1。
     * 旧库还没有 popup 列时查询会抛错，调用方需自行 try/catch 兜底。
     */
    public static function popupNotices(int $limit = 3): array
    {
        return self::rows(
            static::query()
                ->where('status', 1)
                ->where('popup', 1)
                ->where(function ($builder): void {
                    $builder->whereNull('expire_at')
                        ->orWhere('expire_at', '')
                        ->orWhere('expire_at', '>', now());
                })
                ->orderByDesc('pinned')
                ->orderByDesc('id')
                ->limit((int) $limit)
                ->get()
        );
    }

    /**
     *原先叫 all()，但 Eloquent 父类的 all() 是静态方法且带 $columns 参数，
     * 签名对不上会直接 PHP 致命错误，所以改成 allRows()，调用方已同步改名。
     */    public static function allRows(): array
    {
        return self::rows(
            static::query()
                ->orderByDesc('pinned')
                ->orderByDesc('id')
                ->get()
        );
    }

    public static function find(int $id): ?array
    {
        return self::row(static::query()->find($id));
    }

    public static function create(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $model = new self();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    /**
     *原先叫 update()，但 Eloquent 的 update() 是实例方法，静态同名会直接报致命错误，
     * 所以改成 updateById()，调用方已同步改名。
     */
    public static function updateById(int $id, array $data): void
    {
        $data['updated_at'] = now();

        static::query()->where('id', $id)->update($data);
    }

    /**
     * 同 updateById()，改名原因一致。
     */
    public static function deleteById(int $id): void
    {
        static::query()->where('id', $id)->delete();
    }

    public static function levels(): array
    {
        return ['info' => '提示', 'success' => '好消息', 'warning' => '注意', 'danger' => '重要'];
    }
}
