<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Setting;

class ShareService
{
    public static function hash(string $plain): string
    {
        return $plain === '' ? '' : password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function isLocked(array $row): bool
    {
        return trim((string) ($row['password'] ?? '')) !== '';
    }

    /**
     * 密码指纹：对库里的哈希再取摘要前 12 位，用来识别「密码已被换掉」
     * 只存 session，不落库，也就不会把哈希暴露出去
     */
    public static function fingerprint(array $row): string
    {
        $hash = (string) ($row['password'] ?? '');
        return $hash === '' ? '' : substr(hash('sha256', $hash), 0, 12);
    }

    public static function unlocked(string $type, int $id, array $row = []): bool
    {
        $key = $type . ':' . $id;
        $entry = Session::get('_share.' . $key);
        if ($entry === null) {
            return false;
        }

        // 新格式是 ['t' => 时间, 'h' => 指纹]，老格式是纯时间戳，两种都要能读
        if (is_array($entry)) {
            $stamp = (int) ($entry['t'] ?? 0);
            if ($row !== [] && (string) ($entry['h'] ?? '') !== self::fingerprint($row)) {
                Session::forget('_share.' . $key);
                return false;
            }
        } else {
            $stamp = (int) $entry;
        }

        $ttl = max(60, (int) Setting::get('share_password_ttl', '7200'));
        if ($stamp <= 0 || time() - $stamp >= $ttl) {
            Session::forget('_share.' . $key);
            return false;
        }

        // 滑动续期：有效期按「最后一次访问」算，连续浏览不会走到一半被踢回密码页
        // 每 60 秒最多写一次，避免列表页几十张图把 session 文件锁争满
        if (time() - $stamp > 60) {
            Session::put('_share.' . $key, [
                't' => time(),
                'h' => $row !== [] ? self::fingerprint($row) : (string) ($entry['h'] ?? ''),
            ]);
        }
        return true;
    }

    public static function unlock(string $type, int $id, array $row, string $password): bool
    {
        if (!self::isLocked($row)) {
            return true;
        }
        if (!password_verify($password, (string) $row['password'])) {
            return false;
        }
        Session::put('_share.' . $type . ':' . $id, [
            't' => time(),
            'h' => self::fingerprint($row),
        ]);
        return true;
    }

    public static function granted(string $type, int $id, array $row, bool $isOwnerOrAdmin): bool
    {
        if ($isOwnerOrAdmin) {
            return true;
        }
        if (!self::isLocked($row)) {
            return true;
        }
        return self::unlocked($type, $id, $row);
    }
}
