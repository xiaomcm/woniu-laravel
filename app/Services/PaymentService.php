<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;
use App\Core\Setting;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;

class PaymentService
{
    public static function quote(int $packageId, string $couponCode, ?int $userId): array
    {
        $package = Package::find($packageId);
        if ($package === null || $package['status'] !== 1) {
            return ['ok' => false, 'message' => '套餐不存在或已下架', 'package' => null, 'discount' => 0, 'total' => 0, 'coupon' => null];
        }

        // 限购校验：purchase_limit > 0 时，每个用户最多购买该套餐 N 次。
        // 计数口径见 Order::purchasedCount()（已支付永久占位，未过期待支付临时占位）。
        // 放在 quote() 里，createOrder() 内部会调用它，所以下单入口自动被拦住。
        $limit = (int) ($package['purchase_limit'] ?? 0);
        $bought = 0;
        if ($limit > 0 && $userId !== null && $userId > 0) {
            $bought = Order::purchasedCount((int) $userId, $packageId);
            if ($bought >= $limit) {
                return [
                    'ok'              => false,
                    'message'         => '该套餐每人限购 ' . $limit . ' 次，你已达到购买上限',
                    'package'         => $package,
                    'discount'        => 0,
                    'total'           => (int) $package['price_cents'],
                    'coupon'          => null,
                    'purchase_limit'  => $limit,
                    'purchased_count' => $bought,
                ];
            }
        }

        $amount = (int) $package['price_cents'];
        $discount = 0;
        $coupon = null;
        $note = '';

        if ($couponCode !== '') {
            $check = Coupon::validate($couponCode, $userId, $packageId, $amount);
            if ($check['ok']) {
                $discount = (int) $check['discount'];
                $coupon = $check['coupon'];
                $note = $check['message'];
            } else {
                return [
                    'ok'       => false,
                    'message'  => $check['message'],
                    'package'  => $package,
                    'discount' => 0,
                    'total'    => $amount,
                    'coupon'   => null,
                ];
            }
        }

        return [
            'ok'       => true,
            'message'  => $note,
            'package'  => $package,
            'discount' => $discount,
            'total'    => max(0, $amount - $discount),
            'coupon'   => $coupon,
            'amount'   => $amount,
            'purchase_limit'  => $limit,
            'purchased_count' => $bought,
        ];
    }

