<?php
/**
 * 站点数据播种。
 *
 * 蜗牛图床的基础数据（默认分组、本地存储、存储驱动、套餐、页面、通知）
 * 由 App\Core\Schema::install() 在建表时一并写入，与既有的行为一致，
 * 不需要额外的 seeder。这里只补一个测试用的管理员账号，供本地开发使用。
 *
 * 正式安装请走安装向导：php artisan woniu:install 或访问 /install
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name'     => 'admin',
            'email'    => 'admin@example.com',
            'password' => 'Test123456',
        ]);
    }
}
