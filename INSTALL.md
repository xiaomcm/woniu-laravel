# 蜗牛图床 WONIU — 安装说明

官网：<https://www.34995549.xyz/>
源码：<https://github.com/xiaomcm/woniu-laravel>

版权归作者所有（QQ 34995549）。这套代码可以自己部署、改成自己要的样子，也能拿去做商业用途；
转载或二次分发时，请保留作者署名、官网和源码地址，别把这几处换成自己的。

---

## 一、环境要求

### 1.1 PHP 版本

| 项 | 要求 |
|---|---|
| PHP | **8.3 或以上**（推荐 8.3.x；Laravel 13 官方支持到 8.5） |
| SAPI | FPM / CGI / CLI 都行，**不能用 CGI 模式**（FPM 最稳） |

> PHP 下限 **8.3** 是 Laravel 13 的硬性要求。
> 安装向导会自动检测，不满足时「开始安装」按钮是灰的。

### 1.2 必需的 PHP 扩展

| 扩展 | 干什么用 | 缺了会怎样 |
|---|---|---|
| `pdo` | 数据库抽象层 | 站点起不来 |
| `pdo_mysql` **或** `pdo_sqlite` | 数据库驱动 | 装不了 |
| `gd` | 缩略图、封面生成（**只用 GD，不需要 ImageMagick**） | 上传报错 |
| `mbstring` | 多字节字符串处理 | 站点起不来 |
| `fileinfo` | 识别上传文件的真实类型 | 识别不出图片类型 |
| `curl` | 云存储、支付、短信、邮件等远程驱动 | 远程驱动全部不可用 |
| `openssl` | 会话加密、`APP_KEY` | 站点起不来 |
| `ctype` `filter` `hash` `session` `tokenizer` | Laravel 13 / Symfony 的硬依赖 | 站点起不来 |
| `json` `pcre` `spl` `standard` | PHP 8 内置 | 默认就有，不用管 |

检查当前环境装了什么：

```bash
php -m
```

宝塔面板里在 **软件商店 → PHP 8.3 → 设置 → 安装扩展** 里勾选即可。

### 1.3 可选扩展

| 扩展 | 作用 | 缺了会怎样 |
|---|---|---|
| `zip` | 后台「打包下载」 | 打包下载按钮不可用，其余功能不受影响 |
| `exif` | 竖拍照片的自动旋转校正 | 手机竖拍的照片方向可能不正，不影响上传 |

> 这两项在安装向导的「环境检查」里会用黄色提示条标出来，**不影响安装**。

### 1.4 这些东西**不需要**装

| 常见要求 | 本程序情况 |
|---|---|
| 队列进程 / Supervisor | **不需要**。缩略图、封面都是请求内同步生成的 |
| 计划任务 / cron | **不需要** |
| ImageMagick 系统软件 / Imagick 扩展 | **不需要**。图片处理只用 PHP 的 GD |
| 改 `disable_functions` | **不需要**。代码里没有调用 `shell_exec` / `system` / `exec` 等外部命令 |

### 1.5 PHP 配置建议

`php.ini`（宝塔：PHP 设置 → 配置修改）：

```ini
upload_max_filesize = 32M
post_max_size = 34M          ; 必须大于 upload_max_filesize，否则大文件会被静默丢弃
memory_limit = 256M
max_execution_time = 60
max_file_uploads = 30
max_input_time = 60
date.timezone = Asia/Shanghai
```

Nginx 侧也要放开：

```nginx
client_max_body_size 34m;
```

> `post_max_size` 小于 `upload_max_filesize` 是最常见的「上传没反应」原因：
> PHP 会在读请求体之前就把请求丢掉，页面上什么错都不报。

---

## 二、数据库

| 类型 | 版本要求 | 需要的扩展 | 说明 |
|---|---|---|---|
| SQLite | 3.35.0+ | `sqlite3` + `pdo_sqlite` | **免配置**，不用建库建用户，个人 / 中小流量首选 |
| MySQL | 5.7+ | `pdo_mysql` | 需先在面板里建好库和用户 |
| MariaDB | 10.3+ | `pdo_mysql` | 同上 |

