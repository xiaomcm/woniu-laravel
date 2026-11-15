# 蜗牛图床 数据结构说明

这份文件记的是数据库表结构、字段类型和图片文件的存放规则，写迁移、
改存储逻辑或者排查数据问题时可以对照着看。以后都沿用这套结构，已有的表和字段不动。

---

## 一、数据保留原则（不可协商）

1. **数据库表结构只增不减**——新版本可以加表、加字段，**绝不允许删表、删字段、改字段含义**
2. **图片文件路径规则不变**——`storage/uploads/年/月/日/sha1.扩展名`，永远按这个规则存取
3. **用户密码哈希格式不变**——`password_hash()` / `password_verify()` 标准 PHP 哈希，任何框架都能验证
4. **配置兼容**——`config/installed.php` 里的数据库连接信息在升级时读取，不重写
5. **升级只换代码层，不碰数据层**——数据库和文件目录原地不动

---

## 二、文件存储路径契约

```
storage/
├── uploads/                    # 图片文件（永久保留，绝不清理）
│   └── {Y}/{m}/{d}/{sha1}.{ext}    # 例：2026/11/14/abc123...def.jpeg
├── cache/                      # 缩略图/封面缓存（可清，自动重建）
│   ├── cover/                  #   封面图缓存（sha1 键控）
│   ├── mosaic/                 #   马赛克缓存
│   └── thumbs/                 #   缩略图缓存
├── logs/                       # 运行日志（可清）
├── tmp/                        # 临时文件（可清）
└── database.sqlite             # SQLite 模式的数据库文件
```

**图片直链映射**：`/uploads/{pathname}` → `storage/uploads/{pathname}`
**缩略图映射**：`/thumbs/{pathname}` → `storage/cache/thumbs/...`
**封面映射**：`/covers/{sha1}.jpg` → `storage/cache/cover/...`

---

## 三、数据库表结构（23 张表，冻结）

### 3.1 核心用户表

```sql
-- 用户表（密码用 PHP password_hash，任何框架都能验证）
CREATE TABLE users (
    id              INTEGER PRIMARY KEY,
    name            VARCHAR(64) NOT NULL,      -- 用户名（唯一，不可修改）
    email           VARCHAR(128) NOT NULL,     -- 邮箱（唯一）
    password        VARCHAR(255) NOT NULL,     -- password_hash() 哈希
    group_id        INTEGER NOT NULL,          -- 用户分组 ID
    capacity        BIGINT,                    -- 自定义容量覆盖（NULL=用分组值）
    is_admin        INTEGER NOT NULL DEFAULT 0,
    status          INTEGER NOT NULL DEFAULT 1, -- 1=正常 0=禁用
    avatar          VARCHAR(255),
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME,
    last_login_at   DATETIME,
    group_expired_at DATETIME,                 -- 分组到期时间
    package_id      INTEGER,                   -- 当前套餐 ID
    package_expired_at DATETIME                -- 套餐到期时间
);
```

### 3.2 图片与相册

```sql
-- 图片表
CREATE TABLE images (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER NOT NULL,
    album_id        INTEGER,                   -- 所属相册（NULL=未分组）
    storage_id      INTEGER NOT NULL,          -- 存储驱动 ID
    name            VARCHAR(255) NOT NULL,     -- 显示名
    original_name   VARCHAR(255) NOT NULL,     -- 原始文件名
    pathname        VARCHAR(500) NOT NULL,     -- 相对路径（年/月/日/sha1.ext）
    mimetype        VARCHAR(64) NOT NULL,
    extension       VARCHAR(16) NOT NULL,
    size            BIGINT NOT NULL,           -- 字节数
    md5             VARCHAR(32),
    sha1            VARCHAR(40),
    width           INTEGER NOT NULL,
    height          INTEGER NOT NULL,
    url             VARCHAR(500) NOT NULL,     -- 外链
    thumb_url       VARCHAR(500),
    status          INTEGER NOT NULL DEFAULT 1,
    is_public       INTEGER NOT NULL DEFAULT 0,
    deleted_at      DATETIME,                  -- 软删除（NULL=正常）
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME,
    description     VARCHAR(500),
    tags            VARCHAR(255),
    views           INTEGER NOT NULL DEFAULT 0,
    password        VARCHAR(255),              -- 访问密码（空=无密码）
    review_note     VARCHAR(255)
);

-- 相册表
CREATE TABLE albums (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER NOT NULL,
    name            VARCHAR(128) NOT NULL,
    intro           VARCHAR(255),
    cover           VARCHAR(255),
    image_num       INTEGER NOT NULL DEFAULT 0,
    is_public       INTEGER NOT NULL DEFAULT 0,
    key             VARCHAR(32),
    password        VARCHAR(255),
    views           INTEGER NOT NULL DEFAULT 0,
    created_at      DATETIME,
    updated_at      DATETIME
);
```

### 3.3 存储与驱动

