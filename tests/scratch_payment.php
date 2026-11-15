<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Drivers\Factory;
use App\Drivers\Registry;

$ok = 0; $fail = 0;
function check(string $name, bool $cond) {
    global $ok, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $cond ? $ok++ : $fail++;
}

// 1) Registry 是否包含新支付驱动
$all = Registry::all()['payment'];
check('registry has paypal', isset($all['paypal']));
check('registry has stripe', isset($all['stripe']));
check('registry has unionpay', isset($all['unionpay']));
check('registry has 6 payment total', count($all) === 6);

// 2) Factory 能实例化
$paypal = Factory::make(['type' => 'payment', 'provider' => 'paypal', 'config' => json_encode(['client_id' => 'x', 'client_secret' => 'y'])]);
check('factory paypal instance', $paypal instanceof \App\Drivers\Payment\Paypal);
$stripe = Factory::make(['type' => 'payment', 'provider' => 'stripe', 'config' => json_encode(['secret_key' => 'sk', 'webhook_secret' => 'whsec'])]);
check('factory stripe instance', $stripe instanceof \App\Drivers\Payment\Stripe);
$union = Factory::make(['type' => 'payment', 'provider' => 'unionpay', 'config' => json_encode([])]);
check('factory unionpay instance', $union instanceof \App\Drivers\Payment\Unionpay);

// 3) UnionPay 签名/验签 闭环
$priv = (string) file_get_contents(__DIR__ . '/_keys/upriv.pem');
$pub = (string) file_get_contents(__DIR__ . '/_keys/upub.pem');

$union2 = Factory::make(['type' => 'payment', 'provider' => 'unionpay', 'config' => json_encode([
    'mer_id' => '777290058110048',
    'cert_id' => '1234567890',
    'private_key' => $priv,
    'unionpay_pub' => $pub,
])]);

$params = [
    'version' => '5.1.0', 'encoding' => 'utf-8', 'certId' => '1234567890',
    'signMethod' => '01', 'txnType' => '01', 'txnSubType' => '01', 'bizType' => '000201',
    'channelType' => '07', 'accessType' => '0', 'merId' => '777290058110048',
    'orderId' => 'WONIU20261114001', 'txnTime' => '20261114120000', 'txnAmt' => '9900',
    'currencyCode' => '156', 'frontUrl' => 'http://x/back', 'backUrl' => 'http://x/notify',
    'respCode' => '00',
];
// 反射调用私有 sign
$ref = new ReflectionMethod($union2, 'sign');
$ref->setAccessible(true);
$params['signature'] = $ref->invoke($union2, $params);
check('unionpay sign non-empty', $params['signature'] !== '');

$info = $union2->verifyNotify($params);
check('unionpay verifyNotify returns mapping', $info !== null);
if ($info !== null) {
    check('unionpay out_trade_no', $info['out_trade_no'] === 'WONIU20261114001');
    check('unionpay amount_cents', $info['amount_cents'] === 9900);
}
// 篡改金额应验签失败
$bad = $params; $bad['txnAmt'] = '100';
check('unionpay tampered rejected', $union2->verifyNotify($bad) === null);

// 4) Stripe webhook 验签（与 factory 配置的 webhook_secret 一致：whsec）
$secret = 'whsec';
$t = time();
$body = json_encode([
    'type' => 'checkout.session.completed',
    'data' => ['object' => [
        'id' => 'cs_live_abc',
        'client_reference_id' => 'WONIU20261114002',
        'amount_total' => 1999,
        'metadata' => ['out_trade_no' => 'WONIU20261114002'],
    ]],
]);
$sig = hash_hmac('sha256', $t . '.' . $body, $secret, false);
$sinfo = $stripe->verifyNotify([], ['stripe-signature' => 't=' . $t . ',v1=' . $sig], $body);
check('stripe verifyNotify mapping', $sinfo !== null && $sinfo['out_trade_no'] === 'WONIU20261114002' && $sinfo['amount_cents'] === 1999);
// 错误签名应失败
check('stripe bad sig rejected', $stripe->verifyNotify([], ['stripe-signature' => 't=' . $t . ',v1=deadbeef'], $body) === null);

// 5) PayPal verifyNotify 缺少签名头应返回 null（不崩溃）
$pin = $paypal->verifyNotify([], []);
check('paypal verifyNotify no-header null', $pin === null);

echo PHP_EOL . "RESULT ok=$ok fail=$fail" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
