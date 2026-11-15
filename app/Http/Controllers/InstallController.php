<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Schema;
use App\Http\Middleware\EnsureInstalled;
use Illuminate\Http\Request;

/**
 * 安装向导。
 *
 * 逻辑与 原安装脚本 一致：环境检查 → 建库建表 → 写 config/installed.php → 锁定。
 * 产出的 config/installed.php 格式与既有格式完全相同，
 * 所以 已装好的站点把那个文件拷过来就能直接用。
 */
class InstallController extends Controller
{
    public function index(Request $request)
    {
        if (EnsureInstalled::installed()) {
            return response()->view('install_locked');
        }

        $errors = [];
        $success = false;

        $checks = [
            // Laravel 13 要求 PHP ≥ 8.3，安装向导按此检测
            'PHP 版本 ≥ 8.3'      => version_compare(PHP_VERSION, '8.3.0', '>='),
            'PDO 扩展'             => extension_loaded('pdo'),
            'PDO MySQL / SQLite'  => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
            'GD 图像处理'          => extension_loaded('gd'),
            'mbstring'             => extension_loaded('mbstring'),
            'fileinfo'             => extension_loaded('fileinfo'),
            'curl'                 => extension_loaded('curl'),
            'openssl'              => extension_loaded('openssl'),
            // Laravel 13 / Symfony 的硬依赖，缺一个都起不来
            'ctype / filter / hash / session / tokenizer' => extension_loaded('ctype')
                && extension_loaded('filter')
                && extension_loaded('hash')
                && extension_loaded('session')
                && extension_loaded('tokenizer'),
            'storage 目录可写'      => is_writable(BASE_PATH . '/storage') || @mkdir(BASE_PATH . '/storage', 0755, true),
            'config 目录可写'       => is_writable(BASE_PATH . '/config'),
        ];
        $checksPassed = !in_array(false, $checks, true);

        // zip 只是打包下载要用，缺了不影响安装，单独提示
        $tips = [];
        if (!extension_loaded('zip')) {
            $tips[] = '未检测到 zip 扩展：后台的「打包下载」不可用，其余功能不受影响';
        }
        if (!extension_loaded('exif')) {
            $tips[] = '未检测到 exif 扩展：竖拍照片的自动旋转会失效，不影响上传';
        }

        if ($request->isMethod('post')) {
            $driver     = ($request->input('driver', 'mysql') === 'sqlite') ? 'sqlite' : 'mysql';
            $host       = trim((string) $request->input('host', '127.0.0.1'));
            $port       = (int) $request->input('port', 3306);
            $database   = trim((string) $request->input('database', ''));
            $username   = trim((string) $request->input('username', ''));
            $password   = (string) $request->input('password', '');
            $prefix     = preg_replace('/[^a-z0-9_]/i', '', (string) $request->input('prefix', '')) ?? '';
            $siteUrl    = rtrim(trim((string) $request->input('site_url', '')), '/');
            $adminName  = trim((string) $request->input('admin_name', ''));
            $adminEmail = trim((string) $request->input('admin_email', ''));
            $adminPass  = (string) $request->input('admin_password', '');

            if ($adminName === '') {
                $errors[] = '管理员昵称不能为空';
            }
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = '管理员邮箱格式不正确';
            }
            if (strlen($adminPass) < 5) {
                $errors[] = '管理员密码至少 5 位';
            }
            if ($driver === 'mysql' && $database === '') {
                $errors[] = '数据库名不能为空';
            }

            if ($errors === []) {
                try {
                    $pdo = $this->connect($driver, $host, $port, $database, $username, $password);

                    Schema::install($pdo, $driver, $prefix);
                    Schema::createAdmin($pdo, $prefix, $adminName, $adminEmail, $adminPass);

                    foreach (['storage', 'storage/uploads', 'storage/logs', 'storage/cache', 'storage/tmp', 'storage/thumbs', 'storage/sessions'] as $dir) {
                        if (!is_dir(BASE_PATH . '/' . $dir)) {
                            @mkdir(BASE_PATH . '/' . $dir, 0755, true);
                        }
                    }

                    $config = [
                        'app' => [
                            'url'   => $siteUrl,
                            'key'   => bin2hex(random_bytes(16)),
                            'debug' => false,
                        ],
                        'db' => [
                            'driver'      => $driver,
                            'host'        => $host,
                            'port'        => $port,
                            'database'    => $database,
                            'username'    => $username,
                            'password'    => $password,
                            'charset'     => 'utf8mb4',
                            'prefix'      => $prefix,
                            'sqlite_path' => 'storage/database.sqlite',
                        ],
                    ];

                    $php = "<?php\n// 由安装向导生成，请勿手写覆盖（可手动修改数据库信息）\n\ndeclare(strict_types=1);\n\nreturn "
                        . var_export($config, true) . ";\n";
                    if (@file_put_contents(BASE_PATH . '/config/installed.php', $php) === false) {
                        $errors[] = '写入 config/installed.php 失败，请检查目录权限';
                    } else {
                        // 安装成功后自动写入锁定文件，阻止安装向导被重复执行
                        @file_put_contents(BASE_PATH . '/install.lock', 'installed at ' . date('Y-m-d H:i:s') . "\n");
                        $success = true;
                    }
                } catch (\Throwable $e) {
                    $errors[] = '安装失败：' . $e->getMessage();
                }
            }
        }

        return response()->view('install', [
            'errors'     => $errors,
            'success'    => $success,
            'checks'     => $checks,
            'checksPassed' => $checksPassed,
            'tips'       => $tips,
            'oldValues'  => $request->all(),
            'detectedUrl' => rtrim(url('/'), '/'),
            // 表单回填：视图里用 $old('字段名', '默认值') 取值
            'old'        => static fn (string $key, string $default = ''): string
                => htmlspecialchars((string) $request->input($key, $default), ENT_QUOTES),
        ]);
    }

    /**
     * 连接数据库。MySQL 目标库不存在时自动建库。
     */
    private function connect(string $driver, string $host, int $port, string $database, string $username, string $password): \PDO
    {
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        if ($driver === 'sqlite') {
            $path = BASE_PATH . '/storage/database.sqlite';
            return new \PDO('sqlite:' . $path, null, null, $options);
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        try {
            return new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
                $root = new \PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);
                $root->exec('CREATE DATABASE `' . str_replace('`', '', $database) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                return new \PDO($dsn, $username, $password, $options);
            }
            throw $e;
        }
    }
}