几点提醒：

- **表前缀**在安装向导里可填，不填就是默认（无前缀）。已有站点升级时**前缀必须保持一致**，否则读不到老数据。
- MySQL 建议用 `utf8mb4` 字符集。
- 选 SQLite 时，安装向导会自动在 `storage/database.sqlite` 建库；也可以填绝对路径指定别处。
- 数据量很大、批量上传频繁时建议 MySQL；SQLite 单机足够扛住普通图床流量。

---

## 三、Web 服务器与伪静态

### 3.1 站点根目录要指向 `public/`

```
/www/wwwroot/img.example.com/          ← 网站根目录
├── app/            ← 业务代码，不对外暴露
├── config/         ← 含数据库配置，绝不对外暴露
├── storage/        ← 上传的图片、缓存、日志
├── vendor/
└── public/         ← ← ← 面板里把「运行目录」指向这里
    ├── index.php
    ├── .htaccess
    └── assets/
```

把根目录指到项目根目录，`config/installed.php`（里面有数据库密码）就会暴露在公网上，所以指到 `public/`。

### 3.2 Nginx

```nginx
server {
    listen 80;
    server_name img.example.com;
    root /www/wwwroot/img.example.com/public;
    index index.php;

    charset utf-8;

    # 与 PHP 的 post_max_size 保持一致或更大
    client_max_body_size 34m;

    access_log /www/wwwlogs/img.example.com.log;
    error_log  /www/wwwlogs/img.example.com.error.log;

    # 伪静态：静态文件直接给，其余全部交给 index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;   # 按你实际的 PHP 版本改
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # 禁止访问隐藏文件
    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

### 3.3 Apache

`public/.htaccess` 已经内置了伪静态规则，只要：

1. 允许 `public/.htaccess` 生效（`AllowOverride All`）
2. 启用 `mod_rewrite`

```apache
<VirtualHost *:80>
    ServerName img.example.com
    DocumentRoot /www/wwwroot/img.example.com/public

    <Directory /www/wwwroot/img.example.com/public>
        AllowOverride All
        Options -Indexes +FollowSymLinks
        Require all granted
    </Directory>
