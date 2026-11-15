<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Drivers\Factory;
use App\Drivers\Registry;
use App\Drivers\Sms\HuaweiCloudSms;
use App\Drivers\Sms\JdCloudSms;
use App\Drivers\Sms\SubmailSms;

$ok = 0; $fail = 0;
function check(string $name, bool $cond) {
    global $ok, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $cond ? $ok++ : $fail++;
}

$sms = Registry::all()['sms'];
foreach (['huawei_sms', 'jd_sms', 'ronglian_sms', 'twilio_sms', 'submail_sms'] as $k) {
    check('registry has ' . $k, isset($sms[$k]));
}
check('sms total >= 8', count($sms) >= 8);

foreach (['huawei_sms', 'jd_sms', 'ronglian_sms', 'twilio_sms', 'submail_sms'] as $k) {
    $d = Factory::make(['type' => 'sms', 'provider' => $k, 'config' => json_encode([])]);
    check('factory ' . $k, $d instanceof \App\Drivers\SmsDriver);
}

// 反射测试签名函数不报错
$hw = new HuaweiCloudSms(['access_key_id' => 'AK', 'access_key_secret' => 'SK', 'sender' => 'S', 'template_id' => 'T']);
$ref = new ReflectionMethod($hw, 'sign');
$ref->setAccessible(true);
$sig = $ref->invoke($hw, 'POST', '/sms/batchSendSms/v1', '{}', '20260101T000000Z', 'AK', 'SK');
check('huawei sign non-empty', $sig !== '');

$jd = new JdCloudSms(['access_key_id' => 'AK', 'access_key_secret' => 'SK']);
$ref2 = new ReflectionMethod($jd, 'sign');
$ref2->setAccessible(true);
$sig2 = $ref2->invoke($jd, 'POST', '/sms/sendBatchSMS', '{}', '20260101T000000Z', 'AK', 'SK', 'cn-north-1', 'sms');
check('jd sign non-empty', $sig2 !== '');

// Submail sign 必须是 32 位 md5
$sub = new SubmailSms(['appid' => 'A', 'appkey' => 'K']);
$ref3 = new ReflectionMethod($sub, 'sign');
$ref3->setAccessible(true);
$subSig = $ref3->invoke($sub, ['appid' => 'A', 'to' => '138', 'content' => 'x', 'sign_type' => 'md5'], 'K');
check('submail sign md5', preg_match('/^[a-f0-9]{32}$/', $subSig) === 1);

echo PHP_EOL . "RESULT ok=$ok fail=$fail" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
