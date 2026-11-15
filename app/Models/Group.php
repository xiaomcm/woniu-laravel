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
 * 用户分组。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent：
 * 简单的增删改查走查询构造器，多表 JOIN / 中间表保留原生 SQL。
 * 对外契约一个字没改：find/create 返回值是数组，
 * 调用方 $group['name'] 这种取法不用改。
 */
class Group extends BaseModel
{
    protected $table = 'groups';

    use Concerns\ReturnsRows;

    /**
     *原先叫 all()，但 Eloquent 父类的 all() 是静态方法且带 $columns 参数，
     * 签名对不上会直接 PHP 致命错误，所以改成 allRows()，调用方已同步改名。
     */
    public static function allRows(): array
    {
        $rows = self::rows(
            static::query()->orderBy('id')->get()
        );
        return array_map([self::class, 'cast'], $rows);
    }

    public static function find(?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        $row = self::row(static::query()->find($id));
        return $row === null ? null : self::cast($row);
    }

    public static function defaultGroup(): ?array
    {
        $row = self::row(
            static::query()->where('is_default', 1)->orderBy('id')->first()
        );
        if ($row === null) {
            $row = self::row(static::query()->orderBy('id')->first());
        }
        return $row === null ? null : self::cast($row);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = now();

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

    public static function userCount(int $id): int
    {
        return (int) User::query()->where('group_id', $id)->count();
    }



    public static function allowedExtensions(array $group): array
    {
        $raw = (string) ($group['allowed_extensions'] ?? '');
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }

    public static function permissionKeys(): array
    {
        return ['upload', 'album', 'api', 'explore', 'download', 'watermark', 'guest'];
    }

    public static function permissions(array $group): array
    {
        $raw = (string) ($group['permissions'] ?? '');
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $permissions = [];
        foreach (self::permissionKeys() as $key) {
            if (is_array($decoded)) {
                $permissions[$key] = !empty($decoded[$key]);
            } else {
                $permissions[$key] = true;
            }
        }
        return $permissions;
    }

    public static function can(?array $group, string $key): bool
    {
        if ($group === null) {
            return false;
        }
        return !empty(self::permissions($group)[$key]);
    }

    public static function storageIds(int $groupId): array
    {
        // 分组与存储的对应关系放在中间表 group_storage，没有对应的 Eloquent 模型，
        // 这里保留原生 SQL。
        $rows = DB::fetchAll(
            'SELECT storage_id FROM ' . DB::table('group_storage') . ' WHERE group_id = :g',
            ['g' => $groupId]
        );
        return array_map(static fn ($r) => (int) $r['storage_id'], $rows);
    }

    /**
     * 分组可用的存储列表。
     *
     * storages JOIN group_storage 且带表别名 s / gs，保留原生 SQL。
     */
    public static function storages(int $groupId): array
    {
        return DB::fetchAll(
            'SELECT s.* FROM ' . DB::table('storages') . ' s
             INNER JOIN ' . DB::table('group_storage') . ' gs ON gs.storage_id = s.id
             WHERE gs.group_id = :g AND s.status = 1
             ORDER BY s.is_default DESC, s.id ASC',
            ['g' => $groupId]
        );
    }

    public static function syncStorages(int $groupId, array $storageIds): void
    {
        // 中间表没有 Eloquent 模型，保留原生 SQL
        DB::query('DELETE FROM ' . DB::table('group_storage') . ' WHERE group_id = :g', ['g' => $groupId]);
        foreach (array_unique(array_map('intval', $storageIds)) as $sid) {
            if ($sid <= 0) {
                continue;
            }
            try {
                DB::insert('group_storage', ['group_id' => $groupId, 'storage_id' => $sid]);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function storageAllowed(?array $group, int $storageId): bool
    {
        if ($group === null || $storageId <= 0) {
            return true;
        }
        $ids = self::storageIds((int) $group['id']);
        if ($ids === []) {
            return true;
        }
        return in_array($storageId, $ids, true);
    }

    public static function buyable(): array
    {
        return array_values(array_filter(self::allRows(), static fn ($g) => (int) ($g['buyable'] ?? 0) === 1));
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：取出来的值类型必须和旧版完全一样，
     * 不靠 Eloquent 的 $casts，免得两边规则打架。
     */
    public static function cast(array $row): array
    {
        $row['id']             = (int) $row['id'];
        $row['is_default']     = (int) $row['is_default'];
        $row['capacity']       = (int) $row['capacity'];
        $row['max_file_size']  = (int) $row['max_file_size'];
        $row['max_upload_num'] = (int) $row['max_upload_num'];
        $row['daily_limit']    = (int) ($row['daily_limit'] ?? 0);
        $row['watermark']      = (int) $row['watermark'];
        $row['buyable']        = (int) ($row['buyable'] ?? 0);
        $row['default_storage_id'] = $row['default_storage_id'] === null ? null : (int) $row['default_storage_id'];
        return $row;
    }
}
