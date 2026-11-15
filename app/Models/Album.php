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
 * 相册。
 *
 * 对外仍然保留既有的静态方法契约：find/create 返回值是数组，
 * 调用方 $album['name'] 这种取法不用改。
 *
 * 类型转换统一走 cast()（既有的原方法），保证取出来的值类型和旧版完全一致。
 */
class Album extends BaseModel
{
    protected $table = 'albums';

    use Concerns\ReturnsRows;

    public static function allForUser(int $userId): array
    {
        $rows = self::rows(
            static::query()->where('user_id', $userId)->orderByDesc('id')->get()
        );
        return array_map([self::class, 'cast'], $rows);
    }

    public static function find(int $id): ?array
    {
        $row = self::row(static::query()->find($id));
        return $row === null ? null : self::cast($row);
    }

    public static function findByKey(string $key): ?array
    {
        $row = self::row(static::query()->where('key', $key)->first());
        return $row === null ? null : self::cast($row);
    }

    public static function create(int $userId, string $name, string $intro, bool $isPublic): int
    {
        $model = new self();
        $model->forceFill([
            'user_id'    => $userId,
            'name'       => $name,
            'intro'      => $intro,
            'cover'      => null,
            'image_num'  => 0,
            'is_public'  => $isPublic ? 1 : 0,
            'key'        => $isPublic ? self::generateKey() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        // 图片跟相册解绑是跨表操作，继续走原生 SQL
        DB::update('images', ['album_id' => null], 'album_id = :id', ['id' => $id]);
        static::query()->where('id', $id)->delete();
    }

    public static function recount(int $id): void
    {
        $count = (int) DB::value(
            'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE album_id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        // 封面只挑能公开访问的图，加密图/未过审图当封面会让整张缩略图 404
        $cover = DB::fetch(
            'SELECT url FROM ' . DB::table('images') . '
             WHERE album_id = :id AND deleted_at IS NULL AND status = 1
               AND (password IS NULL OR password = "")
             ORDER BY id DESC LIMIT 1',
            ['id' => $id]
        );
        static::query()->where('id', $id)->update([
            'image_num' => $count,
            'cover'     => $cover['url'] ?? null,
        ]);
    }

    public static function generateKey(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 12);
    }

    public static function cast(array $row): array
    {
        $row['id']        = (int) $row['id'];
        $row['user_id']   = (int) $row['user_id'];
        $row['image_num'] = (int) $row['image_num'];
        $row['is_public'] = (int) $row['is_public'];
        return $row;
    }
}
