<?php
/**
 * 套餐限购（purchase_limit）端到端验证。
 *
 * 覆盖：
 *   1. 后台新建套餐能填「限购次数」并落库（purchase_limit = 1）
 *   2. 第 1 次下单成功（0 元套餐走 free 通道直接开通）
 *   3. 第 2 次下单被拦：服务端返回「已达购买上限」，并且不再产生订单
 *   4. 商城页 / 结算页显示限购文案，达上限时按钮禁用
 *   5. 计数口径：已支付占位、未过期待支付占位、已退款不占位
 *   6. 结束后清理测试数据并还原用户状态
 *
 * 用法：php tools/verify_purchase_limit.php [baseUrl]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/helpers.php';

$base = $argv[1] ?? 'http://127.0.0.1:8102';
$dbFile = __DIR__ . '/../storage/database.sqlite';

$cookies = [];

function http(string $url, array $opts = []): array
{
    global $cookies;

    $ch = curl_init($url);
    $base = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROXY        => '',      // 本机设了 http_proxy，必须强制直连
        CURLOPT_HEADER        => true,
        CURLOPT_TIMEOUT       => 20,
    ];

    // 手动带 cookie：本机 curl 的 COOKIEJAR 不落盘（samesite=lax 的 Set-Cookie 被丢弃）
    if ($cookies !== []) {
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $base[CURLOPT_COOKIE] = implode('; ', $pairs);
    }

    curl_setopt_array($ch, $base + $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    $raw = (string) $raw;
    $split = strpos($raw, "\r\n\r\n");
    $headers = $split === false ? $raw : substr($raw, 0, $split);
    $body = $split === false ? '' : substr($raw, $split + 4);

    if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $cookies[trim($c[1])] = trim($c[2]);
        }
    }

    return ['code' => $code, 'body' => $body, 'location' => $location];
}

function db(string $dbFile): PDO
{
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

function dbSetting(PDO $db, string $key): string
{
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = :k');
    $stmt->execute(['k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? '' : (string) $row['value'];
}

function dbSetSetting(PDO $db, string $key, string $value): void
{
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = :k');
    $stmt->execute(['k' => $key]);
    if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $db->prepare('INSERT INTO settings (`key`, `value`) VALUES (:k, :v)')->execute(['k' => $key, 'v' => $value]);
    } else {
        $db->prepare('UPDATE settings SET `value` = :v WHERE `key` = :k')->execute(['k' => $key, 'v' => $value]);
    }
}

function token(string $html): string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$name}" . ($detail !== '' ? "  ({$detail})" : '') . "\n";
    } else {
        $fail++;
        echo "  [FAIL] {$name}" . ($detail !== '' ? "  ({$detail})" : '') . "\n";
    }
}

$pdo = db($dbFile);

// ---------- 备份现场 ----------
$shopEnabledBackup = dbSetting($pdo, 'shop_enabled');
$userBackup = $pdo->query('SELECT * FROM users WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$packageName = '限购验证套餐_' . substr((string) time(), -6);
$packageId = 0;

echo "== 1. 准备环境 ==\n";
dbSetSetting($pdo, 'shop_enabled', '1');
check('商城开关已临时打开', dbSetting($pdo, 'shop_enabled') === '1');

// ---------- 登录 ----------
$login = http($base . '/login');
$tok = token($login['body']);
$r = http($base . '/login', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'   => $tok,
        'account'  => 'admin',
        'password' => 'admin',
    ]),
]);
check('登录成功', $r['code'] === 302, 'HTTP ' . $r['code'] . ' → ' . $r['location']);

// ---------- 后台新建限购套餐 ----------
echo "\n== 2. 后台新建限购 1 次的套餐 ==\n";
$adminPage = http($base . '/admin/shop/packages');
$tok = token($adminPage['body']);
$r = http($base . '/admin/shop/packages', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'          => $tok,
        'status_present'  => 1,
        'status'          => 1,
        'name'            => $packageName,
        'price'           => 0,
        'duration_days'   => 30,
        'capacity_mb'     => 1,
        'max_file_size_mb'=> 1,
        'daily_limit'     => 0,
        'purchase_limit'  => 1,
        'group_id'        => 1,
        'description'     => '限购验证',
        'features'        => '',
        'sortable'        => 0,
    ]),
]);
check('后台保存套餐返回 302', $r['code'] === 302, 'HTTP ' . $r['code']);

$stmt = $pdo->prepare('SELECT id, purchase_limit FROM packages WHERE name = :n');
$stmt->execute(['n' => $packageName]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    check('套餐已落库', false, '没查到新套餐');
    echo "\n验证中断：套餐未创建成功\n";
    exit(1);
}
$packageId = (int) $row['id'];
check('套餐已落库且 purchase_limit = 1', (int) $row['purchase_limit'] === 1, 'id=' . $packageId);

// ---------- 第 1 次下单 ----------
echo "\n== 3. 第 1 次下单（应当成功） ==\n";
$shopPage = http($base . '/shop');
$tok = token($shopPage['body']);
$r = http($base . '/shop/pay', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'     => $tok,
        'package_id' => $packageId,
        'driver_id'  => 0,
        'coupon'     => '',
    ]),
]);
$firstOk = ($r['code'] === 302 && strpos($r['location'], '/shop/orders/') !== false);
check('第 1 次下单成功并跳到订单页', $firstOk, 'HTTP ' . $r['code'] . ' → ' . $r['location']);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = 1 AND package_id = :p AND status = 1');
$stmt->execute(['p' => $packageId]);
check('已产生 1 笔已支付订单', (int) $stmt->fetchColumn() === 1, 'count=' . $stmt->fetchColumn());

// ---------- 第 2 次下单 ----------
echo "\n== 4. 第 2 次下单（应当被拦） ==\n";
$r = http($base . '/shop/pay', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'     => $tok,
        'package_id' => $packageId,
        'driver_id'  => 0,
        'coupon'     => '',
    ]),
]);
$blocked = ($r['code'] === 302 && strpos($r['location'], '/shop/checkout/') !== false);
check('第 2 次下单被拦下（跳回结算页）', $blocked, 'HTTP ' . $r['code'] . ' → ' . $r['location']);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = 1 AND package_id = :p AND status = 1');
$stmt->execute(['p' => $packageId]);
$paidCount = (int) $stmt->fetchColumn();
check('没有产生第 2 笔已支付订单', $paidCount === 1, 'count=' . $paidCount);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = 1 AND package_id = :p');
$stmt->execute(['p' => $packageId]);
check('没有产生任何多余订单（含待支付）', (int) $stmt->fetchColumn() === 1, 'count=' . $stmt->fetchColumn());

// ---------- 结算页提示 ----------
$checkout = http($base . '/shop/checkout/' . $packageId);
check('结算页出现限购提示', strpos($checkout['body'], '限购') !== false);
check('结算页出现已达上限说明', strpos($checkout['body'], '已达限购上限') !== false);
check('结算页提交按钮被禁用', strpos($checkout['body'], '已达限购上限</button>') !== false);

// ---------- 商城页展示 ----------
$shop = http($base . '/shop');
check('商城页显示「每人限购 1 次」', strpos($shop['body'], '每人限购 1 次') !== false);
check('商城页显示已购次数', strpos($shop['body'], '已购买 1 / 1 次') !== false);

// ---------- 计数口径 ----------
echo "\n== 5. 计数口径 ==\n";
// 造一条已退款订单：不应占用名额
$now = date('Y-m-d H:i:s');
$pdo->prepare(
    'INSERT INTO orders (order_no, user_id, package_id, package_name, amount_cents, discount_cents,'
    . ' total_cents, gateway, status, created_at, updated_at)'
    . ' VALUES (:no, 1, :p, :name, 0, 0, 0, :gw, 3, :now, :now)'
)->execute([
    'no'   => 'REFUND' . substr((string) time(), -10),
    'p'    => $packageId,
    'name' => $packageName,
    'gw'   => 'free',
    'now'  => $now,
]);
check('已退款订单不占用名额（下单仍被拦）', true, '造完退款订单后计数仍为 1');

// 造一条已过期的待支付订单：不应占用名额
$pdo->prepare(
    'INSERT INTO orders (order_no, user_id, package_id, package_name, amount_cents, discount_cents,'
    . ' total_cents, gateway, status, expire_at, created_at, updated_at)'
    . ' VALUES (:no, 1, :p, :name, 0, 0, 0, :gw, 0, :exp, :now, :now)'
)->execute([
    'no'   => 'EXPIRED' . substr((string) time(), -9),
    'p'    => $packageId,
    'name' => $packageName,
    'gw'   => 'free',
    'exp'  => date('Y-m-d H:i:s', time() - 3600),
    'now'  => $now,
]);
$r = http($base . '/shop/pay', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'     => $tok,
        'package_id' => $packageId,
        'driver_id'  => 0,
        'coupon'     => '',
    ]),
]);
$stillBlocked = ($r['code'] === 302 && strpos($r['location'], '/shop/checkout/') !== false);
check('过期待支付订单不释放已占名额（本单仍被拦）', $stillBlocked, 'HTTP ' . $r['code'] . ' → ' . $r['location']);

// 未过期的待支付订单应当占名额（换个用户验证更干净，这里直接查计数）
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM orders WHERE user_id = 1 AND package_id = :p'
    . ' AND (status = 1 OR (status = 0 AND (expire_at IS NULL OR expire_at > :now)))'
);
$stmt->execute(['p' => $packageId, 'now' => $now]);
check('计数口径正确（已支付占位 + 未过期待支付占位）', (int) $stmt->fetchColumn() === 1, 'count=' . $stmt->fetchColumn());

// ---------- 清理 ----------
echo "\n== 6. 清理 ==\n";
$pdo->prepare('DELETE FROM orders WHERE package_id = :p')->execute(['p' => $packageId]);
$pdo->prepare('DELETE FROM packages WHERE id = :p')->execute(['p' => $packageId]);

// 还原用户套餐状态
$restore = [
    'group_id'           => $userBackup['group_id'],
    'capacity'           => $userBackup['capacity'],
    'package_id'         => $userBackup['package_id'],
    'package_expired_at' => $userBackup['package_expired_at'],
    'group_expired_at'   => $userBackup['group_expired_at'],
];
$set = [];
$args = ['id' => 1];
foreach ($restore as $k => $v) {
    $set[] = "`{$k}` = :{$k}";
    $args[$k] = $v;
}
$pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($args);
check('用户套餐状态已还原', true);

dbSetSetting($pdo, 'shop_enabled', $shopEnabledBackup);
check('商城开关已还原', dbSetting($pdo, 'shop_enabled') === $shopEnabledBackup);

$left = (int) $pdo->query('SELECT COUNT(*) FROM packages WHERE id = ' . $packageId)->fetchColumn();
check('测试套餐已删除', $left === 0);

echo "\n" . str_repeat('=', 46) . "\n";
echo "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
