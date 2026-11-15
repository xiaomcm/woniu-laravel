<?php
/**
 * 套餐有效天数语义验证：0 = 永久有效（不限制）。
 *
 * 直接跑业务代码，不依赖 HTTP：
 *   1. Package::cast() 派生字段
 *   2. PaymentService::fulfill() 履约后 users 表的到期时间
 *   3. 过期降级（Auth 里的 expirePackageIfNeeded 等价逻辑）
 *   4. expirePackages() 批量回收不会碰永久套餐
 *
 * 用法：php tools/verify_package_duration.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);

// 引导 Laravel 容器：Package / User 走的是 Eloquent，没有应用实例跑不起来
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Core\Database as DB;
use App\Core\Setting;
use App\Models\Package;
use App\Models\User;
use App\Services\PaymentService;

$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $name . ($detail !== '' ? '  → ' . $detail : '') . PHP_EOL;
}

/**
 * 取字段值并区分「不存在」与「值为 null」。
 *
 * 不能用 ?? ：值为 null 时 ?? 会返回默认值，null 与缺失就分不出来了。
 */
function field(array $row, string $key): array
{
    if (!array_key_exists($key, $row)) {
        return ['exists' => false, 'isNull' => false, 'text' => '<字段缺失>'];
    }
    $value = $row[$key];
    return [
        'exists' => true,
        'isNull' => $value === null,
        'text'   => var_export($value, true),
    ];
}

$root = dirname(__DIR__);
$dbFile = $root . '/storage/database.sqlite';

echo '== 1. Package::cast() 派生字段 ==' . PHP_EOL;
$row = Package::cast([
    'id' => 1, 'group_id' => 1, 'price_cents' => 0, 'duration_days' => 0,
    'capacity' => 1024, 'max_file_size' => 100, 'daily_limit' => 0,
    'status' => 1, 'sortable' => 0, 'features' => '[]',
]);
check('duration_days=0 → is_permanent=true', ($row['is_permanent'] ?? null) === true);
check('duration_days=0 → duration_text=永久', ($row['duration_text'] ?? '') === '永久', (string) ($row['duration_text'] ?? ''));

$row30 = Package::cast([
    'id' => 2, 'group_id' => 1, 'price_cents' => 9900, 'duration_days' => 30,
    'capacity' => 1024, 'max_file_size' => 100, 'daily_limit' => 0,
    'status' => 1, 'sortable' => 0, 'features' => '[]',
]);
check('duration_days=30 → is_permanent=false', ($row30['is_permanent'] ?? null) === false);
check('duration_days=30 → duration_text=30 天', ($row30['duration_text'] ?? '') === '30 天', (string) ($row30['duration_text'] ?? ''));

echo PHP_EOL . '== 2. 履约：0 天套餐到期时间写 NULL ==' . PHP_EOL;

// 造一个临时用户 + 临时套餐，跑真实 fulfill()
$tmpUserId = 0;
$tmpPkgId = 0;
try {
    $now = date('Y-m-d H:i:s');

    $userId = User::create([
        'name'     => 'pkgtest_' . substr(md5((string) microtime(true)), 0, 8),
        'email'    => 'pkgtest_' . substr(md5((string) microtime(true)), 0, 8) . '@test.local',
        'password' => 'x',
        'group_id' => 1,
    ]);

    $pkgId = Package::create([
        'name'          => '永久套餐测试',
        'group_id'      => 1,
        'price_cents'   => 0,
        'duration_days' => 0,
        'capacity'      => 1048576,
        'max_file_size' => 1048576,
        'daily_limit'   => 0,
        'description'   => '',
        'features'      => '[]',
        'status'        => 1,
        'sortable'      => 99,
    ]);

    $tmpUserId = $userId;
    $tmpPkgId = $pkgId;

    // 走真实履约链路
    PaymentService::fulfill(['package_id' => $pkgId, 'user_id' => $userId]);

    $after = User::find($userId);
    $f1 = field($after, 'package_expired_at');
    check('0 天套餐履约后 package_expired_at = NULL', $f1['exists'] && $f1['isNull'], $f1['text']);
    $f2 = field($after, 'group_expired_at');
    check('0 天套餐履约后 group_expired_at = NULL', $f2['exists'] && $f2['isNull'], $f2['text']);
    check('0 天套餐履约后 package_id 已写入', (int) ($after['package_id'] ?? 0) === $pkgId);

    // 再造一个 30 天套餐对比
    $pkg30Id = Package::create([
        'name'          => '30 天套餐测试',
        'group_id'      => 1,
        'price_cents'   => 0,
        'duration_days' => 30,
        'capacity'      => 1048576,
        'max_file_size' => 1048576,
        'daily_limit'   => 0,
        'description'   => '',
        'features'      => '[]',
        'status'        => 1,
        'sortable'      => 98,
    ]);
    PaymentService::fulfill(['package_id' => $pkg30Id, 'user_id' => $userId]);
    $after30 = User::find($userId);
    $exp30 = (string) ($after30['package_expired_at'] ?? '');
    check('30 天套餐履约后 package_expired_at 非空', $exp30 !== '', $exp30);

    $daysDiff = $exp30 !== ''
        ? (int) floor((strtotime($exp30) - time()) / 86400)
        : -1;
    check('30 天套餐到期日 ≈ 30 天后', $daysDiff >= 29 && $daysDiff <= 30, "实际 {$daysDiff} 天");

    echo PHP_EOL . '== 3. 过期回收不碰永久套餐 ==' . PHP_EOL;

    // 把临时用户的到期时间改成过去，模拟已过期
    DB::update('users', ['package_expired_at' => date('Y-m-d H:i:s', time() - 3600)], 'id = :id', ['id' => $userId]);
    PaymentService::expirePackages();

    $expired = User::find($userId);
    $e1 = field($expired, 'package_id');
    check('已过期套餐被回收：package_id 置 NULL', $e1['exists'] && $e1['isNull'], $e1['text']);
    $e2 = field($expired, 'package_expired_at');
    check('已过期套餐被回收：package_expired_at 置 NULL', $e2['exists'] && $e2['isNull'], $e2['text']);

    // 再履约一次 0 天套餐，然后跑回收，确认永久套餐不受影响
    PaymentService::fulfill(['package_id' => $pkgId, 'user_id' => $userId]);
    PaymentService::expirePackages();
    $permanent = User::find($userId);
    check('永久套餐不受回收影响：package_id 仍在', (int) ($permanent['package_id'] ?? 0) === $pkgId,
        var_export($permanent['package_id'] ?? null, true));
    $p1 = field($permanent, 'package_expired_at');
    check('永久套餐不受回收影响：到期时间仍为 NULL', $p1['exists'] && $p1['isNull'], $p1['text']);

    // 清理临时套餐
    DB::delete('packages', 'id = :id', ['id' => $pkgId]);
    DB::delete('packages', 'id = :id', ['id' => $pkg30Id]);
} finally {
    if ($tmpUserId > 0) {
        DB::delete('users', 'id = :id', ['id' => $tmpUserId]);
        echo PHP_EOL . '  临时数据已清理（用户 ' . $tmpUserId . '）';
    }
}

echo PHP_EOL;
if ($fail > 0) {
    echo 'FAILED: ' . $fail . ' 项不通过' . PHP_EOL;
    exit(1);
}
echo '全部通过' . PHP_EOL;
exit(0);
