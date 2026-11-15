<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$qr = (string) ($pay['qr'] ?? '');
$codeUrl = (string) ($pay['url'] ?? '');
if ($qr === '' && $codeUrl !== '') {
    // 驱动未下发二维码图片时，用链接内容现场生成，避免页面留白
    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&data=' . rawurlencode($codeUrl);
}
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">扫码支付</h1>
        <p class="explore-sub">请使用对应 App 扫描二维码，支付完成后页面会自动跳转</p>
    </div>
    <a class="btn btn-sm" href="{{ url('shop/orders') }}">我的订单</a>
</div>

<div class="pay-panel" data-order-poll="{{ (string) $order['order_no'] }}"
     data-success-url="{{ url('shop/orders/' . (string) $order['order_no']) }}">
    <div class="pay-qr">
        @if ($qr !== '')
            <img src="{{ $qr }}" alt="支付二维码" width="226" height="226">
        @else
            <div class="pay-qr-empty">
                <svg class="ic" style="width:32px;height:32px"><use href="#i-qr"/></svg>
                <p class="hint">该通道未返回二维码</p>
            </div>
        @endif
        <span class="hint">订单 {{ (string) $order['order_no'] }}</span>
    </div>

    <div class="pay-side">
        <div class="pay-amount">{{ format_money((int) $order['total_cents']) }}</div>
        <div class="hint" style="margin-top:2px">{{ (string) $order['package_name'] }}</div>

        <div class="kv-row"><span class="kv-key">下单时间</span><span class="kv-val">{{ (string) $order['created_at'] }}</span></div>
        <div class="kv-row"><span class="kv-key">支付状态</span><span class="kv-val">{!! badge((string) $order['status_text'], (string) $order['status_tone']) !!}</span></div>
        @if (!empty($order['expire_at']))
            <div class="kv-row"><span class="kv-key">支付截止</span><span class="kv-val">{{ (string) $order['expire_at'] }}</span></div>
        @endif

        <div class="pay-tip">
            <span class="pulse-dot"></span>
            <span>正在等待支付结果，到账后自动跳转</span>
        </div>

        @if ($codeUrl !== '' && strpos($codeUrl, 'http') === 0)
            <a class="btn btn-sm btn-block" href="{{ $codeUrl }}" target="_blank" rel="noopener" style="margin-top:12px">在支付 App 中打开</a>
        @endif
        <a class="btn btn-sm btn-ghost btn-block" href="{{ url('shop/orders/' . (string) $order['order_no']) }}" style="margin-top:8px">已完成支付 / 查看订单</a>

        <p class="hint" style="margin:14px 0 0;line-height:1.75">
            二维码过期请返回套餐页重新下单；同一订单请勿重复支付。
        </p>
    </div>
</div>
