<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Models\Storage as StorageModel;

class Manager
{
    private static array $map = [
        'local'  => LocalStorage::class,
        's3'     => S3Storage::class,
        'oss'    => OssStorage::class,
        'cos'    => CosStorage::class,
        'qiniu'  => QiniuStorage::class,
        'upyun'  => UpyunStorage::class,
        'ftp'    => FtpStorage::class,
        'webdav' => WebdavStorage::class,
        'sftp'   => SftpStorage::class,
        'minio'  => MinioStorage::class,
    ];

    public static function make(array $storage): Driver
    {
        $type = (string) ($storage['type'] ?? 'local');
        $class = self::$map[$type] ?? LocalStorage::class;
        return new $class($storage);
    }

    public static function of(?int $storageId): ?Driver
    {
        if ($storageId === null) {
            return null;
        }
        $storage = StorageModel::find($storageId);
        return $storage === null ? null : self::make($storage);
    }

    public static function default(): ?Driver
    {
        $storage = StorageModel::defaultStorage();
        return $storage === null ? null : self::make($storage);
    }

    /**
     * 测试指定存储是否可正常读写
     * @return array{ok:bool,message:string}
     */
    public static function test(int $storageId): array
    {
        $driver = self::of($storageId);
        if ($driver === null) {
            return ['ok' => false, 'message' => '存储不存在'];
        }
        return $driver->test();
    }
}
