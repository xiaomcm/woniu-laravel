<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * API 用法说明页。所有示例里的 {{ $apiBase }} 来自控制器，跟着站点地址走，
 * 不用手改域名；两个开关的实时状态也由控制器传入，关了就在顶部提示。
 */

// 接口清单：分组 => [方法, 路径, 说明]
$groups = [
    '账户' => [
        ['GET',  '/me',            '当前账号信息'],
        ['GET',  '/me/quota',      '容量与用量'],
        ['GET',  '/profile',       '个人资料'],
        ['POST', '/profile',       '修改个人资料'],
        ['POST', '/me/password',   '修改密码'],
        ['POST', '/me/email',      '修改邮箱'],
    ],
    '上传与图片' => [
        ['POST', '/upload',                  '上传图片（file / files / url）'],
        ['GET',  '/images',                  '图片列表（分页）'],
        ['GET',  '/images/search',           '搜索图片'],
        ['GET',  '/images/random',           '随机图片'],
        ['GET',  '/images/{id}',             '图片详情'],
        ['GET',  '/images/{id}/meta',        '图片元数据'],
        ['GET',  '/images/{id}/download',    '下载图片'],
        ['POST', '/images/{id}',             '修改图片信息'],
        ['DELETE','/images/{id}',            '删除图片（进回收站）'],
        ['POST', '/images/batch-delete',     '批量删除'],
        ['POST', '/images/batch-move',       '批量移动到相册'],
    ],
    '相册' => [
        ['GET',    '/albums',               '相册列表'],
        ['POST',   '/albums',               '新建相册'],
        ['GET',    '/albums/{id}',          '相册详情'],
        ['POST',   '/albums/{id}',          '修改相册'],
        ['DELETE', '/albums/{id}',          '删除相册'],
        ['GET',    '/albums/{id}/images',   '相册内图片'],
        ['GET',    '/albums/share/{key}',   '分享相册（无需令牌）'],
    ],
    '令牌' => [
        ['GET',    '/tokens',              '令牌列表'],
        ['POST',   '/tokens',              '新建令牌'],
        ['POST',   '/tokens/{id}/rename',  '重命名令牌'],
        ['POST',   '/tokens/{id}/revoke',  '吊销令牌'],
        ['DELETE', '/tokens/{id}',         '删除令牌'],
    ],
    '套餐与订单' => [
        ['GET',  '/packages',          '套餐列表'],
        ['GET',  '/packages/active',   '当前生效套餐'],
        ['POST', '/coupon/check',      '查询优惠码'],
        ['GET',  '/orders',            '订单列表'],
        ['POST', '/orders',            '下单'],
        ['GET',  '/orders/{no}/pay',   '获取支付信息'],
    ],
    '其它' => [
        ['GET',  '/site/info',     '站点信息（无需令牌）'],
        ['GET',  '/health',        '健康检查（无需令牌）'],
        ['GET',  '/stats',         '统计概览'],
        ['GET',  '/logs',          '操作日志'],
        ['GET',  '/tickets',       '工单列表'],
        ['POST', '/feedback',      '提交反馈'],
        ['GET',  '/admin/stats',   '管理端统计（管理员）'],
    ],
];
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">API 文档</h1>
        <p class="explore-sub">用令牌调用接口上传图片、管理相册和图库。全部接口返回 JSON，路径前缀是 <code class="inline">{{ $apiBase }}</code></p>
    </div>
</div>

@if (!$siteOn)
    <div class="alert alert-warning" style="margin-bottom:18px">
        本站已由管理员关闭 API 接口，当前所有接口都调不通。请联系站主开启后再使用。
    </div>
@endif
@if ($loggedIn && !$userApiOn)
    <div class="alert alert-warning" style="margin-bottom:18px">
        你已在个人设置里关闭自己的 API 接口。到 <a href="{{ url('settings') }}">个人设置</a> 打开「启用我的 API 接口」即可恢复，令牌不会丢失。
    </div>
@endif

<div class="card" style="margin-bottom:18px">
    <div class="card-head"><h2>一、先拿令牌</h2></div>
    <div class="card-body">
        <p>登录后在 <a href="{{ url('settings') }}">个人设置 → API 令牌</a> 里点「新建令牌」。令牌以 <code class="inline">wn_</code> 开头，<strong>只在创建那一刻显示一次</strong>，之后看不到完整值——创建时立刻复制保存。</p>
        <p style="margin-top:8px">令牌属于你的账号，共用你的空间和套餐额度。不需要了就在设置页删掉，删除立即生效。</p>
        @if (!$loggedIn)
            <p style="margin-top:8px"><a class="btn btn-sm btn-primary" href="{{ url('login') }}?redirect={{ urlencode('settings') }}">登录后创建令牌</a></p>
        @endif
    </div>
