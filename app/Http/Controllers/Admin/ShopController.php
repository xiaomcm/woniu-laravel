<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Response as HttpResponse;
use App\Http\Controllers\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Coupon;
use App\Models\Group;
use App\Models\Order;
use App\Models\Package;

class ShopController extends Controller
{
    public function packages(): HttpResponse
    {
        Auth::requireAdmin();

        return $this->view('admin/packages', [
            'pageTitle' => '套餐管理',
            'packages'  => Package::allRows(),
            'groups'    => Group::allRows(),
            'orderStat' => [
                'revenue' => Order::revenueCents(),
                'paid'    => Order::countPaid(),
            ],
        ], 'layouts/admin');
    }

    public function packageStore(): void
    {
        Auth::requireAdmin();
        $this->validateCsrf();

        $data = $this->packagePayload();
        if ($data['name'] === '') {
            flash('error', '套餐名称不能为空');
            back();
        }

        Package::create($data);
        flash('success', '套餐已创建');
        redirect('admin/shop/packages');
    }

    public function packageUpdate(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        if (Package::find($id) === null) {
            abort(404, '套餐不存在');
        }

        $data = $this->packagePayload();
        if ($data['name'] === '') {
            flash('error', '套餐名称不能为空');
            back();
        }

        Package::updateById($id, $data);
        flash('success', '套餐已保存');
        redirect('admin/shop/packages');
    }

    public function packageDelete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        Package::deleteById($id);
        flash('success', '套餐已删除');
        redirect('admin/shop/packages');
    }

    public function orders(): HttpResponse
    {
        Auth::requireAdmin();

        $page = max(1, Request::int('page', 1));
        $status = Request::input('status', '');
        $keyword = trim((string) Request::input('keyword', ''));

        $result = Order::paginate([
            'status'  => $status === '' ? null : (int) $status,
            'keyword' => $keyword,
        ], $page, 20);

        return $this->view('admin/orders', [
            'pageTitle' => '订单管理',
            'orders'    => $result['items'],
            'pager'     => $result,
            'status'    => $status,
            'keyword'   => $keyword,
            'revenue'   => Order::revenueCents(),
            'paidCount' => Order::countPaid(),
        ], 'layouts/admin');
    }

    public function orderRefund(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $order = Order::find($id);
        if ($order === null) {
            abort(404, '订单不存在');
        }
        if ($order['status'] !== Order::STATUS_PAID) {
            flash('error', '只有已支付的订单可以标记退款');
            back();
        }

        \App\Core\Database::update('orders', [
            'status'     => Order::STATUS_REFUNDED,
            'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);

        flash('success', '订单已标记为退款');
        redirect('admin/shop/orders');
    }

    public function coupons(): HttpResponse
    {
        Auth::requireAdmin();

        return $this->view('admin/coupons', [
            'pageTitle' => '优惠券',
            'coupons'   => Coupon::allRows(),
            'packages'  => Package::allRows(),
        ], 'layouts/admin');
    }

    public function couponStore(): void
    {
        Auth::requireAdmin();
        $this->validateCsrf();

        $data = $this->couponPayload();
        if ($data['code'] === '') {
            flash('error', '优惠码不能为空');
            back();
        }
        if (Coupon::findByCode($data['code']) !== null) {
            flash('error', '优惠码已存在');
            back();
        }

        Coupon::create($data);
        flash('success', '优惠券已创建');
        redirect('admin/shop/coupons');
    }

    public function couponUpdate(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $coupon = Coupon::find($id);
        if ($coupon === null) {
            abort(404, '优惠券不存在');
        }

        $data = $this->couponPayload();
        if ($data['code'] === '') {
            flash('error', '优惠码不能为空');
            back();
        }
        $dup = Coupon::findByCode($data['code']);
        if ($dup !== null && $dup['id'] !== $id) {
            flash('error', '优惠码已被占用');
            back();
        }

        Coupon::updateById($id, $data);
        flash('success', '优惠券已保存');
        redirect('admin/shop/coupons');
    }

    public function couponDelete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        Coupon::deleteById($id);
        flash('success', '优惠券已删除');
        redirect('admin/shop/coupons');
    }

    private function packagePayload(): array
    {
        $features = preg_split('/[\r\n]+/', (string) Request::input('features', '')) ?: [];
        $features = array_values(array_filter(array_map('trim', $features)));

        return [
            'name'          => mb_substr(trim((string) Request::input('name', '')), 0, 60),
            'group_id'      => max(1, Request::int('group_id', 1)),
            'price_cents'   => (int) round(((float) Request::input('price', '0')) * 100),
            // 有效天数 0 = 永久有效（不限制），与容量、单文件、每日张数的 0=不限 语义一致。
            // 负数没有意义，按 0 处理。
            'duration_days' => max(0, Request::int('duration_days', 30)),
            'capacity'      => (int) round(((float) Request::input('capacity_mb', '0')) * 1024 * 1024),
            'max_file_size' => (int) round(((float) Request::input('max_file_size_mb', '0')) * 1024 * 1024),
            'daily_limit'   => max(0, Request::int('daily_limit', 0)),
            // 每个用户可购买该套餐的次数上限，0 = 不限购（与「0 为不限」语义一致）
            'purchase_limit' => max(0, Request::int('purchase_limit', 0)),
            'description'   => mb_substr((string) Request::input('description', ''), 0, 480),
            'features'      => json_encode($features, JSON_UNESCAPED_UNICODE),
            'status'        => Request::bool('status_present') ? (Request::bool('status') ? 1 : 0) : 1,
            'sortable'      => Request::int('sortable', 0),
        ];
    }

    private function couponPayload(): array
    {
        $type = (string) Request::input('type', 'fixed');
        $valueRaw = (float) Request::input('value', '0');

        return [
            'code'              => strtoupper(trim((string) Request::input('code', ''))),
            'name'              => mb_substr((string) Request::input('name', '优惠券'), 0, 60),
            'type'              => $type === 'percent' ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED,
            'value'             => $type === 'percent' ? (int) round($valueRaw) : (int) round($valueRaw * 100),
            'min_amount_cents'  => (int) round(((float) Request::input('min_amount', '0')) * 100),
            'max_discount_cents' => (int) round(((float) Request::input('max_discount', '0')) * 100),
            'quota'             => max(0, Request::int('quota', 0)),
            'per_user'          => max(1, Request::int('per_user', 1)),
            'scope'             => trim((string) Request::input('scope', 'all')) ?: 'all',
            'starts_at'         => $this->dateOrNull((string) Request::input('starts_at', '')),
            'expires_at'        => $this->dateOrNull((string) Request::input('expires_at', '')),
            'status'            => Request::bool('status', true) ? 1 : 0,
        ];
    }

    private function dateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }
}
