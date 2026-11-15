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
 * 优惠码。
 *
 *原先是裸 SQL 静态类，这里换成 Eloquent：
 * 简单的增删改查走查询构造器，跨表统计保留原生 SQL。
 * 对外契约不变：find/create 返回值是数组，列表返回行数组的数组。
 */
class Coupon extends BaseModel
{
    protected $table = 'coupons';

    use Concerns\ReturnsRows;

    public const TYPE_FIXED   = 'fixed';
    public const TYPE_PERCENT = 'percent';

    /**
     *原先叫 all()，但 Eloquent 父类的 all() 是静态方法且带 $columns 参数，
     * 签名对不上会直接 PHP 致命错误，所以改成 allRows()，调用方已同步改名。
     */
    public static function allRows(): array
    {
        $rows = self::rows(
            static::query()->orderByDesc('id')->get()
        );
        return array_map([self::class, 'cast'], $rows);
    }

    public static function find(int $id): ?array
    {
        $row = self::row(static::query()->find($id));
        return $row === null ? null : self::cast($row);
    }

    public static function findByCode(string $code): ?array
    {
        $row = self::row(
            static::query()->where('code', strtoupper(trim($code)))->first()
        );
        return $row === null ? null : self::cast($row);
    }

    public static function create(array $data): int
    {
        $data['code'] = strtoupper(trim((string) $data['code']));
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
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        }
        //既有的 Database::update() 传空数组时返回 0 且不发 SQL，
        // Eloquent 的 update([]) 会拼出非法 SQL，这里保持旧版行为
        if ($data === []) {
            return;
        }
        static::query()->where('id', $id)->update($data);
    }

    /**
     * 同 updateById()，改名原因一致。
     */
    public static function deleteById(int $id): void
    {
        static::query()->where('id', $id)->delete();
    }

    public static function discountFor(array $coupon, int $amountCents): int
    {
        if ($amountCents <= 0) {
            return 0;
        }
        if ($coupon['type'] === self::TYPE_PERCENT) {
            $off = (int) floor($amountCents * ((int) $coupon['value']) / 100);
            $max = (int) $coupon['max_discount_cents'];
            if ($max > 0 && $off > $max) {
                $off = $max;
            }
            return max(0, min($amountCents, $off));
        }
        return max(0, min($amountCents, (int) $coupon['value']));
    }

    /**
     * @return array{ok:bool,message:string,coupon:array|null,discount:int}
     */
    public static function validate(string $code, ?int $userId, int $packageId, int $amountCents): array
    {
        $fail = static fn (string $msg): array => ['ok' => false, 'message' => $msg, 'coupon' => null, 'discount' => 0];

        $coupon = self::findByCode($code);
        if ($coupon === null) {
            return $fail('优惠码不存在');
        }
        if ($coupon['status'] !== 1) {
            return $fail('优惠码已停用');
        }
        $now = time();
        if (!empty($coupon['starts_at']) && strtotime((string) $coupon['starts_at']) > $now) {
            return $fail('优惠码尚未生效');
        }
        if (!empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < $now) {
            return $fail('优惠码已过期');
        }
        if ((int) $coupon['quota'] > 0 && (int) $coupon['used'] >= (int) $coupon['quota']) {
            return $fail('优惠码已被领完');
        }
        if ($amountCents < (int) $coupon['min_amount_cents']) {
            return $fail('需满 ' . format_money((int) $coupon['min_amount_cents']) . ' 才能使用');
        }

        $scope = trim((string) ($coupon['scope'] ?? ''));
        if ($scope !== '' && $scope !== 'all') {
            $ids = array_filter(array_map('intval', preg_split('/[\s,]+/', $scope) ?: []));
            if ($ids !== [] && !in_array($packageId, $ids, true)) {
                return $fail('该优惠码不适用于此套餐');
            }
        }

        if ($userId !== null && $userId > 0) {
            // coupon_uses 是另一张表，没有对应的 Eloquent 模型，保留原生 SQL
            $used = (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('coupon_uses') . ' WHERE coupon_id = :c AND user_id = :u',
                ['c' => (int) $coupon['id'], 'u' => $userId]
            );
            if ($used >= max(1, (int) $coupon['per_user'])) {
                return $fail('你已使用过该优惠码');
            }
        }

        $discount = self::discountFor($coupon, $amountCents);
        if ($discount <= 0) {
            return $fail('该订单无法使用此优惠码');
        }

        return ['ok' => true, 'message' => '优惠码可用', 'coupon' => $coupon, 'discount' => $discount];
    }

    public static function consume(int $couponId, int $userId, int $orderId): void
    {
        // used = used + 1 换成 Eloquent 的原子自增，SQL 写法变了，效果一样
        static::query()->where('id', $couponId)->increment('used');
        try {
            // coupon_uses 没有 Eloquent 模型，保留原生 SQL
            DB::insert('coupon_uses', [
                'coupon_id'  => $couponId,
                'user_id'    => $userId,
                'order_id'   => $orderId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 手工类型转换。
     *
     * cast() 逐字保留原有逻辑：value_text 是从 value 派生的，
     * 后台优惠码列表直接用 $coupon['value_text']，Eloquent 的 $casts 表达不了这个规则。
     */
    public static function cast(array $row): array
    {
        $row['id']                = (int) $row['id'];
        $row['value']             = (int) $row['value'];
        $row['min_amount_cents']  = (int) $row['min_amount_cents'];
        $row['max_discount_cents'] = (int) $row['max_discount_cents'];
        $row['quota']             = (int) $row['quota'];
        $row['used']              = (int) $row['used'];
        $row['per_user']          = (int) $row['per_user'];
        $row['status']            = (int) $row['status'];
        $row['value_text'] = $row['type'] === self::TYPE_PERCENT
            ? $row['value'] . '%'
            : format_money($row['value']);
        return $row;
    }
}
