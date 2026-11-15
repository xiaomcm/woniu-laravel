<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;
use App\Drivers\Driver as DriverContract;
use App\Drivers\Factory;

/**
 * 驱动器。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent：
 * drivers 表自身的增删改查走查询构造器，
 * group_driver / storage_driver 两张中间表没有对应的 Eloquent 模型，保留原生 SQL。
 * 对外契约不变：find/create 返回值是数组，列表返回行数组的数组。
 */
class Driver extends BaseModel
{
    protected $table = 'drivers';

    use Concerns\ReturnsRows;

    /**
     *原先叫 all()，但 Eloquent 父类的 all() 是静态方法且带 $columns 参数，
     * 签名对不上会直接 PHP 致命错误，所以改成 allRows()，调用方已同步改名。
     */
    public static function allRows(?string $type = null): array
    {
        $query = static::query()->orderBy('sortable')->orderBy('id');

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        return self::rows($query->get());
    }

    public static function active(string $type): array
    {
        return self::rows(
            static::query()
                ->where('type', $type)
                ->where('status', 1)
                ->orderBy('sortable')
                ->orderBy('id')
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
     *
     * 顺带把驱动器挂到分组/存储上的关联一起清掉，
     * 中间表没有 Eloquent 模型，这两句保留原生 SQL。
     */
    public static function deleteById(int $id): void
    {
        static::query()->where('id', $id)->delete();
        DB::query('DELETE FROM ' . DB::table('group_driver') . ' WHERE driver_id = :id', ['id' => $id]);
        DB::query('DELETE FROM ' . DB::table('storage_driver') . ' WHERE driver_id = :id', ['id' => $id]);
    }

    public static function config(array $row): array
    {
        $config = json_decode((string) ($row['config'] ?? '{}'), true);
        return is_array($config) ? $config : [];
    }

    public static function instance(array $row): ?DriverContract
    {
        return Factory::make($row);
    }

    /**
     * 取某个分组/存储下挂的驱动器。
     *
     * drivers 与中间表 JOIN 且带表别名 d / p，保留原生 SQL。
     */
    public static function pivot(string $kind, int $ownerId): array
    {
        $table = $kind === 'storage' ? 'storage_driver' : 'group_driver';
        $column = $kind === 'storage' ? 'storage_id' : 'group_id';
        return DB::fetchAll(
            'SELECT d.* FROM ' . DB::table('drivers') . ' d
             INNER JOIN ' . DB::table($table) . ' p ON p.driver_id = d.id
             WHERE p.' . $column . ' = :oid AND d.status = 1
             ORDER BY d.sortable ASC, d.id ASC',
            ['oid' => $ownerId]
        );
    }

    public static function pivotOf(string $kind, int $ownerId, string $type): array
    {
        $rows = self::pivot($kind, $ownerId);
        return array_values(array_filter($rows, static fn ($r) => (string) $r['type'] === $type));
    }

    /**
     * 反查：某个驱动器挂在哪些分组/存储下。
     *
     * 中间表没有 Eloquent 模型，保留原生 SQL。
     */
    public static function ownerIds(string $kind, int $driverId): array
    {
        $table = $kind === 'storage' ? 'storage_driver' : 'group_driver';
        $column = $kind === 'storage' ? 'storage_id' : 'group_id';
        $rows = DB::fetchAll(
            'SELECT ' . $column . ' AS oid FROM ' . DB::table($table) . ' WHERE driver_id = :id',
            ['id' => $driverId]
        );
        return array_map(static fn ($r) => (int) $r['oid'], $rows);
    }

    public static function syncOwners(string $kind, int $driverId, array $ownerIds): void
    {
        $table = $kind === 'storage' ? 'storage_driver' : 'group_driver';
        $column = $kind === 'storage' ? 'storage_id' : 'group_id';
        DB::query('DELETE FROM ' . DB::table($table) . ' WHERE driver_id = :id', ['id' => $driverId]);
        foreach (array_unique(array_map('intval', $ownerIds)) as $oid) {
            if ($oid <= 0) {
                continue;
            }
            try {
                DB::insert($table, [$column => $oid, 'driver_id' => $driverId]);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function syncPivot(string $kind, int $ownerId, array $driverIds): void
    {
        $table = $kind === 'storage' ? 'storage_driver' : 'group_driver';
        $column = $kind === 'storage' ? 'storage_id' : 'group_id';
        DB::query('DELETE FROM ' . DB::table($table) . ' WHERE ' . $column . ' = :oid', ['oid' => $ownerId]);
        foreach (array_unique(array_map('intval', $driverIds)) as $did) {
            if ($did <= 0) {
                continue;
            }
            try {
                DB::insert($table, [$column => $ownerId, 'driver_id' => $did]);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 解析某角色组在某个存储上真正生效的驱动器，组级配置优先于存储级
     */
    public static function resolve(?int $groupId, int $storageId, string $type): ?array
    {
        if ($groupId !== null && $groupId > 0) {
            $rows = self::pivotOf('group', $groupId, $type);
            if ($rows !== []) {
                return $rows[0];
            }
        }
        $rows = self::pivotOf('storage', $storageId, $type);
        return $rows[0] ?? null;
    }

    /**
     * 各类驱动器的数量，GROUP BY 聚合，保留原生 SQL。
     */
    public static function countByType(): array
    {
        $rows = DB::fetchAll('SELECT type, COUNT(*) AS c FROM ' . DB::table('drivers') . ' GROUP BY type');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['type']] = (int) $row['c'];
        }
        return $out;
    }
}
