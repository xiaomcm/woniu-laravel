<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

/**
 * 接口令牌。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：create() 返回明文令牌本身（不是 id），find/create 返回值语义与旧版一致。
 */
class Token extends BaseModel
{
    protected $table = 'tokens';

    use Concerns\ReturnsRows;

    public static function allForUser(int $userId): array
    {
        $rows = self::rows(
            static::query()->where('user_id', $userId)->orderByDesc('id')->get()
        );
        return array_map([self::class, 'cast'], $rows);
    }

    /**
     * 生成新令牌。
     *
     * 返回的是明文令牌（只给用户看一次），库里只存 sha256 摘要与前缀，
     * 所以这里的返回值不是 id —— 与既有的契约一致，调用方不能改。
     */
    public static function create(int $userId, string $name, ?string $expiresAt = null): string
    {
        $plain = 'wn_' . bin2hex(random_bytes(20));

        $model = new self();
        $model->forceFill([
            'user_id'      => $userId,
            'name'         => $name,
            'token_hash'   => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 12),
            'expires_at'   => $expiresAt,
            'created_at'   => now(),
        ]);
        $model->save();

        return $plain;
    }

    public static function findByPlain(string $plain): ?array
    {
        $row = self::row(
            static::query()->where('token_hash', hash('sha256', $plain))->first()
        );
        if ($row === null) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return self::cast($row);
    }

    /**
     * 记一次使用时间。
     *
     *原先叫 touch()，但 Eloquent 父类已经有实例方法 touch($attribute = null)，
     * 静态同名会直接报致命错误，所以改成 touchById()，调用方已同步改名。
     */
    public static function touchById(int $id): void
    {
        static::query()->where('id', $id)->update(['last_used_at' => now()]);
    }

    /**
     * 同 touchById()，改名原因一致。
     *
     * 带上 user_id 一起过滤，别人家的令牌删不掉。
     */
    public static function deleteById(int $id, int $userId): void
    {
        static::query()->where('id', $id)->where('user_id', $userId)->delete();
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：取出来的值类型必须和旧版完全一样。
     */
    public static function cast(array $row): array
    {
        $row['id']      = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        return $row;
    }
}
