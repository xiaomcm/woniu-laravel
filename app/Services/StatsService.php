<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;
use App\Models\Image;
use App\Models\User;

class StatsService
{
    public static function overview(): array
    {
        return [
            'users'        => User::countAll(),
            'users_today'  => User::countToday(),
            'images'       => Image::countAll(),
            'images_today' => Image::countToday(),
            'used'         => Image::sumSize(),
            'albums'       => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('albums')),
            'pending'      => (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE status = 0 AND deleted_at IS NULL'
            ),
        ];
    }

    public static function series(int $days = 7, ?int $userId = null): array
    {
        return Image::dailySeries($days, $userId);
    }

    public static function userOverview(int $userId): array
    {
        $user = User::find($userId);
        if ($user === null) {
            return ['images' => 0, 'used' => 0, 'albums' => 0, 'capacity' => 0];
        }
        return [
            'images'   => (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :uid AND deleted_at IS NULL',
                ['uid' => $userId]
            ),
            'used'     => User::usedBytes($userId),
            'albums'   => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('albums') . ' WHERE user_id = :uid', ['uid' => $userId]),
            'capacity' => User::capacity($user),
        ];
    }
}
