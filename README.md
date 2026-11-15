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

## 宝塔面板安装

用宝塔的话照下面点一遍就行，不用手写 Nginx 配置。Windows 面板步骤一样，路径换成 `C:\...`。

1. **装 PHP 8.3**。软件商店 → 运行环境 → PHP 8.3（FPM 模式）。装完在「设置 → 安装扩展」里确认 `pdo_mysql` 或 `pdo_sqlite`、`gd`、`mbstring`、`fileinfo`、`curl`、`openssl` 都在，`zip` 和 `exif` 可选。再到「禁用函数」里把 `putenv`、`proc_open`、`pcntl_signal` 删掉，Composer 装依赖会用到，装完可以加回去。队列、Redis、Supervisor、ImageMagick 都不用装。

2. **新建站点**。网站 → 添加站点，域名填自己的，根目录保持默认，数据库选 MySQL 并记下库名、账号、密码（也可以不建，后面用 SQLite），PHP 版本选刚装的 8.3。

3. **上传代码**。文件管理上传 zip 解压，保证 `composer.json`、`app/`、`public/` 直接在站点根目录这一层，别多套一层文件夹。或者在面板终端里拉代码，以后 `git pull` 更新也方便：

```bash
cd /www/wwwroot/img.example.com
git clone https://github.com/xiaomcm/woniu-laravel.git .
```

结尾那个 `.` 表示克隆到当前目录，不加会多出一层子目录。

4. **装依赖、建目录**。继续在终端里跑：

```bash
cd /www/wwwroot/img.example.com
composer install --no-dev --optimize-autoloader
cp .env.example .env
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
```

别把本地 Windows 上的 `vendor/` 传上服务器，平台相关的二进制不通用。宝塔一般自带 Composer，没有就按官方文档装一个。这里同样不用跑 `php artisan key:generate`。

5. **设运行目录**。网站 → 点站点名 → 网站目录，运行目录选 `/public` 保存，然后把 **「防跨站攻击（open_basedir）」关掉**。开着的话 PHP 只能读 `public/`，读不到上一级的 `vendor/` 和 `storage/`，页面直接白屏 500，日志里是 `open_basedir restriction in effect`。关掉后由运行目录本身限定 Web 访问范围就行。

6. **配伪静态**。网站设置 → 伪静态，清空原有内容，粘贴后保存：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

不要给 `/uploads/`、`/thumbs/`、`/covers/` 加 `alias` 或静态直出规则，这三个路径要交给 PHP 做密码校验和审核状态判断。Apache 环境不用填，用 `public/.htaccess`，确认面板允许重写即可。

7. **放开上传体积**。网站设置 → 配置文件，在 `server { }` 里加一行 `client_max_body_size 34m;`；再到 PHP 8.3 的「配置修改」把 `upload_max_filesize` 和 `post_max_size` 改成 `32M`、`max_execution_time` 改成 `120`，重载 PHP。后台「图片处理 → 单张最大体积」别超过这个数，不然大图会 413 或被 PHP 截断。

8. **设权限**。站点目录设 `755`、所有者 `www`，`config/` 和 `storage/` 都要可写，安装向导要写 `config/installed.php`。别用 777。

```bash
chown -R www:www /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com
```

9. **配 SSL**。网站 → SSL → Let's Encrypt → 申请，签发后打开强制 HTTPS。装完进后台「系统设置 → 常规设置」把站点地址改成 `https://` 开头，否则图片链接还是 http，会被浏览器拦成混合内容。

10. **跑安装向导**。浏览器访问域名，会自动跳到 `/install`：数据库类型选 MySQL 或 SQLite，主机 `127.0.0.1`、端口 `3306`，库名账号密码填第 2 步记下的那组，表前缀留空，站点地址填带 `https://` 的域名，管理员账号密码自己设（密码至少 5 位）。点「开始安装」就会建表并写好 `config/installed.php` 和 `install.lock`。装完立刻登录后台改密码。

完整的字段说明、伪静态规则和常见报错（白屏 500、首页以外全 404、`Class "..." not found` 等）在 INSTALL.md 的 3.5。

## 关于

作者 QQ 34995549。这套代码可以自己部署、改成自己要的样子，也能拿去做商业用途；
转载或二次分发请保留作者署名、官网和源码地址，别把这几处换成自己的。

数据表字段说明在 DATA_SCHEMA.md，命令行部署、伪静态、目录权限和常见问题在 INSTALL.md。
