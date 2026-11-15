<?php
/**
 * 蜗牛图床 WONIU — 科技风落地页
 */
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
$tagline = (string) ($siteSettings['site_description'] ?? '极速、稳定、优雅的图片托管服务');
$loggedIn = !empty($me);
$showcase = $showcase ?? [];
$statImages = (int) ($stats['images'] ?? 0);
$statPublic = (int) ($stats['public'] ?? 0);
$statUsers = (int) ($stats['users'] ?? 0);
$statStorages = (int) ($stats['storages'] ?? 0);

$heroThumbs = array_slice($showcase, 0, 3);
$features = [
    ['i-cloud',   '极速上传',   '拖拽、粘贴、批量与 URL 拉取，多文件并发上传，完成即得直链，无需等待。'],
    ['i-storage', '多存储驱动', '本地、S3、OSS、COS、七牛、又拍云、FTP、WebDAV、SFTP、MinIO 自由切换，扩容迁移无感知。'],
    ['i-link',    '一键外链',   '直链、Markdown、HTML、BBCode 多格式一键复制，写博客、发论坛随手就用。'],
    ['i-album',   '相册管理',   '相册分组、批量操作、密码保护与独立分享页，让图片资产井井有条。'],
    ['i-shield',  '内容审核',   '腾讯云 / 百度云 / NSFW.js 多引擎审核，防盗链、访问密码与回收站，安心托管。'],
    ['i-sliders', '开放 API',   '100+ 标准接口与 Token 鉴权，PicGo、浏览器扩展、CI 脚本轻松接入。'],
];
$steps = [
    ['01', '注册账号', '创建专属图床空间，立即获得免费存储额度与 API 配额。'],
    ['02', '上传图片', '拖拽或粘贴图片，自动生成缩略图与多种外链格式。'],
    ['03', '分发外链', '复制链接发到任意平台，全球多节点极速访问。'],
];
?>

<!-- 首屏 -->
<section class="lx-hero">
    <div class="lx-container lx-hero-grid">
        <div class="lx-hero-copy lx-reveal">
            <span class="lx-kicker"><span class="dot"></span> 开源免费 · 多存储 · 多审核图床</span>
            <h1>极速、优雅的<br><span class="grad">下一代开源图床</span></h1>
            <p class="lx-lead">{{ $tagline }}。把图片交给 {{ $siteName }}，用拖拽的速度、霓虹的体验，重新定义你的图床工作流。</p>

            <div class="lx-hero-cta lx-cta-row">
                @if ($loggedIn)
                    <a class="lx-btn lx-btn-primary" href="{{ url('dashboard') }}"><svg class="ic"><use href="#i-upload"/></svg> 进入控制台</a>
                    <a class="lx-btn lx-btn-ghost" href="{{ url('explore') }}"><svg class="ic"><use href="#i-compass"/></svg> 发现广场</a>
                @else
                    <a class="lx-btn lx-btn-primary" href="{{ url('register') }}"><svg class="ic"><use href="#i-upload"/></svg> 免费开始使用</a>
                    <a class="lx-btn lx-btn-ghost" href="{{ url('explore') }}"><svg class="ic"><use href="#i-compass"/></svg> 逛逛发现广场</a>
                @endif
            </div>

            <div class="lx-hero-note">
                <svg class="ic"><use href="#i-check"/></svg>
                无需信用卡 · 注册即用 · 支持 API 上传与 Webhook 回调
            </div>

            <div class="lx-hero-tags">
                <span class="lx-tag">存储 <b>10+</b></span>
                <span class="lx-tag">审核 <b>3 引擎</b></span>
                <span class="lx-tag">支付 <b>PayPal / 银联</b></span>
                <span class="lx-tag">API <b>100+</b></span>
            </div>
        </div>

        <!-- 上传面板 -->
        <div class="lx-hero-visual lx-reveal">
            <div class="lx-panel">
                <div class="lx-panel-bar">
                    <span class="lx-dot" style="background:#ff5f57"></span>
                    <span class="lx-dot" style="background:#febc2e"></span>
                    <span class="lx-dot" style="background:#28c840"></span>
                    <span class="lx-panel-title">{{ $siteName }} · upload.sh</span>
                </div>
                <div class="lx-panel-body">
                    <div class="lx-drop">
                        <svg class="ic"><use href="#i-upload"/></svg>
                        <b>拖拽图片到此处</b>
                        <span>支持 JPG / PNG / GIF / WebP · 单张最大 20MB</span>
                    </div>
                    <div class="lx-thumbs">
                        @foreach ($heroThumbs as $img)
                            <div class="lx-thumb"><img src="{{ image_cover($img) }}" alt=""{{ lazy_attr() }}></div>
                        @endforeach
                        @for ($i = count($heroThumbs); $i < 3; $i++)
                            <div class="lx-thumb empty"><svg class="ic"><use href="#i-image"/></svg></div>
                        @endfor
                    </div>
                    <div class="lx-progress">
                        <div class="lx-progress-row"><svg class="ic"><use href="#i-check"/></svg> 上传成功 · 外链已复制到剪贴板</div>
                        <div class="lx-bar"><i></i></div>
                    </div>
                    <div class="lx-linkout"><svg class="ic"><use href="#i-link"/></svg> https://{{ request()->getHost() }}/i/8f3a…png</div>
                </div>
            </div>
            {{-- 卖点徽章：走正常文档流，不再绝对定位遮挡面板内容 --}}
            <div class="lx-badges">
                <span class="lx-badge"><svg class="ic"><use href="#i-upload"/></svg> 多存储驱动 · 无感扩容</span>
                <span class="lx-badge"><svg class="ic"><use href="#i-meter"/></svg> 边缘节点 · 毫秒响应</span>
            </div>
        </div>
    </div>
