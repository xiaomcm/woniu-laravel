<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function notify(string $driverId): void
    {
        $driverId = (int) $driverId;
        $params = array_merge($_GET, $_POST);
        if ($params === []) {
            $json = Request::json();
            if (is_array($json) && $json !== []) {
                $params = $json;
            }
        }

        // 收集网关回调头（PayPal / Stripe 等用签名头做验签）
        $headerNames = [
            'PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME',
            'STRIPE-SIGNATURE',
        ];
        $headers = [];
        foreach ($headerNames as $name) {
            $value = Request::header($name);
            if ($value !== null) {
                $headers[strtolower($name)] = $value;
            }
        }

        $body = Request::raw();
        $result = PaymentService::handleNotify($driverId, $params, $headers, $body);
        Response::text($result['body'], $result['ok'] ? 200 : 400);
    }

    public function back(): HttpResponse
    {
        $no = trim((string) Request::input('order', ''));
        if ($no === '') {
            $no = trim((string) Request::input('out_trade_no', ''));
        }

        $order = $no === '' ? null : Order::findByNo($no);

        return $this->view('pay_result', [
            'pageTitle' => '支付结果',
            'order'     => $order,
            'tradeNo'   => (string) Request::input('trade_no', ''),
        ], 'layouts/site');
    }
}
