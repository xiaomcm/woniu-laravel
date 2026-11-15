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
 * 订单。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent。
 * 对外契约不变：find() 返回行数组，create() 返回 id，列表返回行数组的数组。
 */
class Order extends BaseModel
{
    protected $table = 'orders';

    use Concerns\ReturnsRows;

    public const STATUS_PENDING  = 0;
    public const STATUS_PAID     = 1;
    public const STATUS_CANCELED = 2;
    public const STATUS_REFUNDED = 3;

    public static function generateNo(): string
    {
        return date('YmdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $data['order_no'] = $data['order_no'] ?? self::generateNo();

        $model = new self();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    public static function find(int $id): ?array
    {
        $row = self::row(static::query()->find($id));
        return $row === null ? null : self::cast($row);
    }

    public static function findByNo(string $no): ?array
    {
        $row = self::row(static::query()->where('order_no', $no)->first());
        return $row === null ? null : self::cast($row);
    }

    /**
     * 后台订单列表。
     *
     * 要 JOIN 用户表并按订单号 / 用户名 / 邮箱做关键字搜索，整段保留原生 SQL。
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'o.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $where[] = 'o.status = :st';
            $params['st'] = (int) $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(o.order_no LIKE :kw OR u.name LIKE :kw OR u.email LIKE :kw)';
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) DB::value(
            'SELECT COUNT(*) FROM ' . DB::table('orders') . ' o LEFT JOIN ' . DB::table('users') . ' u ON u.id = o.user_id WHERE ' . $whereSql,
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $rows = DB::fetchAll(
            'SELECT o.*, u.name AS user_name, u.email AS user_email
             FROM ' . DB::table('orders') . ' o
             LEFT JOIN ' . DB::table('users') . ' u ON u.id = o.user_id
             WHERE ' . $whereSql . ' ORDER BY o.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'     => array_map([self::class, 'cast'], $rows),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public static function markPaid(int $id, string $tradeNo = ''): bool
    {
        $order = self::find($id);
        if ($order === null || $order['status'] === self::STATUS_PAID) {
            return false;
        }
        static::query()->where('id', $id)->update([
            'status'     => self::STATUS_PAID,
            'trade_no'   => $tradeNo,
            'paid_at'    => now(),
            'updated_at' => now(),
        ]);
        return true;
    }

    public static function cancel(int $id): void
    {
        static::query()->where('id', $id)->update([
            'status'     => self::STATUS_CANCELED,
            'updated_at' => now(),
        ]);
    }

    public static function revenueCents(): int
    {
        return (int) static::query()->where('status', self::STATUS_PAID)->sum('total_cents');
    }

    public static function countPaid(): int
    {
        return (int) static::query()->where('status', self::STATUS_PAID)->count();
    }

    /**
     * 统计某用户对某套餐已占用的购买次数（限购校验用）。
     *
     * 口径：
     *   - 已支付订单永久占用名额；
     *   - 待支付订单在 expire_at 之前也占用名额，防止用户同时开多张单绕过限购；
     *     过期后自动释放，否则一张没付款的订单会永久占死名额；
     *   - 已取消 / 已退款不占用名额（退款后可以再买）。
     */
    public static function purchasedCount(int $userId, int $packageId): int
    {
        if ($userId <= 0 || $packageId <= 0) {
            return 0;
        }

        return (int) static::query()
            ->where('user_id', $userId)
            ->where('package_id', $packageId)
            ->where(function ($q) {
                $q->where('status', self::STATUS_PAID)
                    ->orWhere(function ($q2) {
                        $q2->where('status', self::STATUS_PENDING)
                            ->where(function ($q3) {
                                $q3->whereNull('expire_at')->orWhere('expire_at', '>', now());
                            });
                    });
            })
            ->count();
    }

    public static function recentForUser(int $userId, int $limit = 10): array
    {
        $rows = static::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit((int) $limit)
            ->get();

        return array_map([self::class, 'cast'], self::rows($rows));
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_PAID     => '已支付',
            self::STATUS_CANCELED => '已取消',
            self::STATUS_REFUNDED => '已退款',
            default               => '待支付',
        };
    }

    public static function statusTone(int $status): string
    {
        return match ($status) {
            self::STATUS_PAID     => 'success',
            self::STATUS_CANCELED => 'muted',
            self::STATUS_REFUNDED => 'warning',
            default               => 'primary',
        };
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：金额、状态这些字段取出来是什么类型，
     * 新版必须一模一样，连带算出来的 total_text / status_text / status_tone 也不变。
     */
    public static function cast(array $row): array
    {
        $row['id']             = (int) $row['id'];
        $row['user_id']        = (int) $row['user_id'];
        $row['package_id']     = (int) $row['package_id'];
        $row['amount_cents']   = (int) $row['amount_cents'];
        $row['discount_cents'] = (int) $row['discount_cents'];
        $row['total_cents']    = (int) $row['total_cents'];
        $row['coupon_id']      = $row['coupon_id'] === null ? null : (int) $row['coupon_id'];
        $row['driver_id']      = $row['driver_id'] === null ? null : (int) $row['driver_id'];
        $row['status']         = (int) $row['status'];
        $row['total_text']     = number_format($row['total_cents'] / 100, 2, '.', '');
        $row['status_text']    = self::statusLabel($row['status']);
        $row['status_tone']    = self::statusTone($row['status']);
        return $row;
    }
}