</section>

<!-- 统计 -->
<section class="lx-container">
    <div class="lx-stats lx-reveal">
        <div class="lx-stat"><div class="lx-stat-num" data-count="{{ $statImages }}">{!! number_format($statImages) !!}</div><div class="lx-stat-label">已收录图片</div></div>
        <div class="lx-stat"><div class="lx-stat-num" data-count="{{ $statPublic }}">{!! number_format($statPublic) !!}</div><div class="lx-stat-label">公开分享</div></div>
        <div class="lx-stat"><div class="lx-stat-num" data-count="{{ $statUsers }}">{!! number_format($statUsers) !!}</div><div class="lx-stat-label">注册用户</div></div>
        <div class="lx-stat"><div class="lx-stat-num" data-count="{{ $statStorages }}">{!! number_format($statStorages) !!}</div><div class="lx-stat-label">存储策略</div></div>
    </div>
</section>

<!-- 特性 -->
<section class="lx-section">
    <div class="lx-container">
        <div class="lx-sec-head lx-reveal">
            <span class="lx-kicker"><span class="dot"></span> FEATURES</span>
            <h2>为什么选择 {{ $siteName }}</h2>
            <p>从上传到分发，每一步都为速度与体验而设计</p>
        </div>
        <div class="lx-feat-grid">
            @foreach ($features as $f)
                <div class="lx-feat lx-reveal">
                    <div class="lx-feat-ic"><svg class="ic"><use href="#i-{{ $f[0] }}"/></svg></div>
                    <h3>{{ $f[1] }}</h3>
                    <p>{{ $f[2] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

@if ($showcase !== [])
<!-- 作品展示 -->
<section class="lx-section" style="padding-top:0">
    <div class="lx-container">
        <div class="lx-sec-head lx-reveal">
            <span class="lx-kicker"><span class="dot"></span> GALLERY</span>
            <h2>看看大家在分享什么</h2>
            <p>来自站内用户的公开图片，随时可以探索</p>
        </div>
        <div class="lx-show">
            @foreach (array_slice($showcase, 0, 8) as $img)
                <a class="lx-show-card lx-reveal" href="{{ url('i/' . (string) $img['sha1']) }}">
                    <img src="{{ image_cover($img) }}" alt="{{ (string) $img['name'] }}"{{ lazy_attr() }}
                         @if ((string) setting('lightbox_enabled', '1') === '1')
                             data-lightbox-src="{{ (string) $img['url'] }}" data-lightbox-caption="{{ (string) $img['name'] }}" title="点击查看大图"
                         @endif
                    >
                    @if ((string) setting('lightbox_enabled', '1') === '1')
                        <button class="lb-trigger" type="button" data-lightbox-src="{{ (string) $img['url'] }}" data-lightbox-caption="{{ (string) $img['name'] }}" data-lightbox-self="1" title="灯箱预览" aria-label="灯箱预览">
                            <svg class="ic"><use href="#i-expand"/></svg>
                        </button>
                    @endif
                    <div class="lk"><span><svg class="ic"><use href="#i-expand"/></svg> {{ (string) $img['name'] }}</span></div>
                </a>
            @endforeach
        </div>
        <div class="lx-show-more lx-reveal">
            <a class="lx-btn lx-btn-ghost" href="{{ url('explore') }}">进入发现广场 →</a>
        </div>
    </div>
</section>
@endif

<!-- 三步 -->
<section class="lx-section" style="padding-top:0">
    <div class="lx-container">
        <div class="lx-sec-head lx-reveal">
            <span class="lx-kicker"><span class="dot"></span> GET STARTED</span>
            <h2>三步开始</h2>
            <p>注册、上传、分享，一分钟搞定</p>
        </div>
        <div class="lx-steps">
            @foreach ($steps as $s)
                <div class="lx-step lx-reveal">
                    <div class="lx-step-num">{{ $s[0] }}</div>
                    <h3>{{ $s[1] }}</h3>
                    <p>{{ $s[2] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- API 双栏 -->
<section class="lx-section" id="api" style="padding-top:0;scroll-margin-top:80px">
    <div class="lx-container lx-api">
        <div class="lx-api-text lx-reveal">
            <span class="lx-kicker"><span class="dot"></span> DEVELOPER API</span>
            <h2>开放生态<br>一万种可能</h2>
            <p>标准文件管理接口，把 {{ $siteName }} 融进你的工作流：博客图床、写作工具、自动化脚本，一个 Token 全搞定。</p>
            <div class="lx-cta-row">
                @if ($loggedIn)
                    <a class="lx-btn lx-btn-primary" href="{{ url('settings') }}">获取 API Token</a>
                @else
                    <a class="lx-btn lx-btn-primary" href="{{ url('register') }}">立即注册</a>
                @endif
                <button class="lx-btn lx-btn-ghost" type="button" data-copy="POST {{ url('api/v1/upload') }}">复制接口地址</button>
            </div>
        </div>
        <div class="lx-code lx-reveal">
            <div class="lx-code-bar">
                <span class="lx-dot" style="background:#ff5f57"></span>
                <span class="lx-dot" style="background:#febc2e"></span>
                <span class="lx-dot" style="background:#28c840"></span>
                <span class="tt">upload.sh</span>
            </div>
<pre><span class="c-cmd">curl</span> -X POST <span class="c-str">"{{ url('api/v1/upload') }}"</span> \
  -H <span class="c-str">"Authorization: Bearer &lt;token&gt;"</span> \
  -F <span class="c-str">"file=@photo.png"</span>

<span class="c-cmt"># 返回</span>
{
  <span class="c-key">"status"</span>: <span class="c-num">true</span>,
  <span class="c-key">"data"</span>: {
    <span class="c-key">"links"</span>: {
      <span class="c-key">"url"</span>: <span class="c-str">"https://…/photo.png"</span>,
      <span class="c-key">"markdown"</span>: <span class="c-str">"![](https://…/photo.png)"</span>
    }
  }
}</pre>
        </div>
    </div>
</section>

<!-- 底部行动条 -->
<section class="lx-section" style="padding-top:0">
    <div class="lx-container">
        <div class="lx-cta-band lx-reveal">
            <h2>现在开始，免费托管你的图片</h2>
            <p>注册即用，无需信用卡。让你的图片跑得更快、管理更省心。</p>
            <div class="lx-cta-row">
                @if ($loggedIn)
                    <a class="lx-btn lx-btn-white" href="{{ url('dashboard') }}">进入控制台</a>
                @else
                    <a class="lx-btn lx-btn-white" href="{{ url('register') }}">免费注册</a>
                    <a class="lx-btn lx-btn-outline" href="{{ url('login') }}">已有账号，去登录</a>
                @endif
            </div>
        </div>
    </div>
</section>