</div>

<div class="card" style="margin-bottom:18px">
    <div class="card-head"><h2>二、带上令牌</h2></div>
    <div class="card-body">
        <p>在请求头里加 <code class="inline">Authorization: Bearer 你的令牌</code>。已登录的浏览器会话也能直接调接口，不必带令牌。</p>
        <pre class="code-block" style="margin-top:12px"><code>curl -H "Authorization: Bearer wn_你的令牌" \
  "{{ $apiBase }}/me"</code></pre>
        <p class="form-help" style="margin-top:10px">令牌无效、过期，或账号被停用，返回 HTTP 401。</p>
    </div>
</div>

<div class="card" style="margin-bottom:18px">
    <div class="card-head"><h2>三、返回格式</h2></div>
    <div class="card-body">
        <p>所有接口都返回 JSON，三个字段固定：</p>
        <pre class="code-block" style="margin-top:12px"><code>{
  "status": true,
  "message": "",
  "data": { ... }
}</code></pre>
        <p class="form-help" style="margin-top:10px">
            <code class="inline">status</code> 为 <code class="inline">true</code> 表示成功；失败时为 <code class="inline">false</code>，原因在 <code class="inline">message</code>。
            一般失败返回 HTTP 422，鉴权失败 401，被开关拦住 403。
        </p>
    </div>
</div>

<div class="card" style="margin-bottom:18px">
    <div class="card-head"><h2>四、上传图片</h2></div>
    <div class="card-body">
        <p>把文件放在 <code class="inline">file</code> 字段（多个文件用 <code class="inline">files</code>），也可以传 <code class="inline">url</code> 让服务器自己拉取。可选 <code class="inline">album_id</code>、<code class="inline">storage_id</code>。</p>
        <pre class="code-block" style="margin-top:12px"><code># 本地文件
curl -X POST "{{ $apiBase }}/upload" \
  -H "Authorization: Bearer wn_你的令牌" \
  -F "file=@/path/to/a.png"

# 从网址拉取
curl -X POST "{{ $apiBase }}/upload" \
  -H "Authorization: Bearer wn_你的令牌" \
  -d "url=https://example.com/a.png"</code></pre>
        <p style="margin-top:12px">成功时 <code class="inline">data</code> 是图片对象，<code class="inline">links</code> 字段里就是各种尺寸的直链：</p>
        <pre class="code-block" style="margin-top:8px"><code>{
  "status": true,
  "message": "上传成功",
  "data": {
    "id": 12,
    "name": "a.png",
    "size": 102400,
    "mimetype": "image/png",
    "width": 800,
    "height": 600,
    "sha1": "...",
    "links": { "url": "...", "thumbnail": "...", "preview": "..." }
  }
}</code></pre>
        <p class="form-help" style="margin-top:10px">
            上传受站点的文件大小、允许格式、容量和套餐限制约束，和管理页面上传是同一套规则。
        </p>
    </div>
</div>

<div class="card" style="margin-bottom:18px">
    <div class="card-head"><h2>五、取图片列表</h2></div>
    <div class="card-body">
        <pre class="code-block"><code>curl "{{ $apiBase }}/images?page=1&amp;per_page=20&amp;keyword=风景" \
  -H "Authorization: Bearer wn_你的令牌"</code></pre>
        <p class="form-help" style="margin-top:10px">
            <code class="inline">per_page</code> 最大 100。<code class="inline">data</code> 里是 <code class="inline">data</code>（图片数组）加 <code class="inline">current_page</code>、<code class="inline">last_page</code>、<code class="inline">total</code> 等分页字段。
        </p>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>六、接口清单</h2></div>
    <div class="card-body">
        <p class="form-help" style="margin-bottom:14px">下面是最常用的一部分。完整清单见程序里的 <code class="inline">routes/api.php</code>（共 102 条），接口实现见 <code class="inline">app/Http/Controllers/ApiController.php</code>。</p>
        @foreach ($groups as $name => $items)
            <h3 style="font-size:15px;font-weight:500;margin:18px 0 8px">{{ $name }}</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th style="width:90px">方法</th><th>路径</th><th>说明</th></tr></thead>
                    <tbody>
                    @foreach ($items as [$method, $path, $desc])
                        <tr>
                            <td><code class="inline">{{ $method }}</code></td>
                            <td><code class="inline">{{ $apiBase }}{{ $path }}</code></td>
                            <td>{{ $desc }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
