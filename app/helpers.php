<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 全局助手函数。
 *
 * 关于加载顺序：本文件由 public/index.php 与 artisan 在 vendor/autoload.php
 * 之前 require，Laravel 自带的助手函数都带 function_exists 守卫，谁先定义谁生效。
 *
 * 但不能只依赖这个顺序 —— PHPUnit 之类的入口会先加载 Composer 的自动加载，
 * 那时 Laravel 的同名函数已经定义了。所以既有的语义真正落在下面这几处，
 * 与「谁先被定义」无关：
 *
 *   url() / asset()  → App\Core\Url + App\Routing\WoniuUrlGenerator
 *                       （Laravel 的全局函数最终也落到容器里的 UrlGenerator）
 *   redirect()/back()→ App\Core\Response::redirect()
 *   csrf_field()     → App\Core\Csrf::field()（Blade 编译期替换）
 *
 * e() / now() / abort() / csrf_token() 不在这里重复定义，
 * 直接用 Laravel 的等价实现，行为与既有行为一致。
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Session;
use App\Core\Setting;
use Illuminate\Support\HtmlString;

if (!function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (!function_exists('base_url')) {
    /**
     * 站点根地址。优先用安装向导写入的 app.url，
     * 没写的话按当前请求的根路径算（兼容装在子目录的情况）。
     */
    function base_url(): string
    {
        return \App\Core\Url::base();
    }
}

if (!function_exists('url')) {
    /**
     * 站内地址。与既有的 url() 一致。
     *
     * 实际的生成规则在 App\Core\Url 里，Laravel 自己的 UrlGenerator
     * 也走同一套（见 App\Routing\WoniuUrlGenerator），
     * 所以无论这个全局函数由谁定义，产出的地址都一样。
     */
    function url(string $path = ''): string
    {
        return \App\Core\Url::to($path);
    }
}

if (!function_exists('asset')) {
    /**
     * 静态资源地址。带文件修改时间做版本号，浏览器不会拿旧缓存。
     */
    function asset(string $path): string
    {
        return app('url')->asset($path);
    }
}

if (!function_exists('redirect')) {
    /**
     * 跳转。原先是 header + exit，这里换成抛响应异常，效果一样（立刻中断本次请求），
     * 但请求生命周期交给框架统一收尾。
     *
     *既有的响应体是空的（只发 Location 头），这里也保持一致。
     *
     * 实际的跳转逻辑在 App\Core\Response::redirect()，那是唯一的语义来源。
     * 这里只做一层薄封装，避免两处实现走偏。
     */
    function redirect(string $path = '/', int $status = 302, array $headers = []): never
    {
        \App\Core\Response::redirect($path, $status);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * CSRF 隐藏域。
     *
     * Laravel 自带的 csrf_field() 会多一个 autocomplete="off"，
     *原先没有这个属性。实际的 HTML 生成在 App\Core\Csrf::field()，
     * 那是唯一的语义来源；Blade 编译期也会把 csrf_field() 换成它，
     * 这样无论全局函数由谁定义，页面输出的字节都与既有行为一致。
     */
    function csrf_field(): string
    {
        return \App\Core\Csrf::field();
    }
}

if (!function_exists('back')) {
    /**
     * 返回上一页。
     *
     *原先是取 referer 头跳转，没有 referer 就回首页。
     * 这里走 App\Core\Response::redirect()，跳转语义与 redirect() 一致，
     * 不依赖全局函数由谁定义。
     */
    function back(): never
    {
        $referer = (string) request()->headers->get('referer', '');
        \App\Core\Response::redirect($referer !== '' ? $referer : '/');
    }
}

if (!function_exists('flash')) {
    /**
     * 写一条一次性提示。
     *
     * 键名用 _woniu_flash 而不是 _flash：Laravel 自带的 flash 机制会往 _flash
     * 里写 ['new' => [], 'old' => []]，跟这里的列表结构会撞车。
     */
    function flash(string $type, string $message): void
    {
        $items = Session::get('_woniu_flash', []);
        if (!is_array($items)) {
            $items = [];
        }
        $items[] = ['type' => $type, 'message' => $message];
        Session::put('_woniu_flash', $items);
    }
}

if (!function_exists('flashes')) {
    function flashes(): array
    {
        $items = Session::get('_woniu_flash', []);
        Session::forget('_woniu_flash');
        return is_array($items) ? $items : [];
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $user = Auth::user();
        return $user !== null && (int) $user['is_admin'] === 1;
    }
}

if (!function_exists('lazy_attr')) {
    /**
     * 图片懒加载属性串：后台「图片延迟加载」开关关闭时返回空串。
     *
     * loading="lazy" 让浏览器把图片下载推迟到滚动进视口附近，
     * 首屏不再一次性把所有缩略图拉下来 —— 图库、广场、瀑布流这类页面最吃带宽。
     * decoding="async" 让解码不阻塞主线程，滚动更跟手。
     *
     * 只给「列表里的图片」用：当前正在看的那张大图、logo、验证码、支付二维码
     * 这些必须立刻显示的，走 eager（也就是不加这个属性）。
     *
     * 返回 HtmlString 而不是裸字符串：Blade 的 {{ }} 会把引号转义成 &quot;，
     * 属性就废了。HtmlString 会被原样输出。
     */
    function lazy_attr(): HtmlString
    {
        return Setting::bool('lazy_load_enabled', true)
            ? new HtmlString(' loading="lazy" decoding="async"')
            : new HtmlString('');
    }
}

if (!function_exists('account_label')) {
    /**
     * 用户下拉菜单头部副标题：显示账号绑定的邮箱。
     *
     * 邮箱字段是账号的登录标识之一（见 User::findForLogin），正常注册必须填合法邮箱。
     * 但历史数据里存在脚本造的账号 —— email 列存的是用户名（如 "admin"）而不是邮箱，
     * 直接显示会变成「管理员 · admin」这种把用户名当邮箱的怪样子。
     * 所以这里先做一次格式校验：不是合法邮箱就回退显示用户名，不展示假邮箱。
     */
    function account_label(?array $user): string
    {
        $name = trim((string) ($user['name'] ?? ''));
        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return $name;
    }
}

if (!function_exists('account_role')) {
    /**
     * 用户下拉菜单头部的角色文案，只作悬停提示用（副标题已经让位给邮箱）。
     */
    function account_role(?array $user): string
    {
        return (int) ($user['is_admin'] ?? 0) === 1 ? '管理员' : '普通用户';
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes($bytes, int $precision = 2): string
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = (int) floor(log($bytes, 1024));
        $index = min($index, count($units) - 1);
        return round($bytes / (1024 ** $index), $precision) . ' ' . $units[$index];
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 16): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('format_money')) {
    function format_money($cents, bool $withSymbol = true): string
    {
        $symbol = (string) setting('currency_symbol', '¥');
        $amount = number_format(((int) $cents) / 100, 2, '.', ',');
        return $withSymbol ? $symbol . $amount : $amount;
    }
}

if (!function_exists('money_input')) {
    function money_input($cents): string
    {
        return number_format(((int) $cents) / 100, 2, '.', '');
    }
}

if (!function_exists('badge')) {
    function badge(string $text, string $tone = 'muted'): string
    {
        return '<span class="badge badge-' . e($tone) . '">' . e($text) . '</span>';
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return \App\Core\Request::method() === 'POST';
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $value, int $limit = 40): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . '…' : $value;
    }
}

if (!function_exists('image_thumb')) {
    /**
     * 前台缩略图地址：未过审/已下线给空白占位，加密图照常给地址
     * 加密图的服务端响应会自动换成马赛克，所以地址可以公开，内容不会出站
     * $manage=true 用于「我的图片」这类本人管理页，走 owner 专用入口看自己的小图
     */
    function image_thumb(array $image, bool $manage = false): string
    {
        $status = (int) ($image['status'] ?? 1);
        $thumb  = trim((string) ($image['thumb_url'] ?? ''));

        if ($status !== 1) {
            // 待审/已拒绝/已下架一律白块：不给内容，也不让浏览器顶破图
            // 本人管理页同样如此，状态由卡片角标说明，看图请去后台审核入口
            return blank_image($image);
        }

        if ($thumb !== '') {
            return $thumb;
        }

        // 小图上传时不生成缩略图，thumb_url 因此为空：回落到统一入口，那边会按需补生成。
        // 不要退回固定比例的占位图，比例对不上，卡片高度会跟同列其他图错开
        $sha1 = trim((string) ($image['sha1'] ?? ''));
        if ($sha1 !== '') {
            return url('thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg');
        }

        return blank_image($image);
    }
}

if (!function_exists('image_cover')) {
    /**
     * 广场封面地址：固定 1280×860，服务端把任意比例的图裁成同一尺寸再发
     * 未过审/已下线的图给同尺寸白块，卡片大小仍然一致
     */
    function image_cover(array $image): string
    {
        if ((int) ($image['status'] ?? 1) !== 1) {
            return \App\Services\Blank::url(\App\Services\Cover::W, \App\Services\Cover::H);
        }

        $sha1 = trim((string) ($image['sha1'] ?? ''));
        if ($sha1 === '') {
            return \App\Services\Blank::url(\App\Services\Cover::W, \App\Services\Cover::H);
        }

        // 带版本号：给图片设密码后 updated_at 变化，URL 跟着变，
        // 浏览器不会拿 30 天前缓存的清晰封面继续显示
        $version = substr(sha1((string) ($image['updated_at'] ?? '')), 0, 8);

        return \App\Services\Cover::url($sha1) . '?v=' . $version;
    }
}

if (!function_exists('blank_image')) {
    /**
     * 空白占位图：纯白，尺寸取原图自身的宽高
     * 比例跟原图完全一致，所以放进去之后卡片高度和真图一样，不会把瀑布流撑歪
     */
    function blank_image(array $image): string
    {
        return \App\Services\Blank::url((int) ($image['width'] ?? 0), (int) ($image['height'] ?? 0));
    }
}

if (!function_exists('image_locked')) {
    /**
     * 图片是否需要密码：图片自身密码，或所属相册有密码
     */
    function image_locked(array $image): bool
    {
        return trim((string) ($image['password'] ?? '')) !== ''
            || trim((string) ($image['album_password'] ?? '')) !== '';
    }
}

if (!function_exists('image_lock_badge')) {
    /**
     * 是否要在缩略图上盖锁标：需要密码，且当前访客既不是本人也不是管理员，也没解锁过
     */
    function image_lock_badge(array $image): bool
    {
        if (!image_locked($image)) {
            return false;
        }
        $viewer = Auth::user();
        if ($viewer !== null) {
            if ((int) $viewer['id'] === (int) ($image['user_id'] ?? 0) || is_admin()) {
                return false;
            }
        }
        if (\App\Services\ShareService::unlocked('image', (int) ($image['id'] ?? 0), $image)) {
            return false;
        }
        $albumId = (int) ($image['album_id'] ?? 0);
        if ($albumId > 0 && \App\Services\ShareService::unlocked('album', $albumId)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('image_state_note')) {
    /**
     * 前台图片状态角标：让占位图有人话解释，不至于像破图
     */
    function image_state_note(array $image): string
    {
        $status = (int) ($image['status'] ?? 1);
        if ($status === 2) {
            return '已下线';
        }
        if ($status === 0) {
            return '审核中';
        }
        if (trim((string) ($image['password'] ?? '')) !== '') {
            return '加密';
        }
        return '';
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        return \App\Core\Request::path();
    }
}

if (!function_exists('nav_active')) {
    function nav_active(string $prefix, bool $exact = false): string
    {
        $current = current_path();
        if ($exact) {
            return $current === $prefix ? 'active' : '';
        }
        if ($prefix === '/') {
            return $current === '/' ? 'active' : '';
        }
        return strncmp($current, $prefix, strlen($prefix)) === 0 ? 'active' : '';
    }
}

if (!function_exists('paginate_links')) {
    function paginate_links(int $page, int $lastPage, array $query = []): string
    {
        if ($lastPage <= 1) {
            return '';
        }
        $build = static function (int $p) use ($query): string {
            $query['page'] = $p;
            return '?' . http_build_query($query);
        };
        $html = '<nav class="pager">';
        $html .= $page > 1
            ? '<a class="pager-item" href="' . e($build($page - 1)) . '">上一页</a>'
            : '<span class="pager-item disabled">上一页</span>';

        $start = max(1, $page - 2);
        $end   = min($lastPage, $start + 4);
        $start = max(1, $end - 4);

        if ($start > 1) {
            $html .= '<a class="pager-item" href="' . e($build(1)) . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="pager-item disabled">…</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $page
                ? '<span class="pager-item active">' . $i . '</span>'
                : '<a class="pager-item" href="' . e($build($i)) . '">' . $i . '</a>';
        }
        if ($end < $lastPage) {
            if ($end < $lastPage - 1) {
                $html .= '<span class="pager-item disabled">…</span>';
            }
            $html .= '<a class="pager-item" href="' . e($build($lastPage)) . '">' . $lastPage . '</a>';
        }
        $html .= $page < $lastPage
            ? '<a class="pager-item" href="' . e($build($page + 1)) . '">下一页</a>'
            : '<span class="pager-item disabled">下一页</span>';
        $html .= '</nav>';
        return $html;
    }
}

if (!function_exists('query')) {
    function query(string $key, string $default = ''): string
    {
        $value = request()->query($key, $default);
        return is_string($value) ? trim($value) : $default;
    }
}

if (!function_exists('session_ttl_script')) {
    /**
     * 账号自动退出：把剩余秒数注入页面，供 app.js 做倒计时提醒与到点跳登录。
     * 未登录或后台没设超时（0）时输出空串，页面零开销。
     */
    function session_ttl_script(): string
    {
        $ttl = (int) \App\Core\Setting::get('session_ttl', 0);
        if ($ttl <= 0 || !Session::has('user_id')) {
            return '';
        }
        $left = $ttl - (time() - (int) Session::get('_last_active', time()));
        if ($left <= 0) {
            return '';
        }
        return '<script>window.__SESSION_LEFT=' . (int) $left . ';</script>';
    }
}
