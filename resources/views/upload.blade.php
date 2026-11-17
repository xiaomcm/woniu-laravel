<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="card">
    <div class="card-head">
        <h2>上传图片</h2>
        <div class="spacer"></div>
        <span class="badge">单张 ≤ {{ format_bytes($maxSize) }}</span>
        @if ((int) ($maxNum ?? 0) > 0)
            <span class="badge">单次 ≤ {!! (int) $maxNum !!} 张</span>
        @endif
    </div>
    <div class="card-body">
        @include('partials.uploader')
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>上传小贴士</h2>
    </div>
    <div class="card-body tight">
        <div class="kv"><span>相册</span><span>上传前可在下方下拉框选择，归入对应相册</span></div>
        <div class="kv"><span>秒传</span><span>同内容文件会自动复用，不重复占用空间</span></div>
        <div class="kv"><span>API</span><span><code class="inline">POST /api/v1/upload</code>（Bearer Token）</span></div>
        <div class="kv"><span>格式</span><span>{{ $allowed !== '' ? $allowed : 'jpg,png,gif,webp' }}</span></div>
    </div>
</div>
