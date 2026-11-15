<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$sortLabels = ['newest' => '最新', 'hot' => '最热', 'oldest' => '最早'];
$query = array_filter([
    'q'    => $filters['keyword'] ?? '',
    'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
    'tag'  => $filters['tag'] ?? '',
], static fn($v) => $v !== '' && $v !== null);
?>
<div class="site-wrap">
    <section class="explore-head">
        <div class="explore-head-main">
            <h1 class="explore-title">发现</h1>
            <p class="explore-sub">
                来自本站用户的公开作品
                <span class="dot">·</span>
                共 {!! (int) $total !!} 张
                @if (!empty($filters['tag']))
                    <span class="dot">·</span> 标签「{{ (string) $filters['tag'] }}」
                @endif
            </p>
        </div>
        <form class="explore-search" method="get" action="{{ url('explore') }}">
            @if (!empty($filters['tag']))
                <input type="hidden" name="tag" value="{{ (string) $filters['tag'] }}">
            @endif
            <div class="search-box">
                <svg class="ic"><use href="#i-search"/></svg>
                <input class="search-input" type="search" name="q" value="{{ (string) $filters['keyword'] }}" placeholder="搜索图片名称、标签…">
            </div>
            <button class="btn btn-primary" type="submit">搜索</button>
        </form>
    </section>

    <div class="explore-bar">
        <div class="tabs tabs-inline">
            @foreach ($sortLabels as $key => $label)
                <?php
                $q = $query;
                $q['sort'] = $key;
                $href = url('explore') . '?' . http_build_query($q);
                ?>
                <a class="tab {!! ($filters['sort'] ?? 'newest') === $key ? 'active' : '' !!}" href="{{ $href }}">{{ $label }}</a>
            @endforeach
        </div>
        @if (!empty($tags))
            <div class="tag-row">
                @if (!empty($filters['tag']))
                    <a class="tag-chip active" href="{{ url('explore') . '?' . http_build_query(array_filter(['q' => $filters['keyword'], 'sort' => $query['sort'] ?? ''])) }}">
                        {{ (string) $filters['tag'] }}                        <svg class="ic" style="width:12px;height:12px"><use href="#i-close"/></svg>
                    </a>
                @endif
                @foreach ($tags as $tag => $count)
                    <?php if ((string) $tag === (string) ($filters['tag'] ?? '')) { continue; } ?>
                    <a class="tag-chip" href="{{ url('explore') . '?' . http_build_query(['tag' => $tag]) }}">
                        {{ (string) $tag }}<span class="tag-count">{!! (int) $count !!}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if (empty($images))
        <div class="empty">
            <svg class="ic ic-lg"><use href="#i-compass"/></svg>
            <p>这里还没有公开图片</p>
            @if (!empty($me))
                <a class="btn btn-primary btn-sm" href="{{ url('upload') }}">上传第一张</a>
            @endif
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
                            {!! (int) $image['width'] !!}×{!! (int) $image['height'] !!}                            <span class="dot">·</span>{{ format_bytes((int) $image['size']) }}                            <span class="dot">·</span>
                            <svg class="ic" style="width:12px;height:12px"><use href="#i-eye"/></svg>
                            {!! (int) $image['views'] !!}                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        {!! paginate_links((int) $pager['page'], (int) $pager['last_page'], $query) !!}    @endif
</div>
