<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$maxSeries = max(1, max($series ?: [1]));
$total = array_sum($series);
$shopOn = (string) ($siteSettings['shop_enabled'] ?? '0') === '1';
$myPackage = null;
if ($shopOn && !empty($me['package_id'])) {
    try {
        $myPackage = \App\Models\Package::find((int) $me['package_id']);
    } catch (\Throwable $e) {
    }
}
// 到期时间的三种状态：永久有效 / 具体到期日 / 没有套餐
$expireText = '不限';
if ($myPackage !== null) {
    $expireAt = trim((string) ($me['package_expired_at'] ?? ''));
    $expireText = $expireAt === '' ? '永久' : $expireAt;
}
?>
@if ($shopOn)
<div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div>
            <div class="hint">我的套餐</div>
            <div style="font-size:16px;font-weight:700;margin-top:4px">{!! $myPackage !== null ? e((string) $myPackage['name']) : '默认套餐' !!}</div>
        </div>
        <div>
            <div class="hint">到期时间</div>
            <div style="font-weight:600;margin-top:4px">{{ $expireText }}</div>
        </div>
        <div class="spacer" style="flex:1"></div>
        <a class="btn btn-primary btn-sm" href="{{ url('shop') }}">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-cart"/></svg> 购买 / 升级套餐
        </a>
        <a class="btn btn-sm" href="{{ url('shop/orders') }}">我的订单</a>
    </div>
</div>
@endif
<div class="stat-grid">
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-image"/></svg> 图片总数</div>
        <div class="stat-value">{!! (int) $stats['images'] !!}</div>
        <div class="stat-sub">近 7 天新增 {!! (int) $total !!} 张</div>
    </div>
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-storage"/></svg> 已用空间</div>
        <div class="stat-value">{{ format_bytes((int) $stats['used']) }}</div>
        <div class="stat-sub">
            {!! (int) $stats['capacity'] > 0 ? '总容量 ' . e(format_bytes((int) $stats['capacity'])) : '不限容量' !!}        </div>
    </div>
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-album"/></svg> 相册</div>
        <div class="stat-value">{!! (int) $stats['albums'] !!}</div>
        <div class="stat-sub"><a href="{{ url('albums') }}">管理相册 →</a></div>
    </div>
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-meter"/></svg> 最近 7 天</div>
        <div class="chart" style="height:56px;padding-top:0">
            @foreach ($series as $date => $count)
                <div class="chart-col" title="{{ $date . ' · ' . $count . ' 张' }}">
                    <div class="chart-bar" style="height: {!! max(4, (int) round($count / $maxSeries * 100)) !!}%"></div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>上传图片</h2>
        <div class="spacer"></div>
        <a class="btn btn-sm btn-ghost" href="{{ url('upload') }}">独立上传页 →</a>
    </div>
    <div class="card-body">
        @include('partials.uploader')
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>最近上传</h2>
        <div class="spacer"></div>
        <a class="btn btn-sm" href="{{ url('images') }}">全部图片</a>
    </div>
    <div class="card-body">
        @if ($recent === [])
            <div class="empty">
                <svg class="ic"><use href="#i-image"/></svg>
                <h3>还没有图片</h3>
                <div>拖一张图片到上传区试试</div>
            </div>
        @else
            <div class="image-grid">
                @foreach ($recent as $image)
                    <div class="image-card" style="cursor:default">
                        <img src="{{ image_thumb($image) }}" data-w="{!! (int) $image['width'] !!}" data-h="{!! (int) $image['height'] !!}" alt="{{ (string) $image['name'] }}"{{ lazy_attr() }}
                             @if ((string) setting('lightbox_enabled', '1') === '1')
                                 data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" title="点击查看大图"
                             @endif
                        >
                        <div class="image-overlay">
                            <div class="image-meta">
                                <strong>{{ (string) $image['name'] }}</strong>
                                {{ format_bytes((int) $image['size']) }}                                @if ((int) $image['width'] > 0)
                                    · {!! (int) $image['width'] !!}×{!! (int) $image['height'] !!}                                @endif
                            </div>
                            <div class="image-ops">
                                @if ((string) setting('lightbox_enabled', '1') === '1')
                                    <button class="op-btn" type="button" data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" data-lightbox-self="1" title="灯箱预览">
                                        <svg class="ic"><use href="#i-expand"/></svg>
                                    </button>
                                @endif

                                <button class="op-btn" type="button" data-copy="{{ (string) $image['url'] }}" title="复制链接">
                                    <svg class="ic"><use href="#i-copy"/></svg>
                                </button>
                                <a class="op-btn" href="{{ (string) $image['url'] }}" target="_blank" rel="noopener" title="打开">
                                    <svg class="ic"><use href="#i-external"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
