<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$get = static fn (string $key, string $default = ''): string => (string) ($settings[$key] ?? $default);
?>
<form method="post" action="{{ url('admin/settings') }}">
    {!! csrf_field() !!}
    <div class="card" style="margin-top:0">
        <div class="card-head"><h2>站点信息</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="field">
                    <label class="label">站点名称</label>
                    <input class="input" name="site_name" value="{{ $get('site_name', '蜗牛图床') }}" required>
                </div>
                <div class="field">
                    <label class="label">站点地址 <span class="hint">留空自动识别</span></label>
                    <input class="input" name="site_url" value="{{ $get('site_url') }}" placeholder="https://img.example.com">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">站点描述</label>
                    <input class="input" name="site_description" value="{{ $get('site_description') }}">
                </div>
                <div class="field">
                    <label class="label">后台名称 <span class="hint">留空跟随站点名</span></label>
                    <input class="input" name="admin_title" value="{{ $get('admin_title') }}" placeholder="{{ $get('site_name', '蜗牛图床') }} 后台">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">ICP 备案号</label>
                    <input class="input" name="site_icp" value="{{ $get('site_icp') }}">
                </div>
                <div class="field">
                    <label class="label">页脚版权</label>
                    <input class="input" name="site_footer" value="{{ $get('site_footer') }}">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">网站副标题 <span class="hint">拼在浏览器标签标题后面，留空只显示站名</span></label>
                    <input class="input" name="site_subtitle" value="{{ $get('site_subtitle') }}" placeholder="例如：免费图片托管 · 支持外链">
                </div>
                <div class="field">
                    <label class="label">网站关键词 <span class="hint">逗号分隔，给搜索引擎看</span></label>
                    <input class="input" name="site_keywords" value="{{ $get('site_keywords') }}" placeholder="图床,免费图床,图片外链,图片托管">
                </div>
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="label">SEO 介绍 <span class="hint">搜索引擎结果里标题下面那段描述，建议 80~160 字；留空回落到上面的「站点描述」</span></label>
                <textarea class="input" name="site_seo_description" rows="3" placeholder="用一两句话讲清这个站是做什么的、有什么好处">{{ $get('site_seo_description') }}</textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>夜间模式</h2></div>
        <div class="card-body">
            <label class="switch" style="margin-bottom:14px">
                <input type="checkbox" name="theme_auto_enabled" value="1" {!! $get('theme_auto_enabled', '0') === '1' ? 'checked' : '' !!}>
                <span class="track"></span><span>按时间自动切换深浅色</span>
            </label>
            <div class="form-row">
                <div class="field">
                    <label class="label">夜间开始 <span class="hint">到点切深色，24 小时制</span></label>
                    <input class="input" type="time" name="theme_dark_start" value="{{ $get('theme_dark_start', '22:00') }}">
                </div>
                <div class="field">
                    <label class="label">夜间结束 <span class="hint">到点切回浅色</span></label>
                    <input class="input" type="time" name="theme_dark_end" value="{{ $get('theme_dark_end', '07:00') }}">
                </div>
            </div>
            <p class="hint" style="margin:12px 0 0">访客手动点过主题按钮的，以访客自己的选择为准；没手动选过的，按这里的时间自动切换。结束时间早于开始时间表示跨零点（比如 22:00 到次日 07:00）。</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>注册与用户</h2></div>
        <div class="card-body">
            <div style="display:flex;gap:26px;flex-wrap:wrap;margin-bottom:16px">
                <label class="switch">
                    <input type="checkbox" name="register_enabled" value="1" {!! $get('register_enabled') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>开放注册</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="register_email_verify" value="1" {!! $get('register_email_verify') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>注册需邮箱验证</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="register_agree_terms" value="1" {!! $get('register_agree_terms', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>注册需同意服务条款</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="email_change_verify" value="1" {!! $get('email_change_verify', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>修改邮箱需邮箱验证码</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="review_enabled" value="1" {!! $get('review_enabled') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>上传需审核</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="guest_upload" value="1" {!! $get('guest_upload') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>允许游客上传</span>
                </label>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">注册邀请码 <span class="hint">留空不校验</span></label>
                    <input class="input" name="register_invite" value="{{ $get('register_invite') }}">
                </div>
                <div class="field">
                    <label class="label">默认用户组</label>
                    <select class="select" name="default_group_id">
                        @foreach ($groups as $group)
                            <option value="{!! (int) $group['id'] !!}" {!! (int) $get('default_group_id', '1') === (int) $group['id'] ? 'selected' : '' !!}>
                                {{ (string) $group['name'] }}                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>注册验证码</h2></div>
        <div class="card-body">
            <div style="display:flex;gap:26px;flex-wrap:wrap;margin-bottom:16px">
                <label class="switch">
                    <input type="checkbox" name="captcha_enabled" value="1" {!! $get('captcha_enabled', '0') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用注册验证码（全局开关）</span>
                </label>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">验证码类型</label>
                    <select class="select" name="captcha_provider" id="captcha-provider">
                        <option value="local" {!! $get('captcha_provider', 'local') === 'local' ? 'selected' : '' !!}>本地验证码</option>
                        <option value="tencent" {!! $get('captcha_provider') === 'tencent' ? 'selected' : '' !!}>腾讯云验证码</option>
                        <option value="aliyun" {!! $get('captcha_provider') === 'aliyun' ? 'selected' : '' !!}>阿里云验证码</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label">验证方式</label>
                    <select class="select" name="captcha_mode" id="captcha-mode">
                        <option value="image" {!! $get('captcha_mode', 'image') === 'image' ? 'selected' : '' !!}>图形验证码</option>
                        <option value="slide" {!! $get('captcha_mode') === 'slide' ? 'selected' : '' !!}>滑块 / 数字滑动验证</option>
                        <option value="sms" {!! $get('captcha_mode') === 'sms' ? 'selected' : '' !!}>短信验证码</option>
                    </select>
                </div>
            </div>

            <div data-captcha-group="local">
                <div class="form-row">
                    <div class="field">
                        <label class="label">图形验证码位数</label>
                        <input class="input" name="captcha_local_length" type="number" min="3" max="8" value="{{ $get('captcha_local_length', '4') }}">
                    </div>
                    <div class="field">
                        <label class="label">说明</label>
                        <p class="hint" style="margin:8px 0 0">服务端生成 SVG 图形验证码，无需 GD、不依赖外网。点击图片可换一张。</p>
                    </div>
                </div>
            </div>

            <div data-captcha-group="tencent">
                <h3 style="font-size:14px;margin:16px 0 10px;color:var(--muted)">腾讯云 · 通用密钥</h3>
                <div class="form-row" data-captcha-sub="common">
                    <div class="field"><label class="label">SecretId</label><input class="input" name="captcha_tencent_secret_id" value="{{ $get('captcha_tencent_secret_id') }}" placeholder="CAM API 密钥"></div>
                    <div class="field"><label class="label">SecretKey</label><input class="input" name="captcha_tencent_secret_key" type="password" value="{{ $get('captcha_tencent_secret_key') }}"></div>
                </div>
                <div class="form-row" data-captcha-sub="slide">
                    <div class="field"><label class="label">CaptchaAppId</label><input class="input" name="captcha_tencent_app_id" value="{{ $get('captcha_tencent_app_id') }}"></div>
                    <div class="field"><label class="label">AppSecretKey</label><input class="input" name="captcha_tencent_app_secret" type="password" value="{{ $get('captcha_tencent_app_secret') }}"></div>
                </div>
                <div class="form-row" data-captcha-sub="sms">
                    <div class="field"><label class="label">短信 SdkAppId</label><input class="input" name="captcha_tencent_sdk_app_id" value="{{ $get('captcha_tencent_sdk_app_id') }}"></div>
                    <div class="field"><label class="label">短信签名</label><input class="input" name="captcha_tencent_sign_name" value="{{ $get('captcha_tencent_sign_name') }}"></div>
                    <div class="field"><label class="label">短信模板 ID</label><input class="input" name="captcha_tencent_template_id" value="{{ $get('captcha_tencent_template_id') }}"></div>
                    <div class="field"><label class="label">地域</label><input class="input" name="captcha_tencent_region" value="{{ $get('captcha_tencent_region', 'ap-guangzhou') }}"></div>
                </div>
            </div>

            <div data-captcha-group="aliyun">
                <h3 style="font-size:14px;margin:16px 0 10px;color:var(--muted)">阿里云 · 通用密钥</h3>
                <div class="form-row" data-captcha-sub="common">
                    <div class="field"><label class="label">AccessKey ID</label><input class="input" name="captcha_aliyun_access_key_id" value="{{ $get('captcha_aliyun_access_key_id') }}"></div>
                    <div class="field"><label class="label">AccessKey Secret</label><input class="input" name="captcha_aliyun_access_key_secret" type="password" value="{{ $get('captcha_aliyun_access_key_secret') }}"></div>
                </div>
                <div class="form-row" data-captcha-sub="slide">
                    <div class="field"><label class="label">SceneId</label><input class="input" name="captcha_aliyun_scene_id" value="{{ $get('captcha_aliyun_scene_id') }}"></div>
                    <div class="field"><label class="label">prefix</label><input class="input" name="captcha_aliyun_prefix" value="{{ $get('captcha_aliyun_prefix') }}"></div>
                    <div class="field"><label class="label">校验 Endpoint</label><input class="input" name="captcha_aliyun_endpoint" value="{{ $get('captcha_aliyun_endpoint', 'captcha.cn-shanghai.aliyuncs.com') }}"></div>
                </div>
                <div class="form-row" data-captcha-sub="sms">
                    <div class="field"><label class="label">短信签名</label><input class="input" name="captcha_aliyun_sign_name" value="{{ $get('captcha_aliyun_sign_name') }}"></div>
                    <div class="field"><label class="label">短信模板 CODE</label><input class="input" name="captcha_aliyun_template_code" value="{{ $get('captcha_aliyun_template_code') }}"></div>
                    <div class="field"><label class="label">地域</label><input class="input" name="captcha_aliyun_region" value="{{ $get('captcha_aliyun_region', 'cn-hangzhou') }}"></div>
                </div>
            </div>

            <p class="hint" style="margin:12px 0 0">
                本地验证码为纯图形；腾讯云 / 阿里云均支持「滑块·数字滑动验证」与「短信验证码」两种方式。
                滑块需前端 SDK 回调票据后由服务端二次校验，短信验证码会写入注册用户的手机号。
                关闭全局开关后注册流程完全不校验验证码。
            </p>

            {{-- 验证码联动脚本放在本卡片内部：卡片之间靠 .card + .card 的相邻兄弟选择器留 18px 间隙，
                 脚本标签插在两个卡片中间会打断相邻关系，间距就没了。 --}}
            <script>
            (function () {
                var provider = document.getElementById('captcha-provider');
                var mode = document.getElementById('captcha-mode');
                if (!provider || !mode) { return; }
                function sync() {
                    var p = provider.value;
                    Array.prototype.forEach.call(mode.options, function (o) {
                        var allowed = p === 'local' ? o.value === 'image' : (o.value === 'slide' || o.value === 'sms');
                        o.disabled = !allowed;
                        o.hidden = !allowed;
                    });
                    if (p === 'local') { mode.value = 'image'; }
                    else if (mode.value === 'image' || mode.value === '') { mode.value = 'slide'; }
                    document.querySelectorAll('[data-captcha-group]').forEach(function (g) {
                        var match = g.getAttribute('data-captcha-group') === p;
                        g.style.display = match ? '' : 'none';
                        if (!match) { return; }
                        g.querySelectorAll('[data-captcha-sub]').forEach(function (s) {
                            var sub = s.getAttribute('data-captcha-sub');
                            s.style.display = (sub === 'common' || sub === mode.value || p === 'local') ? '' : 'none';
                        });
                    });
                }
                provider.addEventListener('change', sync);
                mode.addEventListener('change', sync);
                sync();
            })();
            </script>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>上传设置</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="field">
                    <label class="label">单文件大小上限 <span class="hint">MB</span></label>
                    <input class="input" name="upload_max_size_mb" value="{{ (string) round(((int) $get('upload_max_size', '5242880')) / 1048576, 2) }}">
                    <div class="form-help">
                        服务器 PHP 实际限制：upload_max_filesize = {{ (string) ini_get('upload_max_filesize') }}，
                        post_max_size = {{ (string) ini_get('post_max_size') }}。超过此值请在 php.ini 中调大。
                    </div>
                </div>
                <div class="field">
                    <label class="label">每页显示数量</label>
                    <input class="input" name="page_size" value="{{ $get('page_size', '24') }}">
                </div>
            </div>
            <div class="field">
                <label class="label">允许的图片格式 <span class="hint">逗号分隔</span></label>
                <input class="input" name="upload_allowed" value="{{ $get('upload_allowed') }}">
            </div>
            <div class="field">
                <label class="label">文件路径规则</label>
                <input class="input" name="upload_path_format" value="{{ $get('upload_path_format', '{Y}/{m}/{d}/{sha1}.{ext}') }}">
                <div class="form-help">
                    可用变量：{Y} {y} {m} {d} {H} {i} {s} {Ymd} {md5} {sha1} {uuid} {random} {random:12} {origin} {ext}
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" name="upload_dedupe" value="1" {!! $get('upload_dedupe') === '1' ? 'checked' : '' !!}>
                <span class="track"></span><span>开启秒传（相同内容不重复存储）</span>
            </label>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>图片处理</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="field">
                    <label class="label">压缩质量 <span class="hint">1-100，仅 JPEG/WebP</span></label>
                    <input class="input" name="upload_quality" value="{{ $get('upload_quality', '92') }}">
                </div>
                <div class="field">
                    <label class="label">最大宽度 <span class="hint">px，0 表示不缩放</span></label>
                    <input class="input" name="upload_max_width" value="{{ $get('upload_max_width', '0') }}">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">缩略图宽度 <span class="hint">px</span></label>
                    <input class="input" name="thumb_width" value="{{ $get('thumb_width', '360') }}">
                </div>
                <div class="field">
                    <label class="label">缩略图</label><br>
                    <label class="switch">
                        <input type="checkbox" name="thumb_enabled" value="1" {!! $get('thumb_enabled') === '1' ? 'checked' : '' !!}>
                        <span class="track"></span><span>生成缩略图</span>
                    </label>
                </div>
            </div>

            <label class="switch" style="margin-bottom:16px">
                <input type="checkbox" name="lightbox_enabled" value="1" {!! $get('lightbox_enabled', '1') === '1' ? 'checked' : '' !!}>
                <span class="track"></span><span>开启灯箱预览</span>
            </label>

            <label class="switch" style="margin-bottom:8px">
                <input type="checkbox" name="lazy_load_enabled" value="1" {!! $get('lazy_load_enabled', '1') === '1' ? 'checked' : '' !!}>
                <span class="track"></span><span>开启图片延迟加载（懒加载）</span>
            </label>
            <div class="form-help" style="margin-bottom:16px">
                列表、广场、瀑布流里的图片滚动到视口附近才开始下载，首屏不再一次性拉全量缩略图，省流量也更。
                当前正在查看的那张大图、站点 Logo、验证码不受影响，始终立即加载。
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:6px 0 18px">

            <label class="switch" style="margin-bottom:16px">
                <input type="checkbox" name="watermark_enabled" value="1" {!! $get('watermark_enabled') === '1' ? 'checked' : '' !!}>
                <span class="track"></span><span>开启文字水印</span>
            </label>
            <div class="form-row">
                <div class="field">
                    <label class="label">水印文字</label>
                    <input class="input" name="watermark_text" value="{{ $get('watermark_text') }}" placeholder="例如：© example.com">
                </div>
                <div class="field">
                    <label class="label">位置</label>
                    <select class="select" name="watermark_position">
                        <?php
                        $positions = [
                            'top-left' => '左上', 'top-center' => '上中', 'top-right' => '右上',
                            'center' => '居中', 'bottom-left' => '左下', 'bottom-center' => '下中', 'bottom-right' => '右下',
                        ];
                        foreach ($positions as $value => $label): ?>
                            <option value="{{ $value }}" {!! $get('watermark_position', 'bottom-right') === $value ? 'selected' : '' !!}>
                                {{ $label }}                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">透明度 <span class="hint">0-100</span></label>
                    <input class="input" name="watermark_opacity" value="{{ $get('watermark_opacity', '45') }}">
                </div>
                <div class="field">
                    <label class="label">字号 <span class="hint">px</span></label>
                    <input class="input" name="watermark_size" value="{{ $get('watermark_size', '18') }}">
                </div>
            </div>
            <div class="form-help">
                中文水印需要字体文件：<code class="inline">resources/fonts/watermark.ttf</code>；缺少字体时使用内置英文字体。
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>安全与接口</h2></div>
        <div class="card-body">
            <?php
            // 账号自动退出回显：库存的是秒；能整分就按分钟显示，否则按秒显示
            $ttlSec  = (int) $get('session_ttl', '0');
            $ttlVal  = ($ttlSec >= 60 && $ttlSec % 60 === 0) ? (int) ($ttlSec / 60) : $ttlSec;
            $ttlUnit = ($ttlSec >= 60 && $ttlSec % 60 === 0) ? 'min' : 'sec';
            ?>
            <div class="field">
                <label class="label">账号自动退出 <span class="hint">无操作多久强制退出登录，0 为不限制，最大 30 分钟</span></label>
                <div style="display:flex;gap:10px;align-items:center">
                    <input class="input" name="session_ttl_value" type="number" min="0" max="1800" step="1"
                           value="{!! $ttlVal !!}" style="width:140px">
                    <select class="select" name="session_ttl_unit" style="width:110px;height:38px">
                        <option value="sec" {!! $ttlUnit === 'sec' ? 'selected' : '' !!}>秒</option>
                        <option value="min" {!! $ttlUnit === 'min' ? 'selected' : '' !!}>分钟</option>
                    </select>
                    <span class="hint">超时后所有登录账号自动退出，需重新登录</span>
                </div>
            </div>
            <div style="display:flex;gap:26px;flex-wrap:wrap;margin:16px 0">
                <label class="switch">
                    <input type="checkbox" name="hotlink_enabled" value="1" {!! $get('hotlink_enabled') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>开启防盗链</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="api_enabled" value="1" {!! $get('api_enabled') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用 API 接口</span>
                </label>
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="label">防盗链白名单 <span class="hint">逗号分隔域名，留空仅本站可引用</span></label>
                <input class="input" name="hotlink_domains" value="{{ $get('hotlink_domains') }}" placeholder="example.com,blog.example.com">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>广场与公告</h2></div>
        <div class="card-body">
            <div style="display:flex;gap:26px;flex-wrap:wrap;margin-bottom:16px">
                <label class="switch">
                    <input type="checkbox" name="explore_enabled" value="1" {!! $get('explore_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用图片广场</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="user_home_enabled" value="1" {!! $get('user_home_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用用户主页</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="download_enabled" value="1" {!! $get('download_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>允许打包下载</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="notice_enabled" value="1" {!! $get('notice_enabled', '0') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>显示站点公告</span>
                </label>
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="label">公告内容 <span class="hint">显示在广场等公开页顶部</span></label>
                <input class="input" name="site_notice" value="{{ $get('site_notice') }}" placeholder="例如：本站图片永久保存，支持外链引用">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>商城与运营</h2></div>
        <div class="card-body">
            <div style="display:flex;gap:26px;flex-wrap:wrap;margin-bottom:16px">
                <label class="switch">
                    <input type="checkbox" name="shop_enabled" value="1" {!! $get('shop_enabled', '0') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用套餐商城</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="coupon_enabled" value="1" {!! $get('coupon_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用优惠码</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="ticket_enabled" value="1" {!! $get('ticket_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用工单</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="report_enabled" value="1" {!! $get('report_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用内容举报</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="feedback_enabled" value="1" {!! $get('feedback_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用意见反馈</span>
                </label>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">新用户默认套餐 <span class="hint">注册后自动绑定</span></label>
                    <select class="select" name="default_package_id">
                        <option value="0" {!! $get('default_package_id', '1') === '0' ? 'selected' : '' !!}>不自动绑定</option>
                        @foreach ($allPackages as $pkg)
                            <option value="{!! (int) $pkg['id'] !!}" {!! $get('default_package_id', '1') === (string) (int) $pkg['id'] ? 'selected' : '' !!}>{{ (string) $pkg['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="hint" style="margin:6px 0 0">新用户注册后自动获得该套餐的用户组与容量权益，等同于免费开通。</p>
                </div>
                <div class="field">
                    <label class="label">订单超时（分钟）</label>
                    <input class="input" name="order_expire_minutes" value="{{ $get('order_expire_minutes', '30') }}">
                </div>
                <div class="field">
                    <label class="label">货币符号</label>
                    <input class="input" name="currency_symbol" value="{{ $get('currency_symbol', '¥') }}">
                </div>
                <div class="field">
                    <label class="label">分享密码有效期（秒）<span class="hint">按最后一次访问起算，默认 7200 秒（2 小时），最小 60 秒</span></label>
                    <input class="input" name="share_password_ttl" value="{{ $get('share_password_ttl', '7200') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>通知与集成</h2></div>
        <div class="card-body">
            <div style="display:flex;gap:26px;flex-wrap:wrap;margin-bottom:16px">
                <label class="switch">
                    <input type="checkbox" name="mail_enabled" value="1" {!! $get('mail_enabled', '0') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用邮件</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="sms_enabled" value="1" {!! $get('sms_enabled', '0') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用短信</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="oauth_enabled" value="1" {!! $get('oauth_enabled', '0') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用社会化登录</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="cloud_process_enabled" value="1" {!! $get('cloud_process_enabled', '1') === '1' ? 'checked' : '' !!}>
                    <span class="track"></span><span>启用云处理</span>
                </label>
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="label">发件邮箱</label>
                    <input class="input" name="mail_from" value="{{ $get('mail_from') }}" placeholder="noreply@example.com">
                </div>
                <div class="field">
                    <label class="label">发件人名称</label>
                    <input class="input" name="mail_from_name" value="{{ $get('mail_from_name') }}" placeholder="蜗牛图床">
                </div>
                <div class="field">
                    <label class="label">短信签名</label>
                    <input class="input" name="sms_sign" value="{{ $get('sms_sign') }}" placeholder="填写服务商审核通过的签名">
                </div>
                <div class="field">
                    <label class="label">审核不通过处理</label>
                    <select class="select" name="moderation_action">
                        <option value="hold" {!! $get('moderation_action', 'hold') === 'hold' ? 'selected' : '' !!}>挂起待人工复核</option>
                        <option value="reject" {!! $get('moderation_action') === 'reject' ? 'selected' : '' !!}>直接拒绝并删除文件</option>
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top:18px;padding-top:16px;border-top:1px dashed var(--border)">
                <label class="label">测试发信 <span class="hint">保存设置后，用当前启用的邮件配置投递一封测试邮件</span></label>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <input class="input" id="mail-test-to" type="email" placeholder="收件邮箱，例如 you@example.com" style="flex:1;min-width:220px">
                    <button class="btn" type="button" id="mail-test-btn">发送测试邮件</button>
                </div>
                <p class="hint" id="mail-test-result" style="margin:8px 0 0" role="status"></p>
                <p class="hint" style="margin:6px 0 0">
                    当前邮件驱动：{{ ($mailDriverName ?? '') !== '' ? $mailDriverName : '未配置（请先到「驱动器」添加并启用邮件驱动）' }}
                </p>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('mail-test-btn');
                var input = document.getElementById('mail-test-to');
                var out = document.getElementById('mail-test-result');
                if (!btn) { return; }
                btn.addEventListener('click', function () {
                    var to = (input.value || '').trim();
                    if (!to) { out.textContent = '请先填写收件邮箱'; input.focus(); return; }
                    btn.disabled = true; btn.textContent = '发送中…'; out.textContent = '';
                    var fd = new FormData();
                    fd.append('_token', '{{ csrf_token() }}');
                    fd.append('to', to);
                    fetch('{{ url('admin/settings/mail-test') }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            out.textContent = j.message || (j.status ? '已发送' : '发送失败');
                            out.style.color = j.status ? '#16a34a' : '#dc2626';
                            btn.disabled = false; btn.textContent = '发送测试邮件';
                        })
                        .catch(function () {
                            out.textContent = '请求失败，请稍后重试'; out.style.color = '#dc2626';
                            btn.disabled = false; btn.textContent = '发送测试邮件';
                        });
                });
            })();
            </script>
            <p class="hint" style="margin:12px 0 0">邮件、短信、支付与社会化登录的具体参数在「驱动器」里按类型配置，这里只控制开关与默认发件身份。</p>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:6px">
        <button class="btn btn-primary" type="submit" data-once>
            <svg class="ic" style="width:15px;height:15px"><use href="#i-check"/></svg> 保存全部设置
        </button>
    </div>
</form>

{{-- 更新检查 + 更新日志：放在表单外，检查按钮不会误触发表单提交 --}}
<div class="card" id="update-check-card" style="margin-top:18px">
    <div class="card-head">
        <h2>更新检查</h2>
        <div class="spacer"></div>
        <button class="btn" type="button" id="update-check-btn">检查更新</button>
    </div>
    <div class="card-body">
        <div class="update-now">
            当前版本 <span class="update-ver">v{{ $currentVersion }}</span>
            <span id="update-check-result" class="update-result"></span>
        </div>

        <div class="update-log-head">更新日志</div>
        @if (count($changelog) > 0)
            {{-- 只显示最后一次更新，不堆历史版本 --}}
            @php $log = $changelog[0]; @endphp
            <div class="update-log">
                <div class="update-log-item">
                    <div class="update-log-title">
                        <span class="update-log-ver">{{ $log['version'] }}</span>
                        @if ($log['date'] !== '')
                            <span class="update-log-date">{{ $log['date'] }}</span>
                        @endif
                    </div>
                    @if (count($log['items']) > 0)
                        <ul class="update-log-items">
                            @foreach ($log['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @else
            <p class="hint">没有读到更新日志。确认程序包里带有 CHANGELOG.md。</p>
        @endif
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('update-check-btn');
    var out = document.getElementById('update-check-result');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        btn.disabled = true;
        out.textContent = '正在检查…';
        out.className = 'update-result';
        var fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fetch('{{ url('admin/settings/check-update') }}', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var d = (j && j.data) || {};
            if (!d.ok) {
                out.textContent = '连不上 GitHub，稍后再试（不影响使用）';
                out.className = 'update-result update-warn';
            } else if (d.has_update) {
                out.innerHTML = '';
                var a = document.createElement('a');
                a.href = d.url || '#';
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = '发现新版本 v' + d.latest + '，查看更新说明';
                out.appendChild(a);
                out.className = 'update-result update-new';
            } else {
                out.textContent = '已是最新版本';
                out.className = 'update-result update-ok';
            }
        })
        .catch(function () {
            out.textContent = '检查失败，请稍后重试';
            out.className = 'update-result update-warn';
        })
        .finally(function () { btn.disabled = false; });
    });
})();
</script>
