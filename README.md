# 蜗牛图床 WONIU —— Laravel 版

基于 **Laravel 13** 的图片托管系统。支持本地与多种云存储、套餐扩容、在线支付、
OAuth 登录、工单与内容审核，后台可配置图片处理、水印、懒加载与灯箱预览。

**源码仓库**：<https://github.com/xiaomcm/woniu-laravel>

```bash
# 一条命令装到本地
git clone https://github.com/xiaomcm/woniu-laravel.git
cd woniu-laravel
composer install --no-dev --optimize-autoloader
cp .env.example .env
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
php -S 127.0.0.1:8102 -t public server.php
# 浏览器打开 http://127.0.0.1:8102 → 自动进入安装向导
```

完整部署步骤（**宝塔面板图文流程**、Nginx/Apache 伪静态、目录权限、常见问题、
从 GitHub 更新）见 **[`INSTALL.md`](INSTALL.md)**。

---

## 一、环境要求

| 项目 | 要求 | 说明 |
|---|---|---|
| PHP | ≥ 8.3 | Laravel 13 的硬性要求 |
| 扩展 | pdo, pdo_mysql / pdo_sqlite, gd, mbstring, fileinfo, curl, openssl | 安装向导里会自动检查 |
| Composer | 2.x | 仅安装依赖时需要 |
| 数据库 | MySQL 5.7+ / MariaDB / SQLite | 两者都支持，SQLite 免配置 |

不需要队列、不需要计划任务、不需要 ImageMagick。

## 二、目录结构

```
app/
├── Auth/WoniuGuard.php      会话守卫（登录键名 user_id）
├── Core/                    核心支撑层（Database / Request / Response / View / Setting ...）
├── Drivers/                 驱动器（云存储、支付、短信、邮件、社交登录、内容安全）
├── Http/
│   ├── Controllers/         控制器（前台 + Admin）
│   └── Middleware/          中间件（安装检查、会话回收期、Cookie 加密白名单）
├── Models/                  Eloquent 模型（16 个业务模型）
├── Services/                业务服务（上传、缩略图、分享、支付 ...）
└── Storage/                 存储驱动（本地、OSS、COS、S3、七牛、又拍云、WebDAV、FTP）

config/
├── defaults.php             站点默认配置
├── installed.php            安装向导生成的实际配置
├── woniu.php                defaults + installed 合并后的全站配置
├── app.php / database.php / session.php ...  Laravel 自身配置，已接入 woniu 配置

resources/views/             Blade 模板（前台控制台 / 后台管理 / 落地页 / 灯箱）
public/assets/               前端静态资源（app.css/app.js、landing、lightbox）
routes/                      路由定义（前台 + 后台 + API）
database/migrations/         迁移文件（复用 app/Core/Schema 的表结构定义）
tests/                       契约测试与冒烟测试（Unit / Feature）
tools/                       运维与验证脚本（清理测试数据、像素级回归）
storage/                     运行时目录（数据库、上传文件、缩略图、日志、会话、缓存）
```

> 仓库里**不含** `vendor/`、`.env`、`config/installed.php`、`storage/` 下的运行时数据。
> 前两个用 `composer install` 装，后三个由安装向导生成或运行时产生 ——
> `.gitignore` 已经把它们全部排除在版本库之外，`git pull` 升级不会碰到你的站点数据。

## 三、安装

完整安装说明见 **[`INSTALL.md`](INSTALL.md)**，其中 **第三节 3.5 是宝塔面板完整图文流程**。

### 全新安装

1. 上传全部文件到站点目录，确保 `storage/` 与 `config/` 目录可写。
2. **把网站根目录指向 `public/`**，配好伪静态（全部请求交给 `public/index.php`）。
3. 浏览器访问站点根地址 → 自动跳转 `/install` 安装向导。
4. 填写数据库、管理员账号，点「开始安装」。
5. 安装向导会：
   - 建库建表（23 张业务表）
   - 创建默认分组、默认存储策略、默认设置项
   - 创建管理员账号
   - 写入 `config/installed.php` 与 `install.lock`（安装向导随即锁定）

> 环境要求：PHP **8.3+**，必需扩展 `pdo`、`pdo_mysql`/`pdo_sqlite`、`gd`、`mbstring`、
> `fileinfo`、`curl`、`openssl`、`ctype`、`filter`、`hash`、`session`、`tokenizer`；
> 可选 `zip`（打包下载）、`exif`（照片方向）。安装向导会自动检测这些项。

### 从 GitHub 拉源码部署

```bash
git clone https://github.com/xiaomcm/woniu-laravel.git img.example.com
cd img.example.com
composer install --no-dev --optimize-autoloader
cp .env.example .env
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
chown -R www:www . && chmod -R 755 . && chmod -R 775 storage config
```

然后配好伪静态（根目录指向 `public/`），浏览器访问站点根地址进入安装向导。
详见 INSTALL.md 第 5.1 节。

