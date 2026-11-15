<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$owner = $owner ?? [];
?>
<div class="site-wrap">
    <section class="user-hero">
        <span class="user-hero-avatar">{{ mb_substr((string) ($owner['name'] ?? 'U'), 0, 1) }}</span>
        <div class="user-hero-main">
            <h1 class="explore-title">{{ (string) ($owner['name'] ?? '') }}</h1>
            <p class="explore-sub">
                加入于 {{ substr((string) ($owner['created_at'] ?? ''), 0, 10) }}                <span class="dot">·</span>
                公开作品 {!! (int) $publicCount !!} 张
            </p>
        </div>
    </section>

    @if (empty($images))
        <div class="empty">
            <svg class="ic ic-lg"><use href="#i-image"/></svg>
            <p>这位用户还没有公开的图片</p>
        </div>
    @else
        <div class="gallery gallery-fixed">
            @foreach ($images as $image)
                <a class="gallery-item" href="{{ url('i/' . $image['sha1']) }}">
                    <img src="{{ image_cover($image) }}" width="{!! \App\Services\Cover::W !!}" height="{!! \App\Services\Cover::H !!}" data-w="{!! \App\Services\Cover::W !!}" data-h="{!! \App\Services\Cover::H !!}" alt="{{ (string) $image['name'] }}"{{ lazy_attr() }}
                         @if ((string) setting('lightbox_enabled', '1') === '1')
                             data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" title="点击查看大图"
                         @endif
                    >
                    @if ((string) setting('lightbox_enabled', '1') === '1')
                        <button class="lb-trigger" type="button" data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" data-lightbox-self="1" title="灯箱预览" aria-label="灯箱预览">
                            <svg class="ic"><use href="#i-expand"/></svg>
                        </button>
                    @endif

                    @if (image_lock_badge($image))
                        <span class="gallery-lock" title="需要访问密码">
                            <svg class="ic"><use href="#i-lock"/></svg>
                        </span>
                    @endif
                    <div class="gallery-mask">
                        <span class="gallery-name">{{ (string) $image['name'] }}</span>
                        <span class="gallery-meta">
                            {!! (int) $image['width'] !!}×{!! (int) $image['height'] !!}                            <span class="dot">·</span>
                            <svg class="ic" style="width:12px;height:12px"><use href="#i-eye"/></svg>
                            {!! (int) $image['views'] !!}                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        {!! paginate_links((int) $pager['page'], (int) $pager['last_page'], []) !!}    @endif
</div>