</VirtualHost>
```

### 3.4 配伪静态时还要注意几点

**1. 别让 Nginx 直接输出图片文件。**

本程序的 `/uploads/*`、`/thumbs/*`、`/covers/*` 三个路径**必须进 PHP**：

- 分享密码校验（带密码的私密图）
- 图片下线 / 删除状态判断
- 审核状态判断
- 角色组的访问权限

很多图床把图片丢到 CDN，或者让 Nginx 直接读文件，这套程序不能这么干，**会让私密图泄露**。下面这种规则别加：

```nginx
# 别这么写
location ^~ /uploads/ { alias /www/wwwroot/img.example.com/storage/uploads/; }
```

**2. `storage/` 和 `config/` 别挪进 `public/`。**

它们本来就在 `public/` 之外，Nginx 不会暴露。挪进去等于把数据库密码和上传目录敞开。

**3. 用了反向代理（Nginx 在前、PHP 在后）**，要把真实 IP 传给后端：

```nginx
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header Host $host;
```

程序已经信任代理传来的 IP（`trustProxies(at: '*')`），后台的操作日志里记录的 IP 才是真实的访客 IP。

### 3.5 宝塔面板完整安装流程

按下面的顺序做，一步做完再做下一步。跳步最容易出白屏和 404。
照着宝塔 Linux 面板 9.x 的菜单点就行，不用手写 Nginx 配置文件。
Windows 面板步骤一样，路径换成 `C:\...`。下面用 `img.example.com` 举例子，换成自己的域名。

#### 第 1 步：装 PHP 8.3 和必需扩展

左侧 **软件商店 → 运行环境 → PHP**，安装 **PHP 8.3**（FPM 模式）。装完点它的
**设置 → 安装扩展**，确认下面这些已装（宝塔一般默认带前几个）：

| 扩展 | 用途 | 缺失后果 |
|---|---|---|
| `pdo_mysql` / `pdo_sqlite` | 数据库 | 安装向导直接报错 |
| `gd` | 缩略图、水印 | 上传后无法生成缩略图 |
| `mbstring` | 中文处理 | 页面乱码 / 500 |
| `fileinfo` | 判断真实文件类型 | 上传校验失效 |
| `curl` | 云存储、支付、OAuth | 对应驱动不可用 |
| `openssl` | 加密、HTTPS 请求 | 安装向导环境检查不通过 |
| `zip`（可选） | 打包下载 | 仅影响打包功能 |
| `exif`（可选） | 照片方向自动旋转 | 部分手机照片横过来 |

> 不需要装队列、Redis、Supervisor、ImageMagick。本程序没有队列与计划任务。

#### 第 2 步：放开 PHP 禁用函数

还是 PHP 8.3 的 **设置 → 禁用函数**，把 `putenv`、`proc_open`、`pcntl_signal`
从禁用列表里删掉。第 5 步装 Composer 依赖时会用到，装完可以再加回去。

#### 第 3 步：新建站点

**网站 → 添加站点**：

| 字段 | 填法 |
|---|---|
| 域名 | 你的域名，如 `img.example.com`（先做好解析） |
| 根目录 | 保持默认 `/www/wwwroot/img.example.com` |
| 数据库 | 选 **MySQL**，填库名/用户名/密码并记下来；也可选「不创建」改用 SQLite |
| PHP 版本 | 选刚装的 **PHP 8.3** |

建好后先别急着访问，继续下面几步。

#### 第 4 步：上传代码

两种方式任选：

- **文件管理**：把源码打包成 zip 上传 → 右键解压到站点根目录。
  注意解压后必须是 `composer.json`、`app/`、`public/` 直接在根目录这一层，
  不能多套一层 `woniu-laravel/` 文件夹。
- **终端拉取**（以后 `git pull` 更新方便）：左侧 **终端**，执行

```bash
cd /www/wwwroot/img.example.com
git clone https://github.com/xiaomcm/woniu-laravel.git .
```

> 结尾那个 `.` 表示克隆到当前目录，不加会多出一层子目录。
> 面板没装 Git 的话：`yum install -y git`（Debian/Ubuntu 用 `apt install -y git`）。

#### 第 5 步：装 Composer 依赖

继续在终端里：

```bash
cd /www/wwwroot/img.example.com
composer install --no-dev --optimize-autoloader
```

宝塔一般自带 `composer`，没有就执行 `composer -V` 看提示，或按官方文档装。
**不要**把本地 Windows 上的 `vendor/` 传上服务器 —— 平台相关的二进制不通用。

#### 第 6 步：备 .env、建运行时目录

继续在终端里，补一份框架层的兜底配置并建出运行时目录：

```bash
cp .env.example .env
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
```

> 不用跑 `php artisan key:generate`，理由见 5.1 第 3 步。

#### 第 7 步：设置运行目录

**网站 → 点站点名 → 网站目录**：

1. **运行目录** 选 `/public`，保存
2. 把 **「防跨站攻击（open_basedir）」关掉**

第 2 条容易被忽略：开着防跨站时 PHP 只能读 `public/` 目录，
读不到上一级的 `vendor/` 和 `storage/`，页面直接白屏 500，日志里是
`open_basedir restriction in effect`。关掉后由「运行目录」本身保证 Web 访问范围，
安全性不受影响。

#### 第 8 步：配伪静态

**网站设置 → 伪静态**，清空原有内容，粘贴：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

保存。**不要**给 `/uploads/`、`/thumbs/`、`/covers/` 加 `alias` 或静态直出规则 ——
这三个路径必须交给 PHP 做密码校验、删除/审核状态判断，见 3.4 第 1 点。

Apache 环境不用填伪静态，`public/.htaccess` 已经带好了，确认面板允许 `.htaccess`
覆盖（「网站目录 → 允许重写」）即可。

#### 第 9 步：放开上传体积

两处都要改，否则大图上传会 413 或被 PHP 截断：

- **网站设置 → 配置文件**：在 `server { }` 里加一行
  `client_max_body_size 34m;`
- **软件商店 → PHP 8.3 → 设置 → 配置修改**：把 `upload_max_filesize` 和
  `post_max_size` 都改成 `32M`，`max_execution_time` 改成 `120`，保存后重载 PHP

程序后台「图片处理 → 单张最大体积」也要同步设置，别超过这里的数值。

#### 第 10 步：设置目录权限

**文件管理**里选中站点目录 → **权限**，设 `755`、所有者 `www`。命令行等价写法：

```bash
chown -R www:www /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com/storage
chmod -R 755 /www/wwwroot/img.example.com/config
```

`config/` 必须可写（安装向导要写 `config/installed.php`），`storage/` 全程要写。
**别用 777。**

#### 第 11 步：配 SSL

**网站 → SSL → Let's Encrypt → 申请**，签发后打开「强制 HTTPS」。

#### 第 12 步：跑安装向导

浏览器访问 `https://img.example.com`，会自动跳到 `/install`：

| 字段 | 填法 |
|---|---|
| 数据库类型 | MySQL（第 3 步建的）或 SQLite（免配置，库文件落在 `storage/`） |
| 主机 / 端口 | `127.0.0.1` / `3306` |
| 库名 / 账号 / 密码 | 第 3 步记下的那组 |
| 表前缀 | 留空 |
| 站点地址 | `https://img.example.com` |
| 管理员账号 / 邮箱 / 密码 | 自行设置，密码至少 5 位 |

点「开始安装」，向导会建 23 张表、写好 `config/installed.php` 和 `install.lock`。

#### 第 13 步：装完先做三件事

1. 用管理员账号登录后台，把密码改掉
2. 进「系统设置 → 常规设置」，确认站点地址是 `https://` 开头，否则图片链接仍是 http，
   会被浏览器拦成混合内容
3. 传一张图试试，确认直链和缩略图都能打开

#### 宝塔环境常见问题

| 现象 | 原因 | 解决 |
|---|---|---|
| 白屏 500，日志 `open_basedir restriction in effect` | 第 7 步的「防跨站攻击」没关 | 网站目录 → 关闭防跨站攻击 |
| 访问域名显示宝塔默认页 / 404 | 运行目录没设成 `/public` | 第 7 步：网站目录 → 运行目录选 `/public` |
| 所有页面 404（首页除外） | 伪静态没配或没保存 | 第 8 步：重贴规则，保存后重载 Nginx |
| `Class "..." not found` | 没跑 `composer install` 或传了本地 `vendor/` | 第 5 步：删掉 `vendor/`，在服务器重新装一次 |
| 上传大图 413 Request Entity Too Large | 只改了 PHP 没改 Nginx | 第 9 步：Nginx 和 PHP 两处都要改 |
| 上传后缩略图空白 / 报 `GD` 相关错误 | PHP 缺 `gd` 或 `fileinfo` | 第 1 步：软件商店 → PHP 8.3 → 安装扩展 |
| 登录后立刻掉线、反复跳登录页 | `storage/sessions` 不可写或所有者不对 | 第 10 步：重设权限与所有者 `www` |
| 后台操作日志 IP 全是 `127.0.0.1` | 反代没传真实 IP | 按 3.4 第 3 点补 `X-Forwarded-For` |
| 想重装但 `/install` 已锁 | `install.lock` 存在 | 删 `install.lock` 与 `config/installed.php` 再访问（**先备份**） |

---

## 四、目录权限

用宝塔的话，给站点目录设 **755**，并确认这两个目录可写（755 即可，别用 777）：

| 目录 | 权限 | 谁写它 |
|---|---|---|
| `storage/` | 755（需可写） | 上传的图片、缓存、日志、会话 |
| `storage/uploads/` | 755（需可写） | 上传的原图 |
| `storage/thumbs/` | 755（需可写） | 缩略图 |
| `storage/cache/` | 755（需可写） | 封面、占位图 |
| `storage/logs/` | 755（需可写） | 运行日志 |
| `storage/sessions/` | 755（需可写） | 会话文件 |
| `storage/app/` | 755（需可写） | Laravel 内部缓存 |
| `storage/framework/` | 755（需可写） | 视图缓存（`views/`）、框架缓存（`cache/`）、框架会话（`sessions/`） |
| `storage/testing/` | 755（需可写） | 测试用库，与站点数据无关 |
| `config/` | 755（需可写） | 安装向导要往里写 `installed.php` |

命令行部署时：

```bash
chown -R www:www /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com/storage
chmod -R 755 /www/wwwroot/img.example.com/config
```

安装完成后 `config/installed.php` 会生成，里面是数据库密码 —— **这个文件不能被 Web 访问到**（指对 `public/` 就不会）。

---

## 五、安装步骤

两种部署方式，按你的情况选：

| 方式 | 适合谁 | 小节 |
|---|---|---|
| **A. 从 GitHub 拉源码** | 想随时 `git pull` 更新、或者需要自己改代码的人 | [5.1](#51-从-github-拉取源码部署) |
| **B. 上传整包** | 只想快点装起来、不碰命令行的人 | [5.2](#52-上传整包部署) |

> 两种方式**最终都要走一遍安装向导**（第 4 步往后完全一样）。

---

### 5.1 从 GitHub 拉取源码部署

仓库地址：**<https://github.com/xiaomcm/woniu-laravel>**

#### 第 1 步：拉代码

```bash
cd /www/wwwroot
git clone https://github.com/xiaomcm/woniu-laravel.git img.example.com
cd img.example.com
```

> 没有 `git` 命令？宝塔面板 → 软件商店 → 安装「Git」；或直接下载仓库的
> **Code → Download ZIP** 压缩包，解压到站点目录即可（此时跳到 5.2）。

#### 第 2 步：装依赖

仓库里**不含** `vendor/`（几百 MB，且各平台有差异），需要 Composer 装一次：

```bash
composer install --no-dev --optimize-autoloader
```

- Composer 2.x 即可：`php -r "copy('https://getcomposer.org/installer','composer-setup.php'); php composer-setup.php; rm composer-setup.php;"`
- 装不上 Composer 的，用宝塔面板 → 软件商店 → 安装「Composer」，或从
  <https://getcomposer.org/download/> 下 `composer.phar` 后 `php composer.phar install --no-dev`。

> `--no-dev` 会跳过 PHPUnit 等开发依赖，线上体积小一半。
> 如果要在服务器上跑测试，去掉这个参数。

#### 第 3 步：准备 .env

```bash
cp .env.example .env
```

`.env` 是**框架层**的兜底配置，站点真正的配置由安装向导写进 `config/installed.php`。

> **不用执行 `php artisan key:generate`。**
> 未安装时 `config/installed.php` 还不存在，`AppServiceProvider` 会自动补一个临时密钥，
> 保证安装向导能跑起来；安装完成后正式密钥以 `config/installed.php` 里的 `app.key` 为准
> （会话 Cookie 不加密，已有会话仍可正常解开）。
>
> 如果确实想往 `.env` 里写一个自己的密钥，用 `php artisan key:generate --force`
> —— 不加 `--force` 时该命令会先问一句「Are you sure?」，在非交互环境（CI、脚本）里会**直接挂住**。

#### 第 4 步：建目录 + 设权限

```bash
mkdir -p storage/uploads storage/thumbs storage/cache storage/logs storage/sessions storage/tmp
chown -R www:www /www/wwwroot/img.example.com
chmod -R 755 /www/wwwroot/img.example.com
chmod -R 775 /www/wwwroot/img.example.com/storage
chmod -R 775 /www/wwwroot/img.example.com/config
```

> `config/` 必须可写 —— 安装向导要往里写 `installed.php`。

#### 第 5 步：接着往下走

**从「配置伪静态」那一步继续**（见 [5.2](#52-上传整包部署) 的第 3 步往后）。

也就是说，A 方式只比 B 方式多了「拉代码 + 装依赖 + 备 .env」三步，
**安装向导的流程完全一样**。

> 全新部署会建出 23 张业务表和默认管理员账号，配置写进 `config/installed.php`，
> 装完用管理员账号登录，控制台、后台、用户中心都能打开。

---

### 5.2 上传整包部署

#### 第 1 步：上传代码

把整个项目传到站点目录（不要只传 `public/`）。

### 第 2 步：设置权限

按上一节把 `storage/` 和 `config/` 设成可写。

### 第 3 步：配置伪静态

按 3.2 / 3.3 / 3.5 配好，确认站点根目录指向 `public/`。

### 第 4 步：打开安装向导

浏览器访问站点根地址，未安装会自动跳到 `/install`。

向导页顶部是**环境检查**，11 项全绿才能点「开始安装」：

```
PHP 版本 ≥ 8.3                             通过
PDO 扩展                                   通过
PDO MySQL / SQLite                         通过
GD 图像处理                                通过
mbstring                                   通过
fileinfo                                   通过
curl                                       通过
openssl                                    通过
ctype / filter / hash / session / tokenizer 通过
storage 目录可写                            通过
config 目录可写                             通过
```

### 第 5 步：填表

| 字段 | 说明 |
|---|---|
| 驱动 | MySQL / MariaDB 或 SQLite |
| 主机、端口、库名、用户名、密码 | MySQL 填这几项；选 SQLite 时留空即可 |
| 表前缀 | 已有站点升级时必须和原来一致 |
| 站点地址 | **不带结尾斜杠**，例如 `https://img.example.com`。这个值会写进图片链接，换地址后要按第六节「换站点地址」里的两个脚本处理存量数据 |
| 管理员昵称 | 登录用户名 |
| 管理员邮箱 | 登录也可以用邮箱 |
| 管理员密码 | **至少 5 位** |

> MySQL 目标库不存在时，安装向导会尝试自动创建（需要账号有建库权限）。
> 没权限的话先在面板里手工建好库再回来填。

### 第 6 步：安装完成

向导会做这些事：

- 建 23 张业务表
- 建默认分组、默认本地存储、默认设置项
- 建管理员账号
- 写 `config/installed.php`
- 写 `install.lock`（**安装向导随即锁定，不能重复安装**）

装完站点即可用。想重装，删掉 `install.lock` 和 `config/installed.php` 再访问 `/install`。

---

## 六、安装完成后

1. **用管理员账号登录后台**，先改掉管理员密码。
2. 进「系统设置 → 常规设置」，确认**站点地址**和安装时填的一致。
3. 进「存储管理」，确认默认的「本地存储」配置正常（存储根目录是 `storage/uploads`）。
4. 上传一张图试试，缩略图、详情页、外链都点一遍。

### 换站点地址（域名 / 端口变了）

图片链接在入库时写的是**绝对地址**（`http://站点/uploads/...`）。换了站点地址之后，**历史图片的链接会指向旧地址**，表现为图库里图片打不开。

两个工具脚本专门处理这个：

```bash
# 1) 把库里指向旧地址的绝对 URL 换成当前站点地址
php tools/fix_image_urls.php --all https://img.example.com

# 2) 修历史遗留的缩略图命名（早期版本的缩略图文件名不是 sha1，取图库时找不到记录会 404）
php tools/fix_thumbnails.php
```

> 换地址后**新上传**的图片不受影响，只有存量数据需要跑这两个脚本。

---

## 七、常见问题

| 现象 | 原因 | 解决 |
|---|---|---|
| 打开站点 500，日志里是 `No application encryption key` | `config/installed.php` 没生成或没读到 | 检查 `config/` 是否可写；站点未安装时访问 `/install` |
| 上传没反应，也没报错 | `post_max_size` 小于 `upload_max_filesize` | 见 1.5，两个值一起调 |
| 上传返回「写入存储失败」 | `storage/uploads` 不可写 | 修权限，见第四节 |
| 图库里的图片点开 404 | 站点地址变过，存量图片还指着旧地址 | 跑 `tools/fix_image_urls.php` |
| 图库缩略图 404，但原图能开 | 历史数据的缩略图文件名不是 sha1 | 跑 `tools/fix_thumbnails.php` |
| 登录页提交后报 419 | CSRF 令牌失配 | 检查是不是把 `storage/sessions` 删了；反向代理配置见 3.4 |
| 后台保存设置后白屏 | PHP 报错 | 看 `storage/logs/` 下的日志 |
| 缩略图是原图大小 | `thumb_width` 设成了 0，或 GD 不可用 | 后台「常规设置」里改缩略图宽度；确认 `gd` 扩展已装 |
| 竖拍照片显示躺着 | 缺 `exif` 扩展 | 装上 `exif`（见 1.3） |
| 改了伪静态后所有页面 404 | 站点根目录指到了项目根 | 改成 `public/` |
| 图片能看但链接带 `127.0.0.1` | 站点地址没配对 | 后台改站点地址，再跑 `fix_image_urls.php` |

### 日志在哪

| 文件 | 内容 |
|---|---|
| `storage/logs/app-YYYY-MM-DD.log` | 业务日志（上传、登录、操作记录） |
| `storage/logs/framework/laravel.log` | Laravel 框架自身的错误日志 |

---

## 八、从 GitHub 更新到最新版

用 5.1 方式（`git clone`）装的站，更新就是一条 `git pull`：

```bash
cd /www/wwwroot/img.example.com

# 1) 先备份数据库和配置
cp storage/database.sqlite /tmp/woniu-backup-$(date +%F).sqlite
cp config/installed.php /tmp/woniu-installed-$(date +%F).php

# 2) 拉新代码
git pull origin main

# 3) 依赖有变动时再装一次
composer install --no-dev --optimize-autoloader

# 4) 清缓存
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

| 会被 `git pull` 覆盖的 | 不会被覆盖的（你的数据都在这） |
|---|---|
| `app/` `resources/` `public/assets/` `routes/` `config/defaults.php` … | `config/installed.php`、`storage/`（数据库、图片、日志、会话） |

> `.gitignore` 已经把 `config/installed.php`、`storage/database.sqlite`、
> `storage/uploads/`、`storage/thumbs/` 排除在版本库之外，
> 所以 `git pull` 不会碰站点配置和用户数据。

### 8.1 更新后跑一遍自检

```bash
php artisan test                   # 跑一遍测试
php artisan config:clear && php artisan view:clear
```

---

## 九、常见问题（部署 / GitHub 相关）

| 现象 | 原因 | 解决 |
|---|---|---|
| `git clone` 时提示 `Permission denied (publickey)` | 服务器没配 SSH 密钥 | 改用 HTTPS 地址；或把公钥加到 GitHub 账号的 SSH Keys |
| `composer install` 报 `Could not find composer.json` | 目录不对 | `cd` 到仓库根目录（有 `composer.json` 的那层） |
| `composer install` 报 `Your requirements could not be resolved` | PHP 版本低于 8.3 | 换成 PHP 8.3+ 再装 |
| 拉完代码站点 500，日志报 `No application encryption key` | 极少数环境下框架没能自动补临时密钥 | 执行 `php artisan key:generate --force`；装完后正式密钥由安装向导写入 `config/installed.php` |
| 拉完代码站点 500，日志报 `Class "..." not found` | 依赖没装 | `composer install --no-dev --optimize-autoloader` |
| 拉完代码后台样式全丢 | 静态资源缓存 | `php artisan view:clear` + 浏览器强制刷新（Ctrl+F5） |
| `git pull` 提示 `Your local changes to the following files would be overwritten` | 你直接改过仓库里的文件 | `git stash` 暂存改动，或把改动挪到 `resources/views/` 之外的自定义目录 |
| `git pull` 后数据库连不上 | `config/installed.php` 被删了 | 该文件不在版本库里，被删了要恢复备份（见第八节第 1 步） |
| `composer install` 报 `package:discover ... returned with error code 1` | **Windows 用户名含中文**时，Composer 调 `@php artisan` 会把 PHP 路径里的中文按 ANSI 代码页损坏成 `????`，PHP 找不到自己 | 把 PHP 放到纯英文路径（如 `C:\php83\php.exe`）再执行 composer；Linux 服务器不受影响 |
| `php artisan key:generate` 报 `APP_KEY is already present in the environment` | 框架已经自动补了临时密钥 | **不用管**。正式密钥由安装向导写进 `config/installed.php`；非要写 `.env` 就加 `--force` |
| `php artisan key:generate` 卡住不动 | 该命令会先问一句「Are you sure?」，非交互环境没人回答 | 加 `--force` 跳过确认 |
