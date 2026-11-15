<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$owner = \App\Models\User::find((int) $album['user_id']);
?>
<div class="toolbar">
    <div>
        <div class="share-title">{{ (string) $album['name'] }}</div>
        <div class="share-sub">
            {!! (int) $album['image_num'] !!} 张图片
            @if (!empty($album['intro'])) · {{ (string) $album['intro'] }}@endif
            @if ($owner !== null) · 由 {{ (string) $owner['name'] }} 分享@endif
        </div>
    </div>
    <div class="spacer"></div>
    <span class="badge badge-primary">公开相册</span>
</div>

@if ($images === [])
    <div class="card">
        <div class="empty">
            <svg class="ic"><use href="#i-album"/></svg>
            <h3>这个相册还没有公开的图片</h3>
        </div>
    </div>
@else
    <div class="image-grid">
        @foreach ($images as $image)
            <a class="image-card" href="{{ url('i/' . $image['sha1']) }}" target="_blank" rel="noopener">
                <img src="{{ image_thumb($image) }}" data-w="{!! (int) $image['width'] !!}" data-h="{!! (int) $image['height'] !!}" alt="{{ (string) $image['name'] }}"{{ lazy_attr() }}
                     @if ((string) setting('lightbox_enabled', '1') === '1')
                         data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" title="点击查看大图"
                     @endif
                >
                <div class="image-overlay">
                    <div class="image-meta">
                        <strong>{{ (string) $image['name'] }}</strong>
                        {!! (int) $image['width'] > 0 ? (int) $image['width'] . '×' . (int) $image['height'] : '' !!}                    </div>
                </div>
            </a>
        @endforeach
    </div>
    {!! paginate_links((int) $pager['page'], (int) $pager['last_page'], []) !!}@endif