```sql
-- 存储空间表
CREATE TABLE storages (
    id              INTEGER PRIMARY KEY,
    name            VARCHAR(64) NOT NULL,
    type            VARCHAR(32) NOT NULL,      -- local/oss/cos/s3/qiniu/upyun/webdav...
    config          TEXT,                      -- JSON 配置（密钥等）
    url             VARCHAR(255),
    is_default      INTEGER NOT NULL DEFAULT 0,
    status          INTEGER NOT NULL DEFAULT 1,
    created_at      DATETIME,
    updated_at      DATETIME
);

-- 存储驱动表
CREATE TABLE drivers (
    id              INTEGER PRIMARY KEY,
    name            VARCHAR(64) NOT NULL,
    type            VARCHAR(16) NOT NULL,
    provider        VARCHAR(32) NOT NULL,
    config          TEXT,
    status          INTEGER NOT NULL DEFAULT 1,
    sortable        INTEGER NOT NULL DEFAULT 0,
    remark          VARCHAR(255),
    created_at      DATETIME,
    updated_at      DATETIME
);
```

### 3.4 用户分组与套餐

```sql
-- 用户分组表
CREATE TABLE groups (
    id              INTEGER PRIMARY KEY,
    name            VARCHAR(64) NOT NULL,
    is_default      INTEGER NOT NULL DEFAULT 0,
    capacity        BIGINT NOT NULL DEFAULT 0, -- 容量（字节）
    max_file_size   BIGINT NOT NULL DEFAULT 0,
    allowed_extensions TEXT,
    default_storage_id INTEGER,
    max_upload_num  INTEGER NOT NULL DEFAULT 0,
    watermark       INTEGER NOT NULL DEFAULT 0,
    daily_limit     INTEGER NOT NULL DEFAULT 0,
    permissions     TEXT,
    buyable         INTEGER NOT NULL DEFAULT 0,
    created_at      DATETIME,
    updated_at      DATETIME
);

-- 套餐表
CREATE TABLE packages (
    id              INTEGER PRIMARY KEY,
    name            VARCHAR(64) NOT NULL,
    group_id        INTEGER NOT NULL,
    price_cents     INTEGER NOT NULL,          -- 价格（分）
    duration_days   INTEGER NOT NULL,
    capacity        INTEGER NOT NULL,
    max_file_size   INTEGER NOT NULL,
    daily_limit     INTEGER NOT NULL,
    description     VARCHAR(500),
    features        TEXT,
    status          INTEGER NOT NULL DEFAULT 1,
    sortable        INTEGER NOT NULL DEFAULT 0,
    created_at      DATETIME,
    updated_at      DATETIME
);

-- 订单表
CREATE TABLE orders (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER NOT NULL,
    package_id      INTEGER NOT NULL,
    package_name    VARCHAR(64) NOT NULL,
    amount_cents    INTEGER NOT NULL,
    discount_cents  INTEGER NOT NULL DEFAULT 0,
    total_cents     INTEGER NOT NULL,
    coupon_id       INTEGER,
    driver_id       INTEGER,
    gateway         VARCHAR(32),
    trade_no        VARCHAR(80),
    status          INTEGER NOT NULL DEFAULT 0, -- 0=待付 1=已付 2=已取消
    paid_at         DATETIME,
    expire_at       DATETIME,
    created_at      DATETIME,
    updated_at      DATETIME
);

-- 优惠码表
CREATE TABLE coupons (
    id              INTEGER PRIMARY KEY,
    code            VARCHAR(32) NOT NULL,
    name            VARCHAR(64) NOT NULL,
    type            VARCHAR(10) NOT NULL,      -- fixed/percent
    value           INTEGER NOT NULL,
    min_amount_cents INTEGER NOT NULL DEFAULT 0,
    max_discount_cents INTEGER NOT NULL DEFAULT 0,
    quota           INTEGER NOT NULL DEFAULT 0,
    used            INTEGER NOT NULL DEFAULT 0,
    per_user        INTEGER NOT NULL DEFAULT 0,
    scope           TEXT,
    starts_at       DATETIME,
    expires_at      DATETIME,
    status          INTEGER NOT NULL DEFAULT 1,
    created_at      DATETIME
);

-- 优惠码使用记录
CREATE TABLE coupon_uses (
    id              INTEGER PRIMARY KEY,
    coupon_id       INTEGER NOT NULL,
    user_id         INTEGER NOT NULL,
    order_id        INTEGER NOT NULL,
    created_at      DATETIME
);
```

### 3.5 工单、反馈、公告、举报

