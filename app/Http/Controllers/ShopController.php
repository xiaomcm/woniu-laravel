<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Driver as DriverModel;
use App\Models\Group;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\PaymentService;

class ShopController extends Controller
{
    public function index(): HttpResponse
    {
        if (!Setting::bool('shop_enabled', false)) {
            \App\Core\Response::featureDisabled('商城', '站主暂时关闭了商城，套餐购买与扩容暂不可用。');
        }

        $user = Auth::user();
        $packages = Package::allRows(true);

        $current = null;
        if ($user !== null && !empty($user['package_id'])) {
            $current = Package::find((int) $user['package_id']);
        }

        // 每个套餐的已购次数，用于「限购 N 次」的展示与按钮禁用
        $bought = [];
        if ($user !== null) {
            foreach ($packages as $pkg) {
                $bought[(int) $pkg['id']] = Order::purchasedCount((int) $user['id'], (int) $pkg['id']);
            }
        }

        // 套餐的单文件/每日张数不再读套餐自身（那两个字段填了不生效），
        // 改读套餐绑定用户组的配置 —— 这才是买后真正生效的值。
        $groupLimits = self::groupLimits();

        return $this->view('shop', [
            'pageTitle'   => '套餐与扩容',
            'packages'    => $packages,
            'user'        => $user,
            'current'     => $current,
            'expireAt'    => $user['package_expired_at'] ?? null,
            'bought'      => $bought,
            'groupLimits' => $groupLimits,
        ], 'layouts/app');
    }

    public function checkout(string $packageId): HttpResponse
    {
        $packageId = (int) $packageId;
        if (!Setting::bool('shop_enabled', false)) {
            \App\Core\Response::featureDisabled('商城', '站主暂时关闭了商城，套餐购买与扩容暂不可用。');
        }

        $user = Auth::requireLogin();
        $package = Package::find($packageId);
        if ($package === null || $package['status'] !== 1) {
            abort(404, '套餐不存在');
        }

        $drivers = [];
        $groupId = (int) $user['group_id'];
        $defaultStorage = \App\Models\Storage::defaultStorage();
        $storageId = $defaultStorage === null ? 1 : (int) $defaultStorage['id'];
        $bound = DriverModel::resolve($groupId, $storageId, 'payment');

        foreach (DriverModel::active('payment') as $row) {
            $drivers[] = $row;
        }

        // 限购：已达上限时页面直接提示，服务端下单接口也会再拦一次
        $limit = (int) ($package['purchase_limit'] ?? 0);
        $purchased = Order::purchasedCount((int) $user['id'], $packageId);
        $limitReached = $limit > 0 && $purchased >= $limit;

        return $this->view('checkout', [
            'pageTitle'    => '确认订单',
            'package'      => $package,
            'drivers'      => $drivers,
            'user'         => $user,
            'suggestId'    => $bound === null ? 0 : (int) $bound['id'],
            'free'         => (int) $package['price_cents'] === 0,
            'limit'        => $limit,
            'purchased'    => $purchased,
            'limitReached' => $limitReached,
            'groupLimits'  => self::groupLimits(),
        ], 'layouts/app');
    }

    /**
     * 用户组 id → 该组的完整权益（单文件/每日/单次数量/格式/权限）。
     * 套餐页/结算页展示「用户组权益」时读这里，跟上传校验读的是同一份数据，
     * 保证用户「看到的」和买后「实际生效的」一致。
     *
     * @return array<int, array{name:string, max_file_size:int, daily_limit:int, benefits:array<int,string>}>
     */
    private static function groupLimits(): array
    {
        $out = [];
        foreach (Group::allRows() as $group) {
            $out[(int) $group['id']] = [
                'name'          => (string) ($group['name'] ?? ''),
                'max_file_size' => (int) ($group['max_file_size'] ?? 0),
                'daily_limit'   => (int) ($group['daily_limit'] ?? 0),
                'benefits'      => Group::benefits($group),
            ];
        }
        return $out;
    }

    public function quote(): void
    {
        Auth::requireLogin();

        $packageId = Request::int('package_id', 0);
        $code = trim((string) Request::input('coupon', ''));
        $quote = PaymentService::quote($packageId, $code, Auth::id());

        $this->api($quote['ok'], $quote['message'], [
            'amount'   => (int) ($quote['amount'] ?? 0),
            'discount' => (int) $quote['discount'],
            'total'    => (int) $quote['total'],
            'total_text' => format_money((int) $quote['total']),
            'discount_text' => format_money((int) $quote['discount']),
        ]);
    }

    public function pay(): HttpResponse
    {
        $user = Auth::requireLogin();
        $this->validateCsrf();

        if (!Setting::bool('shop_enabled', false)) {
            \App\Core\Response::featureDisabled('商城', '站主暂时关闭了商城，套餐购买与扩容暂不可用。');
        }

        $packageId = Request::int('package_id', 0);
        $driverId = Request::int('driver_id', 0);
        $coupon = trim((string) Request::input('coupon', ''));

        $result = PaymentService::createOrder($user, $packageId, $driverId, $coupon);
        if (!$result['ok']) {
            flash('error', $result['message']);
            redirect('shop/checkout/' . $packageId);
        }

        $order = $result['order'];
        $pay = (array) $result['pay'];

        if (($pay['mode'] ?? '') === 'free') {
            flash('success', '套餐已开通');
            redirect('shop/orders/' . $order['order_no']);
        }

        if (($pay['mode'] ?? '') === 'redirect') {
            redirect((string) $pay['url']);
        }

        if (($pay['mode'] ?? '') === 'form') {
            return $this->view('pay_form', [
                'pageTitle' => '跳转支付',
                'order'     => $order,
                'pay'       => $pay,
            ], 'layouts/site');
        }

        return $this->view('pay_qr', [
            'pageTitle' => '扫码支付',
            'order'     => $order,
            'pay'       => $pay,
        ], 'layouts/site');
    }

    public function orders(): HttpResponse
    {
        $user = Auth::requireLogin();

        return $this->view('orders', [
            'pageTitle' => '我的订单',
            'orders'    => Order::recentForUser((int) $user['id'], 50),
        ], 'layouts/app');
    }

    public function orderShow(string $no): HttpResponse
    {
        $user = Auth::requireLogin();
        $order = Order::findByNo($no);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            abort(404, '订单不存在');
        }

        return $this->view('order_show', [
            'pageTitle' => '订单 ' . $order['order_no'],
            'order'     => $order,
        ], 'layouts/app');
    }

    public function status(string $no): void
    {
        $user = Auth::requireLogin();
        $order = Order::findByNo($no);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            $this->api(false, '订单不存在');
        }

        $this->api(true, '', [
            'status'      => (int) $order['status'],
            'status_text' => $order['status_text'],
            'paid'        => (int) $order['status'] === Order::STATUS_PAID,
        ]);
    }
}