> 不用跑 `php artisan key:generate`：未安装时框架会自动补临时密钥，
> 正式密钥由安装向导写进 `config/installed.php`。

### 宝塔面板安装

见 **[INSTALL.md 第三节 3.5](INSTALL.md)**，包含：安装 PHP 8.3 与扩展、新建站点、
上传代码、设置运行目录 `/public`、填伪静态、放开上传体积、配 SSL、跑安装向导，
以及宝塔环境下常见的 8 个问题。

## 四、常用命令

```bash
php artisan serve            # 本地开发服务器
php artisan route:list       # 查看路由表
php artisan migrate          # 建表
php artisan config:clear     # 清配置缓存
php artisan view:clear       # 清视图缓存
php artisan test             # 跑测试（145 个用例 / 682 条断言）
php tools/clean_test_data.php --files --yes   # 清空测试数据（保留管理员与系统配置）
```

> `artisan migrate` 会建出 23 张业务表 + Laravel 自己的 `migrations` 记账表。

## 五、关键实现说明

### 5.1 数据层：同一个 PDO，两种访问方式

`App\Core\Database` 是业务代码（模型/服务/控制器）直接调用的适配层，
底层取的是 Laravel 连接自己的 PDO 句柄：

```php
public static function pdo(): \PDO
{
    return self::connection()->getPdo();
}
```

因此 **裸 SQL 与 Eloquent 查询跑在同一连接、同一个事务**，
SQLite 下也不会因为两个连接互相抢锁而报 `database is locked`。

### 5.2 会话

- 会话 Cookie 名 `woniu_session`
- 会话文件目录 `storage/sessions`
- 会话 Cookie **不加密**（`App\Http\Middleware\EncryptCookies` 白名单），
  保证会话 ID 以明文保存，站点迁移后已有会话仍可复用
- 登录用户 id 的会话键名是 `user_id`（`App\Auth\WoniuGuard`）

### 5.3 配置分层

`config/installed.php` 由安装向导写入，`bootstrap/woniu.php` 负责把它与
`config/defaults.php` 合并，`config/app.php`、`config/database.php`、
`config/session.php` 直接读合并结果。

站点迁移时把 `config/installed.php` 和 `storage/` 一起带过来即可，
数据库连接、应用密钥、站点地址都不用重新填。

### 5.4 接口

- 接口前缀 `/api/v1/*`（`bootstrap/app.php` 的 `apiPrefix`）
- 响应格式 `{"status":bool,"message":string,"data":...}`
- 同时支持 Bearer Token 与已登录会话两种鉴权

### 5.5 表结构契约

表结构定义集中在 `app/Core/Schema.php` + `app/Core/SchemaExtra.php`，
`database/migrations/2026_01_01_000000_create_woniu_tables.php` 直接复用它，
保证迁移文件与运行时建表用的是同一份定义。

字段级契约见根目录的 `DATA_SCHEMA.md`。

### 5.6 助手函数：语义与加载顺序解耦

`url()` / `asset()` / `redirect()` / `back()` / `csrf_field()` 有本系统自己的语义，
与 Laravel 自带的同名函数并不完全一致（例如 `csrf_field()` 不带
`autocomplete="off"`，`redirect()` 是「输出即中断」）。

这些语义**不能靠「谁先被定义」** 来保证 —— PHP 的 `function_exists` 守卫让先定义者生效，
而加载顺序取决于入口文件（`public/index.php` 排在 Composer 自动加载之前，
PHPUnit 则是 Composer 自动加载在前）。

所以语义真正落在这几处，与入口无关：

| 函数 | 实现位置 |
|---|---|
| `url()` | `App\Core\Url::to()`；Laravel 的全局 `url()` 最终也走 `App\Routing\WoniuUrlGenerator` |
| `asset()` | `App\Routing\WoniuUrlGenerator::asset()`（带文件 mtime 版本号） |
| `redirect()` / `back()` | `App\Core\Response::redirect()`（抛 `HttpResponseException` 中断请求） |
| `csrf_field()` | `App\Core\Csrf::field()`；Blade 编译期把 `csrf_field()` 换成它 |

`App\Core\Response` 的所有输出方法（`json` / `api` / `html` / `text` / `errorPage` /
`redirect` / `file`）返回类型都是 `never`，语义是「输出即中断」，
编译器会据此检查调用点，不会出现「以为拿到普通返回值、实际已经输出了响应」的情况。

### 5.7 灯箱预览

图片浏览支持灯箱（全屏预览）。

| 位置 | 交互 |
|---|---|
| 详情页主图 | 直接点图打开灯箱（灯箱工具栏里仍有「原图」按钮） |
| 详情页相关推荐 / 探索广场 / 用户主页 / 落地页展示墙 | 悬停图片右上角出现放大按钮，点它开灯箱 |
| 我的图库 / 相册详情 / 控制台首页 | 悬停卡片后操作条里出现放大按钮 |

灯箱内的操作：

