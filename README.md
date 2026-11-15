# 蜗牛图床 WONIU

一个用 Laravel 13 和 PHP 8.3 写的图床。上传图片、拿到外链，图片按日期分目录存，
本地和多种云存储都能接，相册、套餐扩容、后台管理这些也都有。适合想给自己站点配个图床的站长。

源码：<https://github.com/xiaomcm/woniu-laravel>
官网：<https://www.34995549.xyz/>
版权归作者所有（QQ 34995549），可以自用、修改和商用；转载请保留署名与官网地址。

## 能做什么

传上去的图会拿到一条直链和一张缩略图，可以建相册、给图片设访问密码、按分组控制谁能看。
存储除了本地，还能接 OSS、COS、S3、七牛云、又拍云这些。想靠套餐给用户扩容、
接在线支付也行。后台能配水印、缩略图尺寸、懒加载和灯箱预览，用户、图片审核、
工单这些日常管理都在后台点。

## 装之前先确认

PHP 8.3 以上，MySQL 或者 SQLite 都行，再带上 pdo、gd、mbstring、fileinfo、curl、
openssl 这几个常见扩展就够了。不用装队列、计划任务，也不用 ImageMagick。
具体缺哪个扩展，打开安装向导时会自己列出来。

## 快速安装

先把代码拉下来、装好依赖、把运行目录建出来：

```bash
git clone https://github.com/xiaomcm/woniu-laravel.git
cd woniu-laravel
composer install --no-dev --optimize-autoloader
cp .env.example .env
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
```

然后把网站根目录指到 `public/`，伪静态配成所有请求都交给 `public/index.php`。
浏览器打开站点地址，还没装过会自动跳到安装向导，填上数据库和管理员账号就能用了。

不用跑 `php artisan key:generate`，正式密钥由安装向导写进 `config/installed.php`。

装完记得进后台把管理员密码改掉，再确认「系统设置」里的站点地址填对了。
本地演示用的默认管理员是 `admin` / `admin`，正式安装时在安装向导里自己设一个。

用宝塔面板的话，直接翻 INSTALL.md 的 3.5，从装 PHP、建站点到配 SSL 一步一步
都写好了，照着点就行，不用手写 Nginx 配置。完整的部署步骤、伪静态规则、
目录权限、常见问题，还有从 GitHub 更新的办法，也都在 INSTALL.md 里。

## 代码大概怎么放

`app/` 是业务逻辑，`config/` 放配置，`resources/views/` 是页面模板，
`public/` 是唯一对外能访问的目录，`storage/` 存上传的图片、缩略图、日志和数据库。
仓库里不含 `vendor/`、`.env` 和 `storage/` 下的运行时数据，这些要么靠 composer 装，
要么由安装向导或程序运行的时候写出来，`git pull` 更新不会碰它们。

## 常用命令

```bash
php artisan serve         # 本地起个开发服务器
php artisan route:list    # 看路由
php artisan migrate       # 建表
php artisan config:clear  # 清配置缓存
php artisan view:clear    # 清视图缓存
php artisan test          # 跑测试
```

`php artisan test` 目前是 145 个用例、682 条断言。测试用的是单独的库，
不会碰到站点正在用的数据。

## 从 GitHub 更新

用 git 装的站，拉一下新代码就行：

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan view:clear
```

更新前先备份数据库和 `config/installed.php`。站点数据都在 `storage/` 和
`config/installed.php` 里，拉代码不会覆盖。具体看 INSTALL.md 第八节。

## 其它

数据表的字段说明放在 DATA_SCHEMA.md，改代码或者写迁移的时候可以翻一下。
