<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

/**
 * 单页。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find/create 返回值是数组，列表返回行数组的数组。
 */
class Page extends BaseModel
{
    protected $table = 'pages';

    use Concerns\ReturnsRows;

    /**
     *原先叫 all()，但 Eloquent 父类的 all() 是静态方法且带 $columns 参数，
     * 签名对不上会直接 PHP 致命错误，所以改成 allRows()，调用方已同步改名。
     */
    public static function allRows(bool $onlyActive = false): array
    {
        $query = static::query();
        if ($onlyActive) {
            $query->where('status', 1);
        }
        return self::rows(
            $query->orderBy('sortable')->orderBy('id')->get()
        );
    }

    public static function footerPages(): array
    {
        return self::rows(
            static::query()
                ->select(['id', 'title', 'slug'])
                ->where('status', 1)
                ->where('show_in_footer', 1)
                ->orderBy('sortable')
                ->orderBy('id')
                ->get()
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::row(static::query()->where('slug', $slug)->first());
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

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $query = static::query()->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        return (int) $query->count() > 0;
    }
}
