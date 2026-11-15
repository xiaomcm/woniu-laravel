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

按下面的顺序做，一步做完再做下一步。跳步最容易出白屏和 404。不用手写 Nginx 配置文件。
Windows 面板步骤一样，路径换成 `C:\...`。下面用 `img.example.com` 举例子，换成自己的域名。

### 第 1 步：装 PHP 8.3

左侧 **软件商店 → 运行环境 → PHP**，装 **PHP 8.3**（FPM 模式）。装完点它的
**设置 → 安装扩展**，确认下面这些已经装上：

`pdo_mysql` 或 `pdo_sqlite`、`gd`、`mbstring`、`fileinfo`、`curl`、`openssl`。
`zip` 和 `exif` 是可选的，前者用于打包下载，后者用于手机照片自动转正。

队列、Redis、Supervisor、ImageMagick 都不用装，这个程序没有队列和计划任务。

### 第 2 步：放开 PHP 禁用函数

还是 PHP 8.3 的 **设置 → 禁用函数**，把 `putenv`、`proc_open`、`pcntl_signal`
从禁用列表里删掉。第 5 步装 Composer 依赖时会用到，装完可以再加回去。

### 第 3 步：新建站点

**网站 → 添加站点**，按这几项填：

| 字段 | 填法 |
|---|---|
| 域名 | `img.example.com`，先做好解析 |
| 根目录 | 保持默认 `/www/wwwroot/img.example.com` |
| 数据库 | 选 MySQL，填库名、账号、密码并记下来；也可以选「不创建」，后面用 SQLite |
| PHP 版本 | 选第 1 步装的 8.3 |

建好先别访问，接着往下做。

### 第 4 步：上传代码

两种方式选一个，把代码放到站点根目录：

文件管理里上传 zip 再解压，解压后 `composer.json`、`app/`、`public/` 必须直接
在根目录这一层，不能多套一层 `woniu-laravel/` 文件夹。

或者在左侧 **终端** 里拉代码，以后 `git pull` 更新也方便：

```bash
cd /www/wwwroot/img.example.com
git clone https://github.com/xiaomcm/woniu-laravel.git .
```

结尾那个 `.` 表示克隆到当前目录，不加会多出一层子目录。面板没装 Git 的话，
CentOS 用 `yum install -y git`，Debian/Ubuntu 用 `apt install -y git`。

### 第 5 步：装 Composer 依赖

继续在终端里，进到站点目录执行：

```bash
cd /www/wwwroot/img.example.com
composer install --no-dev --optimize-autoloader
```

宝塔一般自带 Composer，没有的话执行 `composer -V` 看提示，或按官方文档装一个。
**不要**把本地 Windows 上的 `vendor/` 传上服务器，平台相关的二进制不通用。

### 第 6 步：备 .env、建运行时目录

还是终端里，接着执行：

```bash
cp .env.example .env
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
```

`.env` 是框架层的兜底配置，站点真正的配置由第 12 步的安装向导写进
`config/installed.php`。这里不用跑 `php artisan key:generate`。

### 第 7 步：设运行目录，关掉防跨站

**网站 → 点站点名 → 网站目录**，两件事都在这一页：

1. **运行目录** 选 `/public`，保存
2. 把 **「防跨站攻击（open_basedir）」关掉**

第 2 条最容易漏。开着防跨站时 PHP 只能读 `public/`，读不到上一级的 `vendor/`
和 `storage/`，页面直接白屏 500，日志里是 `open_basedir restriction in effect`。
关掉后由运行目录本身限定 Web 访问范围就行。

### 第 8 步：配伪静态

**网站设置 → 伪静态**，清空原有内容，粘贴后保存：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

不要给 `/uploads/`、`/thumbs/`、`/covers/` 加 `alias` 或静态直出规则，
这三个路径要交给 PHP 做密码校验和审核状态判断。

Apache 环境不用填伪静态，`public/.htaccess` 已经带好了，确认面板允许
`.htaccess` 覆盖（「网站目录 → 允许重写」）即可。

### 第 9 步：放开上传体积

两处都要改，不然大图会 413 或者被 PHP 截断：

**网站设置 → 配置文件**，在 `server { }` 里加一行：

```nginx
client_max_body_size 34m;
```

**软件商店 → PHP 8.3 → 设置 → 配置修改**，把 `upload_max_filesize` 和
`post_max_size` 都改成 `32M`，`max_execution_time` 改成 `120`，保存后重载 PHP。

等装完进后台，「图片处理 → 单张最大体积」也要设，别超过这里的数值。

### 第 10 步：设目录权限

**文件管理**里选中站点目录 → **权限**，设 `755`、所有者 `www`。命令行等价写法：

```bash
chown -R www:www /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com
```

`config/` 必须可写（安装向导要写 `config/installed.php`），`storage/` 全程要写。
别用 777。

### 第 11 步：配 SSL

**网站 → SSL → Let's Encrypt → 申请**，签发后打开「强制 HTTPS」。

### 第 12 步：跑安装向导

浏览器访问 `https://img.example.com`，会自动跳到 `/install`。按顺序填：

| 字段 | 填法 |
|---|---|
| 数据库类型 | MySQL（第 3 步建的）或 SQLite（免配置，库文件落在 `storage/`） |
| 主机 / 端口 | `127.0.0.1` / `3306` |
| 库名 / 账号 / 密码 | 第 3 步记下的那组 |
| 表前缀 | 留空 |
| 站点地址 | `https://img.example.com` |
| 管理员账号 / 邮箱 / 密码 | 自己设，密码至少 5 位 |

点「开始安装」，向导会建表、写好 `config/installed.php` 和 `install.lock`。

### 第 13 步：装完先做三件事

1. 用管理员账号登录后台，把密码改掉
2. 进「系统设置 → 常规设置」，确认站点地址是 `https://` 开头，否则图片链接还是 http，
   会被浏览器拦成混合内容
3. 传一张图试试，确认直链和缩略图都能打开

### 出问题先看这几条

| 现象 | 回到哪一步 |
|---|---|
| 白屏 500，日志 `open_basedir restriction in effect` | 第 7 步的防跨站没关 |
| 打开域名是宝塔默认页或 404 | 第 7 步的运行目录没设成 `/public` |
| 首页能开，其它页全 404 | 第 8 步的伪静态没配或没保存，重贴后重载 Nginx |
| `Class "..." not found` | 第 5 步没在服务器上跑 Composer，或传了本地 `vendor/`，删掉重新装一次 |
| 大图上传报 413 | 第 9 步 Nginx 和 PHP 两处限制，改完记得重载 |

每一步的字段说明和更多排错情况在 INSTALL.md 的 3.5。

## 关于

作者 QQ 34995549。这套代码可以自己部署、改成自己要的样子，也能拿去做商业用途；
转载或二次分发请保留作者署名、官网和源码地址，别把这几处换成自己的。

数据表字段说明在 DATA_SCHEMA.md，命令行部署、伪静态、目录权限和常见问题在 INSTALL.md。
