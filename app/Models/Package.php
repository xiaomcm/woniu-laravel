<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

/**
 * 套餐。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find/create 返回值是数组，列表返回行数组的数组。
 */
class Package extends BaseModel
{
    protected $table = 'packages';

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
        $rows = self::rows(
            $query->orderBy('sortable')->orderBy('id')->get()
        );
        return array_map([self::class, 'cast'], $rows);
    }

    public static function find(int $id): ?array
    {
        $row = self::row(static::query()->find($id));
        return $row === null ? null : self::cast($row);
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

    public static function features(array $row): array
    {
        $raw = json_decode((string) ($row['features'] ?? '[]'), true);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $raw)));
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：price_text / features_list 是派生字段，
     * Eloquent 的 $casts 表达不了这两个规则，保留原方法最稳。
     */
    public static function cast(array $row): array
    {
        $row['id']             = (int) $row['id'];
        $row['group_id']       = (int) $row['group_id'];
        $row['price_cents']    = (int) $row['price_cents'];
        $row['duration_days']  = (int) $row['duration_days'];
        $row['capacity']       = (int) $row['capacity'];
        $row['max_file_size']  = (int) $row['max_file_size'];
        $row['daily_limit']    = (int) $row['daily_limit'];
        $row['purchase_limit'] = (int) ($row['purchase_limit'] ?? 0);
        $row['status']         = (int) $row['status'];
        $row['sortable']       = (int) $row['sortable'];
        $row['price_text']     = number_format($row['price_cents'] / 100, 2, '.', '');
        $row['features_list']  = self::features($row);

        // duration_days = 0 表示永久有效（不限制）。
        // 与 capacity / max_file_size / daily_limit 的「0 为不限」语义保持一致。
        $row['is_permanent']   = $row['duration_days'] <= 0;
        $row['duration_text']  = $row['is_permanent'] ? '永久' : $row['duration_days'] . ' 天';

        // purchase_limit = 0 表示不限购买次数；N 表示每个用户最多购买 N 次。
        $row['is_limit_purchase']   = $row['purchase_limit'] > 0;
        $row['purchase_limit_text'] = $row['is_limit_purchase']
            ? '限购 ' . $row['purchase_limit'] . ' 次'
            : '不限';

        return $row;
    }
}