```sql
-- 工单表
CREATE TABLE tickets (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER NOT NULL,
    subject         VARCHAR(200) NOT NULL,
    category        VARCHAR(32) NOT NULL,
    priority        INTEGER NOT NULL DEFAULT 1,
    status          INTEGER NOT NULL DEFAULT 0, -- 0=待处理 1=已回复 2=已关闭
    last_reply_at   DATETIME,
    created_at      DATETIME,
    updated_at      DATETIME
);

-- 工单回复表
CREATE TABLE ticket_replies (
    id              INTEGER PRIMARY KEY,
    ticket_id       INTEGER NOT NULL,
    user_id         INTEGER NOT NULL,
    is_admin        INTEGER NOT NULL DEFAULT 0,
    content         TEXT NOT NULL,
    created_at      DATETIME
);

-- 反馈表
CREATE TABLE feedbacks (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER,
    kind            VARCHAR(16) NOT NULL,
    content         TEXT NOT NULL,
    contact         VARCHAR(128),
    status          INTEGER NOT NULL DEFAULT 0,
    reply           TEXT,
    created_at      DATETIME,
    replied_at      DATETIME
);

-- 公告表
CREATE TABLE notices (
    id              INTEGER PRIMARY KEY,
    title           VARCHAR(128) NOT NULL,
    content         TEXT,
    level           VARCHAR(16) NOT NULL DEFAULT 'info', -- info/success/warning/danger
    pinned          INTEGER NOT NULL DEFAULT 0,
    status          INTEGER NOT NULL DEFAULT 1,
    created_at      DATETIME
);

-- 举报表
CREATE TABLE reports (
    id              INTEGER PRIMARY KEY,
    image_id        INTEGER,
    album_id        INTEGER,
    reporter_id     INTEGER,
    category        VARCHAR(32) NOT NULL,
    reason          VARCHAR(500),
    status          INTEGER NOT NULL DEFAULT 0,
    handler_id      INTEGER,
    handle_note     VARCHAR(500),
    created_at      DATETIME,
    handled_at      DATETIME
);
```

### 3.6 其他

```sql
-- 站点设置（key-value）
CREATE TABLE settings (
    key             VARCHAR(64) PRIMARY KEY,
    value           TEXT
);

-- API Token 表
CREATE TABLE tokens (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER NOT NULL,
    name            VARCHAR(64) NOT NULL,
    token_hash      VARCHAR(64) NOT NULL,
    token_prefix    VARCHAR(16) NOT NULL,
    last_used_at    DATETIME,
    expires_at      DATETIME,
    created_at      DATETIME
);

-- 系统日志表
CREATE TABLE logs (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER,
    action          VARCHAR(64) NOT NULL,
    ip              VARCHAR(45),
    ua              VARCHAR(255),
    detail          TEXT,
    created_at      DATETIME
);

-- 自定义页面表
CREATE TABLE pages (
    id              INTEGER PRIMARY KEY,
    title           VARCHAR(128) NOT NULL,
    slug            VARCHAR(64) NOT NULL,
    content         TEXT,
    status          INTEGER NOT NULL DEFAULT 1,
    sortable        INTEGER NOT NULL DEFAULT 0,
    show_in_footer  INTEGER NOT NULL DEFAULT 0,
    created_at      DATETIME,
    updated_at      DATETIME
);

-- OAuth 登录表
CREATE TABLE user_oauth (
    id              INTEGER PRIMARY KEY,
    user_id         INTEGER NOT NULL,
    driver_id       INTEGER,
    provider        VARCHAR(32) NOT NULL,
    openid          VARCHAR(128) NOT NULL,
    unionid         VARCHAR(128),
    nickname        VARCHAR(128),
    avatar          VARCHAR(255),
    created_at      DATETIME
);

-- 关联表（分组-存储 / 分组-驱动 / 存储-驱动）
CREATE TABLE group_storage (group_id INTEGER NOT NULL, storage_id INTEGER NOT NULL, PRIMARY KEY(group_id, storage_id));
CREATE TABLE group_driver  (group_id INTEGER NOT NULL, driver_id  INTEGER NOT NULL, PRIMARY KEY(group_id, driver_id));
CREATE TABLE storage_driver(storage_id INTEGER NOT NULL, driver_id INTEGER NOT NULL, PRIMARY KEY(storage_id, driver_id));
```

---

## 四、密码与密钥格式契约

| 项 | 格式 | 保证 |
|---|---|---|
| 用户密码 | `password_hash($pw, PASSWORD_DEFAULT)` | 任何 PHP 框架 `password_verify()` 可验证 |
| API Token | `hash('sha256', $token)` 存 `token_hash`，前缀存 `token_prefix` | 任何框架可比对 |
| 图片密码 | `password_hash($pw, PASSWORD_DEFAULT)` 存 `images.password` | 同上 |
| 相册密码 | `password_hash($pw, PASSWORD_DEFAULT)` 存 `albums.password` | 同上 |
| 存储密钥 | JSON 明文存 `storages.config` | 升级时原样读取 |
| 站点密钥 | `config/installed.php` → `app.key` | 升级时保留 |

---

## 五、改动数据结构时留意几件事

1. 动手前先备份 `storage/uploads/` 整个目录和数据库文件
2. 业务模型直接按上面的表结构读写，新代码接着用旧库就行
3. 迁移脚本只加字段、加表，不删也不改名
4. `pathname` 字段存的就是文件相对路径，照着用，别改规则
5. 密码是 `password_verify()` 能验证的哈希，不用迁移
6. `storage/cache/` 下的缓存都能删，程序会自己重建

---

*这些表结构和路径规则一直沿用，要改之前先看看会不会影响到已有的数据。*

*作者 QQ 34995549 · www.zhujitown.com*
