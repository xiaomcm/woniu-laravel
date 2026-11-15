<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 用户。
 *
 * 底层是 Laravel 的认证用户模型（可直接交给框架的会话守卫使用），
 * 对外仍然保留既有的静态方法契约：find/create 返回值是数组，
 * 调用方 $user['id'] 这种取法不用改。
 */
class User extends Authenticatable implements AuthenticatableContract
{
    protected $table = 'users';

    /**
     * 时间戳由业务层自己维护。
     */
    public $timestamps = false;

    /**
     *原先是裸 SQL，没有批量赋值保护。
     */
    protected $guarded = [];

    /**
     * 与 原有 cast() 完全对应。
     */
    protected $casts = [
        'id'       => 'integer',
        'group_id' => 'integer',
        'is_admin' => 'integer',
        'status'   => 'integer',
        'capacity' => 'integer',
    ];

    use Concerns\ReturnsRows;

    public static function find(?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        return self::row(static::query()->find($id));
    }

    /**
     * 取 Eloquent 模型本体（登录时交给会话守卫用）。
     */
    public static function model(?int $id): ?self
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        return static::query()->find($id);
    }

    public static function findByEmail(string $email): ?array
    {
        return self::row(
            static::query()->where('email', strtolower(trim($email)))->first()
        );
    }

    /**
     * 登录入口：邮箱或用户名都能查。邮箱优先（不区分大小写），
     * 查不到再按用户名精确匹配，用户名唯一所以不会一行多命。
     */
    public static function findForLogin(string $account): ?array
    {
        $account = trim($account);
        if ($account === '') {
            return null;
        }
        $user = self::findByEmail($account);
        if ($user !== null) {
            return $user;
        }
        return self::row(static::query()->where('name', $account)->first());
    }

    /**
     * 保留用户名：root / admin 这类管理含义的词不许注册。
     * 归一化（小写并去掉空格、下划线、连字符、点、@）后：
     * 与黑名单词相同一律拒绝；黑名单词达到 4 个字符时，开头或结尾带它也算「类似」，一并拒绝。
     * 3 字以内的短词（mod / api / www 等）只拒绝完全相同的写法，免得误伤 modern、apic 这类正常名字。
     */
    private const RESERVED_NAMES = [
        'admin', 'administrator', 'root', 'system', 'sysadmin', 'superuser', 'superadmin',
        'super', 'official', 'staff', 'support', 'moderator', 'webmaster', 'guest',
        'test', 'operator', 'service', 'security', 'master', 'manager', 'owner',
        'woniu', 'zhujitown', 'www', 'api', 'null', 'mod',
    ];

    public static function isReservedName(string $name): bool
    {
        $normalized = strtolower(str_replace([' ', '_', '-', '.', '@'], '', trim($name)));
        if ($normalized === '') {
            return true;
        }
        foreach (self::RESERVED_NAMES as $word) {
            if ($normalized === $word) {
                return true;
            }
            if (strlen($word) >= 4 && (str_starts_with($normalized, $word) || str_ends_with($normalized, $word))) {
                return true;
            }
        }
        return false;
    }

    public static function create(array $data): int
    {
        $data['email'] = strtolower(trim((string) $data['email']));
        $data['created_at'] = $data['created_at'] ?? now();
        $data = array_merge([
            'group_id' => 1,
            'capacity' => null,
            'is_admin' => 0,
            'status'   => 1,
            'avatar'   => null,
        ], $data);

        if (isset($data['password']) && !preg_match('/^\$2y\$|^\$argon2/', (string) $data['password'])) {
            $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

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
        if (isset($data['password']) && $data['password'] !== '' && !preg_match('/^\$2y\$|^\$argon2/', (string) $data['password'])) {
            $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim((string) $data['email']));
        }
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

    public static function paginate(string $keyword, int $page, int $perPage): array
    {
        $countQuery = static::query();
        if ($keyword !== '') {
            $countQuery->where(function ($builder) use ($keyword): void {
                $builder->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }
        $total = (int) $countQuery->count();

        $items = static::query()
            ->leftJoin('groups', 'users.group_id', '=', 'groups.id')
            ->select('users.*')
            ->addSelect('groups.name as group_name')
            ->when($keyword !== '', function ($builder) use ($keyword): void {
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('users.name', 'like', '%' . $keyword . '%')
                        ->orWhere('users.email', 'like', '%' . $keyword . '%');
                });
            })
            ->orderByDesc('users.id')
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($model): array {
                $row = $model->toArray();
                // 关联出来的分组名，摊平成 既有结构里的 group_name 字段
                $row['group_name'] = $model->group_name ?? null;
                return $row;
            })
            ->all();

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public static function countAll(): int
    {
        return (int) static::query()->count();
    }

    public static function countToday(): int
    {
        return (int) static::query()
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();
    }

    public static function usedBytes(int $userId): int
    {
        return (int) Image::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->sum('size');
    }

    public static function capacity(array $user): int
    {
        if ($user['capacity'] !== null) {
            return (int) $user['capacity'];
        }
        $group = Group::find((int) $user['group_id']);
        return $group === null ? 0 : (int) $group['capacity'];
    }

    public static function remaining(array $user): int
    {
        $capacity = self::capacity($user);
        if ($capacity === 0) {
            return PHP_INT_MAX;
        }
        return max(0, $capacity - self::usedBytes((int) $user['id']));
    }

    public static function group(array $user): ?array
    {
        return Group::find((int) $user['group_id']);
    }
}
