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
use App\Core\Database as DB;
use App\Models\Image;
use App\Models\Log;
use App\Services\StatsService;

class DashboardController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();

        $series = StatsService::series(14);
        $max = max(1, max($series));

        $recentImages = Image::paginate([], 1, 8)['items'];
        $logs = Log::paginate(1, 10)['items'];

        $topUsers = DB::fetchAll(
            'SELECT u.id, u.name, COUNT(i.id) AS image_count, COALESCE(SUM(i.size),0) AS total_size
             FROM ' . DB::table('users') . ' u
             LEFT JOIN ' . DB::table('images') . ' i ON i.user_id = u.id AND i.deleted_at IS NULL
             GROUP BY u.id, u.name ORDER BY image_count DESC LIMIT 5'
        );

        return $this->view('admin/dashboard', [
            'pageTitle'    => '控制台',
            'overview'     => StatsService::overview(),
            'series'       => $series,
            'seriesMax'    => $max,
            'recentImages' => $recentImages,
            'logs'         => $logs,
            'topUsers'     => $topUsers,
        ], 'layouts/admin');
    }
}
