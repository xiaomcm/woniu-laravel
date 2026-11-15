<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 建表迁移。
 *
 * 表结构直接复用 app/Core/Schema 里的定义（DATA_SCHEMA.md 里的那份契约），
 * 保证迁移文件与安装向导建表用的是同一份结构，升级时数据可原地沿用。
 */

declare(strict_types=1);

use App\Core\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        Schema::install($connection->getPdo(), $connection->getDriverName(), (string) $connection->getTablePrefix());
    }

    public function down(): void
    {
        // 表结构由 既有 Schema 定义管理，回滚交给安装向导重装更安全
    }
};
