<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

/**
 * 存储桶。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find/create 返回值是数组，列表返回行数组的数组。
 */
class Storage extends BaseModel
{
    protected $table = 'storages';

    use Concerns\ReturnsRows;

    public static function types(): array
    {
        return [
            'local'   => '本地存储',
            's3'      => 'AWS S3 / 兼容',
            'oss'     => '阿里云 OSS',
            'cos'     => '腾讯云 COS',
            'qiniu'   => '七牛云 Kodo',
            'upyun'   => '又拍云 USS',
            'ftp'     => 'FTP',
            'webdav'  => 'WebDAV',
            'sftp'    => 'SFTP',
            'minio'   => 'MinIO',
        ];
    }

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

    public static function enabled(): array
    {
        $rows = self::rows(
            static::query()->where('status', 1)->orderBy('id')->get()
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

    public static function defaultStorage(): ?array
    {
        $row = self::row(
            static::query()
                ->where('status', 1)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first()
        );
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

    public static function imageCount(int $id): int
    {
        // images 表没有软删除过滤器，deleted_at 只是普通字段，统计口径与既有行为一致
        return (int) Image::query()->where('storage_id', $id)->count();
    }

    public static function config(array $storage): array
    {
        $config = json_decode((string) ($storage['config'] ?? ''), true);
        return is_array($config) ? $config : [];
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：config_array 是从 config 字段派生的，
     * Eloquent 的 $casts 表达不了这个规则，保留原方法最稳。
     */
    public static function cast(array $row): array
    {
        $row['id']         = (int) $row['id'];
        $row['is_default'] = (int) $row['is_default'];
        $row['status']     = (int) $row['status'];
        $row['config_array'] = self::config($row);
        return $row;
    }
}