    /**
     * @return array{ok:bool,message:string,order:array|null,pay:array|null}
     */
    public static function createOrder(array $user, int $packageId, int $driverId, string $couponCode): array
    {
        $quote = self::quote($packageId, $couponCode, (int) $user['id']);
        if (!$quote['ok']) {
            return ['ok' => false, 'message' => $quote['message'], 'order' => null, 'pay' => null];
        }

        $package = $quote['package'];
        // 0 元订单（免费套餐或优惠码抵扣完）不需要支付通道，直接开通
        $free = (int) $quote['total'] <= 0;

        $driver = null;
        if (!$free) {
            $driver = DriveService::paymentByDriverId($driverId);
            if ($driver === null) {
                return ['ok' => false, 'message' => '支付通道不可用', 'order' => null, 'pay' => null];
            }
        }

        $driverRow = $free ? null : \App\Models\Driver::find($driverId);
        $expireMinutes = max(5, (int) Setting::get('order_expire_minutes', '30'));

        $orderId = Order::create([
            'user_id'        => (int) $user['id'],
            'package_id'     => (int) $package['id'],
            'package_name'   => (string) $package['name'],
            'amount_cents'   => (int) $quote['amount'],
            'discount_cents' => (int) $quote['discount'],
            'total_cents'    => (int) $quote['total'],
            'coupon_id'      => $quote['coupon'] === null ? null : (int) $quote['coupon']['id'],
            'driver_id'      => $free ? null : $driverId,
            'gateway'        => $free ? 'free' : (string) ($driverRow['provider'] ?? ''),
            'status'         => Order::STATUS_PENDING,
            'expire_at'      => date('Y-m-d H:i:s', time() + $expireMinutes * 60),
        ]);

        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'message' => '订单创建失败', 'order' => null, 'pay' => null];
        }

        if ((int) $order['total_cents'] <= 0) {
            self::settle($order, 'FREE' . $order['order_no']);
            return ['ok' => true, 'message' => '已免费开通', 'order' => Order::find($orderId), 'pay' => ['mode' => 'free']];
        }

        try {
            $pay = $driver->pay([
                'out_trade_no' => (string) $order['order_no'],
                'subject'      => (string) Setting::get('site_name', '蜗牛图床') . ' · ' . $order['package_name'],
                'amount_cents' => (int) $order['total_cents'],
                'notify_url'   => self::notifyUrl($driverId),
                'return_url'   => url('pay/return?order=' . $order['order_no']),
            ]);
        } catch (\Throwable $e) {
            Order::cancel($orderId);
            return ['ok' => false, 'message' => '拉起支付失败：' . $e->getMessage(), 'order' => null, 'pay' => null];
        }

        return ['ok' => true, 'message' => '订单已创建', 'order' => $order, 'pay' => $pay];
    }

    public static function notifyUrl(int $driverId): string
    {
        $driver = \App\Models\Driver::find($driverId);
        $config = $driver === null ? [] : \App\Models\Driver::config($driver);
        $custom = trim((string) ($config['notify_url'] ?? ''));
        return $custom !== '' ? $custom : url('pay/notify/' . $driverId);
    }

    /**
     * 处理异步回调
     * @return array{ok:bool,body:string}
     */
    public static function handleNotify(int $driverId, array $params, array $headers = [], string $body = ''): array
    {
        $driver = DriveService::paymentByDriverId($driverId);
        if ($driver === null) {
            return ['ok' => false, 'body' => 'fail'];
        }

        try {
            $info = $driver->verifyNotify($params, $headers, $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'body' => $driver->notifyResponse()];
        }

        if ($info === null) {
            return ['ok' => false, 'body' => 'fail'];
        }

        $order = Order::findByNo((string) $info['out_trade_no']);
        if ($order === null) {
            return ['ok' => false, 'body' => 'fail'];
        }
        if ((int) $info['amount_cents'] > 0 && (int) $info['amount_cents'] !== (int) $order['total_cents']) {
            return ['ok' => false, 'body' => 'fail'];
        }

        self::settle($order, (string) $info['trade_no']);

        return ['ok' => true, 'body' => $driver->notifyResponse()];
    }

    public static function settle(array $order, string $tradeNo): bool
    {
        $changed = Order::markPaid((int) $order['id'], $tradeNo);
        if (!$changed) {
            return false;
        }

        if (!empty($order['coupon_id'])) {
            Coupon::consume((int) $order['coupon_id'], (int) $order['user_id'], (int) $order['id']);
        }

        self::fulfill($order);

        $user = User::find((int) $order['user_id']);
        if ($user !== null) {
            $result = MailService::sendTemplate(
                (string) $user['email'],
                '订单已支付',
                '<p>订单 <b>' . e((string) $order['order_no']) . '</b> 已支付成功，套餐 <b>' . e((string) $order['package_name']) . '</b> 已生效。</p>'
                . '<p style="color:#6b7280;font-size:13px">如有疑问可通过工单联系我们。</p>'
            );
            if (!$result->pass) {
                \App\Core\Log::info('mail failed for order ' . $order['order_no'] . ': ' . $result->message, 'order');
            }
        }

        return true;
    }

    public static function fulfill(array $order): void
    {
        $package = Package::find((int) $order['package_id']);
        $user = User::find((int) $order['user_id']);
        if ($package === null || $user === null) {
            return;
        }
        // 同套餐续费要从原到期时间往后叠，这是买套餐特有的行为，传 true。
        self::grantPackage($user, $package, true);
    }

    /**
     * 把一个套餐的权益写进用户：用户组、容量、套餐 id、到期时间。
     * 买套餐付款成功（fulfill）和新用户注册默认绑定都走这里，保证两边权益一致。
     *
     * @param array $user               用户行
     * @param array $package            套餐行
     * @param bool  $renewFromExisting  同套餐是否从原到期时间叠加（续费用 true，注册首次绑定用 false）
     */
    public static function grantPackage(array $user, array $package, bool $renewFromExisting = false): void
    {
        $now = time();
        $days = (int) $package['duration_days'];

        if ($days <= 0) {
            // duration_days = 0 表示永久有效：到期时间写 NULL。
            // 过期回收的查询条件是 package_expired_at IS NOT NULL，
            // 所以 NULL 的用户天然不会被降级，永久套餐就是真的永久。
            $expire = null;
        } else {
            $currentExpire = $renewFromExisting && !empty($user['package_expired_at'])
                ? strtotime((string) $user['package_expired_at']) : 0;
            $samePackage = (int) ($user['package_id'] ?? 0) === (int) $package['id'];
            // 同套餐续费：从原到期时间往后叠；换套餐 / 注册首次绑定则从现在开始
            $start = ($samePackage && $currentExpire > $now) ? $currentExpire : $now;
            $expire = date('Y-m-d H:i:s', $start + $days * 86400);
        }

        DB::update('users', [
            'group_id'           => (int) $package['group_id'],
            'capacity'           => (int) $package['capacity'],
            'package_id'         => (int) $package['id'],
            'package_expired_at' => $expire,
            'group_expired_at'   => $expire,
            'updated_at'         => now(),
        ], 'id = :id', ['id' => (int) $user['id']]);
    }

    /**
     * 回收已过期的套餐。
     *
     * 只处理 package_expired_at 非空且已过期的用户；
     * duration_days = 0 的永久套餐到期时间是 NULL，不在回收范围内。
     */
    public static function expirePackages(): void
    {
        $rows = DB::fetchAll(
            'SELECT id, group_id FROM ' . DB::table('users') . '
             WHERE package_expired_at IS NOT NULL AND package_expired_at < :t',
            ['t' => now()]
        );
        if ($rows === []) {
            return;
        }
        $defaultGroup = (int) (Setting::get('default_group_id', '1') ?: 1);
        foreach ($rows as $row) {
            DB::update('users', [
                'group_id'           => $defaultGroup,
                'capacity'           => null,
                'package_id'         => null,
                'package_expired_at' => null,
                'group_expired_at'   => null,
                'updated_at'         => now(),
            ], 'id = :id', ['id' => (int) $row['id']]);
        }
    }
}
