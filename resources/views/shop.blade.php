<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 到期时间：永久套餐（duration_days = 0）到期时间是 NULL，显示「永久」
 */
$expireText = '不限';
if ($current !== null) {
    $expireRaw = trim((string) ($expireAt ?? ''));
    $expireText = $expireRaw === '' ? '永久' : $expireRaw;
}
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">套餐与扩容</h1>
        <p class="explore-sub">升级后可获得更大的存储空间、更高的单文件上限与更快的响应</p>
    </div>
    @if ($user !== null)
        <a class="btn btn-sm" href="{{ url('shop/orders') }}">我的订单</a>
    @endif
</div>

@if ($current !== null)
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div>
                <div class="hint">当前套餐</div>
                <div style="font-size:16px;font-weight:700;margin-top:4px">{{ (string) $current['name'] }}</div>
            </div>
            <div class="spacer" style="flex:1"></div>
            <div>
                <div class="hint">到期时间</div>
                <div style="font-weight:600;margin-top:4px">{{ $expireText }}</div>
            </div>
        </div>
    </div>
@endif

<div class="plan-grid">
    @foreach ($packages as $index => $package)
        <?php
        $featured = (int) $package['price_cents'] > 0 && $index === 1;
        // 限购：purchase_limit = 0 表示不限购；N 表示每个用户最多购买 N 次
        $limit = (int) ($package['purchase_limit'] ?? 0);
        $boughtCount = (int) ($bought[(int) $package['id']] ?? 0);
        $reached = $limit > 0 && $boughtCount >= $limit;
        // 用户组权益读套餐绑定的组（买后真正生效的配置），不读套餐自身那两个摆设字段
        $group = $groupLimits[(int) ($package['group_id'] ?? 0)]
            ?? ['name' => '', 'max_file_size' => 0, 'daily_limit' => 0, 'benefits' => []];
        ?>
        <div class="plan-card {!! $featured ? 'featured' : '' !!}">
            <div class="plan-name">{{ (string) $package['name'] }}</div>
            <div class="plan-price">
                <span class="num">{!! (int) $package['price_cents'] === 0 ? '免费' : e(format_money((int) $package['price_cents'])) !!}</span>
                @if ((int) $package['price_cents'] > 0)
                    <span class="unit">/ {!! (int) $package['duration_days'] > 0 ? (int) $package['duration_days'] . ' 天' : '永久' !!}</span>
                @endif
            </div>
            <div class="plan-desc">{{ (string) ($package['description'] ?? '') }}</div>
            <ul class="plan-features">
                @if ((int) $package['capacity'] > 0)
                    <li>{{ format_bytes((int) $package['capacity']) }} 存储空间</li>
                @else
                    <li>存储空间不限</li>
                @endif
                @if ($limit > 0)
                    <li>每人限购 {!! $limit !!} 次</li>
                    @if ($user !== null)
                        <li>已购买 {!! $boughtCount !!} / {!! $limit !!} 次</li>
                    @endif
                @endif
                @foreach ($package['features_list'] as $feature)
                    <li>{{ $feature }}</li>
                @endforeach
            </ul>
            {{-- 用户组权益：买套餐后升级到的用户组，实际生效的那套配置 --}}
            @if ($group['name'] !== '' && count($group['benefits']) > 0)
                <div class="plan-group-benefits">
                    <div class="plan-group-title">购买后升级「{{ $group['name'] }}」，享 {!! count($group['benefits']) !!} 项权益</div>
                    <ul class="plan-features">
                        @foreach ($group['benefits'] as $benefit)
                            <li>{{ $benefit }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="spacer" style="flex:1"></div>
            @if ($user === null)
                <a class="btn btn-primary btn-block" href="{{ url('login') }}">登录后购买</a>
            @elseif ($reached)
                <button class="btn btn-block" type="button" disabled>已达限购上限</button>
            @elseif ((int) $package['price_cents'] === 0)
                <a class="btn btn-block" href="{{ url('shop/checkout/' . (int) $package['id']) }}">立即领取</a>
            @else
                <a class="btn btn-primary btn-block" href="{{ url('shop/checkout/' . (int) $package['id']) }}">立即购买</a>
            @endif
        </div>
    @endforeach
</div>
