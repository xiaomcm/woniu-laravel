<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Drivers\CloudDriver;
use App\Drivers\MailDriver;
use App\Drivers\PaymentDriver;
use App\Drivers\Result;
use App\Drivers\SecurityDriver;
use App\Drivers\SmsDriver;
use App\Drivers\SocialDriver;
use App\Models\Driver as DriverModel;

class DriveService
{
    private static array $cache = [];

    public static function instance(array $row): mixed
    {
        $key = 'd' . (int) ($row['id'] ?? 0) . '_' . md5((string) ($row['config'] ?? ''));
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = DriverModel::instance($row);
        }
        return self::$cache[$key];
    }

    public static function resolve(?int $groupId, int $storageId, string $type): mixed
    {
        $row = DriverModel::resolve($groupId, $storageId, $type);
        return $row === null ? null : self::instance($row);
    }

    public static function security(?int $groupId, int $storageId): ?SecurityDriver
    {
        $driver = self::resolve($groupId, $storageId, 'security');
        return $driver instanceof SecurityDriver ? $driver : null;
    }

    public static function cloud(?int $groupId, int $storageId): ?CloudDriver
    {
        $driver = self::resolve($groupId, $storageId, 'cloud');
        return $driver instanceof CloudDriver ? $driver : null;
    }

    public static function payment(?int $groupId, int $storageId): ?PaymentDriver
    {
        $driver = self::resolve($groupId, $storageId, 'payment');
        return $driver instanceof PaymentDriver ? $driver : null;
    }

    public static function paymentByDriverId(int $driverId): ?PaymentDriver
    {
        $row = DriverModel::find($driverId);
        if ($row === null || (int) $row['status'] !== 1) {
            return null;
        }
        $driver = self::instance($row);
        return $driver instanceof PaymentDriver ? $driver : null;
    }

    public static function payments(): array
    {
        $out = [];
        foreach (DriverModel::active('payment') as $row) {
            $driver = self::instance($row);
            if ($driver instanceof PaymentDriver) {
                $out[(int) $row['id']] = $driver;
            }
        }
        return $out;
    }

    public static function socials(?int $groupId = null): array
    {
        $rows = $groupId !== null && $groupId > 0
            ? DriverModel::pivotOf('group', $groupId, 'social')
            : DriverModel::active('social');
        $out = [];
        foreach ($rows as $row) {
            $driver = self::instance($row);
            if ($driver instanceof SocialDriver) {
                $out[(int) $row['id']] = $driver;
            }
        }
        return $out;
    }

    public static function socialById(int $id): ?SocialDriver
    {
        $row = DriverModel::find($id);
        if ($row === null) {
            return null;
        }
        $driver = self::instance($row);
        return $driver instanceof SocialDriver ? $driver : null;
    }

    public static function mail(?int $groupId = null): ?MailDriver
    {
        foreach (self::mailCandidates($groupId) as $row) {
            $driver = self::instance($row);
            if ($driver instanceof MailDriver) {
                return $driver;
            }
        }
        return null;
    }

    public static function mailCandidates(?int $groupId = null): array
    {
        if ($groupId !== null && $groupId > 0) {
            $rows = DriverModel::pivotOf('group', $groupId, 'mail');
            if ($rows !== []) {
                return $rows;
            }
        }
        return DriverModel::active('mail');
    }

    public static function sms(?int $groupId = null): ?SmsDriver
    {
        if ($groupId !== null && $groupId > 0) {
            foreach (DriverModel::pivotOf('group', $groupId, 'sms') as $row) {
                $driver = self::instance($row);
                if ($driver instanceof SmsDriver) {
                    return $driver;
                }
            }
        }
        foreach (DriverModel::active('sms') as $row) {
            $driver = self::instance($row);
            if ($driver instanceof SmsDriver) {
                return $driver;
            }
        }
        return null;
    }

    public static function audit(?int $groupId, int $storageId, array $image): Result
    {
        $driver = self::security($groupId, $storageId);
        if ($driver === null) {
            return Result::pass('未配置审核驱动');
        }
        try {
            return $driver->audit($image);
        } catch (\Throwable $e) {
            return Result::pass('审核执行异常，按策略放行：' . $e->getMessage());
        }
    }

    public static function cloudUrl(?int $groupId, int $storageId, string $url, array $ops): string
    {
        $driver = self::cloud($groupId, $storageId);
        if ($driver === null || $url === '') {
            return $url;
        }
        try {
            return $driver->buildUrl($url, $ops);
        } catch (\Throwable $e) {
            return $url;
        }
    }

    public static function test(array $row, array $extra = []): Result
    {
        $driver = self::instance($row);
        if ($driver === null) {
            return Result::fail('未知的驱动器类型');
        }

        try {
            if ($driver instanceof MailDriver) {
                $to = (string) ($extra['to'] ?? '');
                if ($to === '') {
                    return Result::fail('请填写测试收件邮箱');
                }
                return $driver->send($to, '蜗牛图床测试邮件', '<p>这是一封来自蜗牛图床的测试邮件，收到即表示 SMTP 配置可用。</p>');
            }
            if ($driver instanceof SmsDriver) {
                $phone = (string) ($extra['phone'] ?? '');
                if ($phone === '') {
                    return Result::fail('请填写测试手机号');
                }
                return $driver->send($phone, '123456');
            }
            if ($driver instanceof CloudDriver) {
                $url = 'https://cdn.example.com/demo/sample.jpg';
                return Result::pass('生成示例：' . $driver->buildUrl($url, ['width' => 320, 'quality' => 80]));
            }
            if ($driver instanceof SecurityDriver) {
                return $driver->audit([
                    'name'     => 'test.jpg',
                    'mimetype' => 'image/jpeg',
                    'url'      => (string) ($extra['url'] ?? 'https://cdn.example.com/demo/sample.jpg'),
                    'width'    => 800,
                    'height'   => 600,
                ]);
            }
            if ($driver instanceof SocialDriver) {
                return Result::pass('授权地址：' . $driver->authorizeUrl('test', (string) ($extra['redirect'] ?? url('oauth/callback'))));
            }
            if ($driver instanceof PaymentDriver) {
                return Result::pass('配置已加载，支付网关将在下单时校验');
            }
        } catch (\Throwable $e) {
            return Result::fail($e->getMessage());
        }

        return Result::pass('已加载');
    }
}