- `←` `→` 在当前页面的图片之间切换（自动预热左右邻居）
- `Esc` / 点遮罩 / 点关闭按钮 退出
- 滚轮或 `+` `-` 缩放，双击在 1× / 2× 之间切换，放大后可拖拽平移
- 工具栏显示文件名、序号（`3 / 12`）、下载原图、新窗口打开原图

实现上是原生 JS，**不依赖任何第三方库**：

- `public/assets/lightbox.css` + `public/assets/lightbox.js`
- 入口识别靠 `data-lightbox-src`（图片地址）与 `data-lightbox-caption`（说明文字）两个属性，
  同页面里的入口自动组成一组
- 监听器直接绑在入口元素上，不用事件委托 —— 图片管理页的卡片本身带点击行为，
  委托的话按钮的点击会先冒到卡片上

后台「设置 → 图片处理 → 开启灯箱预览」可以随时关掉，
关掉后入口不输出、`lightbox.css/js` 也不会被加载。

## 六、验证体系

契约测试与像素级回归工具都能复跑：

| 工具 | 作用 | 命令 |
|---|---|---|
| `tests/Unit/SchemaContractTest.php` | 表结构契约：23 张表、列名、索引 | `php artisan test` |
| `tests/Unit/ModelContractTest.php` | 模型契约：`find()` 返回行数组、`create()` 返回 id | `php artisan test` |
| `tests/Unit/HelperSemanticsTest.php` | 助手函数语义与 `never` 返回类型 | `php artisan test` |
| `tests/Feature/RouteContractTest.php` | 路由契约：业务路由一条不多一条不少 | `php artisan test` |
| `tests/Feature/SmokeTest.php` | HTTP 冒烟：匿名页/登录/后台/API 都能正常渲染 | `php artisan test` |
| `tests/Feature/LightboxContractTest.php` | 灯箱契约：入口覆盖的页面、开关、资源 | `php artisan test` |
| `tests/Feature/LazyLoadContractTest.php` | 懒加载契约：图片懒加载、卡片间距规则 | `php artisan test` |
| `tests/Feature/BlockGapContractTest.php` | 块级间距契约：栈级块必须有 margin-bottom，禁止内联 margin-top 补丁 | `php artisan test` |
| `tests/Feature/OpButtonContractTest.php` | 图片操作按钮契约：尺寸统一、不被 flex 压扁 | `php artisan test` |
| `tests/Feature/PackagePurchaseLimitContractTest.php` | 套餐购买次数限制契约 | `php artisan test` |
| `tests/Feature/PackageDurationContractTest.php` | 套餐有效天数「0 = 不限制」契约 | `php artisan test` |
| `tests/Feature/SettingsLayoutContractTest.php` | 后台设置页布局契约 | `php artisan test` |
| `tests/Feature/HomepageIsolationContractTest.php` | 登录后仍可访问首页的隔离契约 | `php artisan test` |
| `tools/lightbox_test.cjs` | 灯箱交互实测（jsdom 驱动真实页面） | `node tools/lightbox_test.cjs` |
| `tools/card_gap_test.cjs` | 卡片像素间距实测（同列相邻卡片的真实间隙） | `node tools/card_gap_test.cjs <url>` |
| `tools/block_gap_test.cjs` | 块级间距审计（任意两块垂直间隙 ≥12px） | `node tools/block_gap_test.cjs <url>` |
| `tools/op_btn_test.cjs` | 图片操作按钮尺寸一致性实测 | `node tools/op_btn_test.cjs <url>` |
| `tools/clean_test_data.php` | 清空测试数据（保留管理员与系统配置） | `php tools/clean_test_data.php --files --yes` |

当前基线：**145 个用例 / 682 条断言 / 0 失败**。

浏览器回归工具（`tools/local_smoke.cjs`、`tools/shot.cjs`、`tools/settings_gap_test.cjs`、
`tools/footer_check.cjs` 等）需要站点先跑起来，用 `LB_ACCOUNT` / `LB_PASSWORD`
传入管理员账号。启动命令：

```bash
php -S 127.0.0.1:8102 -t public server.php
```

测试用的库是独立的（`storage/testing/database.sqlite`，见 `phpunit.xml` 的
`WONIU_DB_SQLITE`），不会碰到站点正在用的 `storage/database.sqlite`。

## 七、本地开发环境

```bash
php -S 127.0.0.1:8102 -t public server.php
```

访问 <http://127.0.0.1:8102>，首次会自动进入安装向导。

> 启动**必须带路由脚本 `server.php`**，否则带扩展名的路径
> （`/covers/*.jpg`、`/blank/*.jpg`、`/uploads/*`）会被当成静态文件而 404。

本地默认管理员账号 **`admin` / `admin`**（安装向导里可自行设置）。

## 八、目录权限

```
storage/            755（需可写）
storage/uploads/    图片原文件
storage/thumbs/     缩略图
storage/cache/      封面、占位图、处理缓存
storage/logs/       运行日志
storage/sessions/   会话文件
config/             755（安装向导需要写入 installed.php）
```
