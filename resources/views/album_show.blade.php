<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="toolbar">
    <a class="btn btn-sm btn-ghost" href="{{ url('albums') }}">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-arrow-left"/></svg> 全部相册
    </a>
    <div style="margin-left:6px">
        <div style="font-weight:650;font-size:15px">{{ (string) $album['name'] }}</div>
        <div style="font-size:12px;color:var(--muted)">
            {!! (int) $album['image_num'] !!} 张图片
            @if (!empty($album['intro'])) · {{ (string) $album['intro'] }}@endif
        </div>
    </div>
    <div class="spacer"></div>
    @if ((int) $album['is_public'] === 1 && !empty($album['key']))
        <a class="btn btn-sm" href="{{ url('s/' . $album['key']) }}" target="_blank" rel="noopener">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-external"/></svg> 查看分享页
        </a>
        <button class="btn btn-sm" type="button" data-copy="{{ url('s/' . $album['key']) }}">复制链接</button>
    @endif
</div>

<div class="card">
    <div class="card-body">
        @if ($images === [])
            <div class="empty">
                <svg class="ic"><use href="#i-image"/></svg>
                <h3>相册还是空的</h3>
                <div>上传图片时选择这个相册，或在图片管理里批量移动过来</div>
            </div>
        @else
            <div class="image-grid">
                @foreach ($images as $image)
                    <div class="image-card" data-url="{{ (string) $image['url'] }}">
                        <img src="{{ image_thumb($image, true) }}" data-w="{!! (int) $image['width'] !!}" data-h="{!! (int) $image['height'] !!}" alt="{{ (string) $image['name'] }}"{{ lazy_attr() }}
                             @if ((string) setting('lightbox_enabled', '1') === '1')
                                 data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" title="点击查看大图"
                             @endif
                        >
                        <div class="image-overlay">
                            <div class="image-meta">
                                <strong>{{ (string) $image['name'] }}</strong>
                                {{ format_bytes((int) $image['size']) }}                            </div>
                            <div class="image-ops">
                                @if ((string) setting('lightbox_enabled', '1') === '1')
                                    <button class="op-btn" type="button" data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" data-lightbox-self="1" title="灯箱预览">
                                        <svg class="ic"><use href="#i-expand"/></svg>
                                    </button>
                                @endif

                                <button class="op-btn" type="button" data-copy="{{ (string) $image['url'] }}" title="复制链接">
                                    <svg class="ic"><use href="#i-link"/></svg>
                                </button>
                                <a class="op-btn" href="{{ (string) $image['url'] }}" target="_blank" rel="noopener" title="打开">
                                    <svg class="ic"><use href="#i-external"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            {!! paginate_links((int) $pager['page'], (int) $pager['last_page'], []) !!}        @endif
    </div>
</div>
