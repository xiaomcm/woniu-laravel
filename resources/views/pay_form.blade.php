<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

$action = (string) ($pay['url'] ?? '');
$fields = (array) ($pay['payload'] ?? []);
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">跳转支付</h1>
        <p class="explore-sub">正在跳转到支付网关，请勿关闭页面……</p>
    </div>
    <a class="btn btn-sm" href="{{ url('shop/orders') }}">我的订单</a>
</div>

<div class="pay-panel" data-order-poll="{{ (string) $order['order_no'] }}"
     data-success-url="{{ url('shop/orders/' . (string) $order['order_no']) }}">
    <div class="pay-side" style="max-width:560px">
        <div class="pay-amount">{{ format_money((int) $order['total_cents']) }}</div>
        <div class="hint" style="margin-top:2px">{{ (string) $order['package_name'] }}</div>
        <div class="pay-tip" style="margin-top:14px">
            <span class="pulse-dot"></span>
            <span>正在安全跳转至支付网关</span>
        </div>
        <a class="btn btn-sm btn-ghost btn-block" href="{{ url('shop/orders/' . (string) $order['order_no']) }}" style="margin-top:14px">已完成支付 / 查看订单</a>
    </div>
</div>

<form id="unionpay-form" action="{{ $action }}" method="post" style="position:absolute;left:-9999px">
    @foreach ($fields as $k => $v)
        <input type="hidden" name="{{ e((string) $k) }}" value="{{ e((string) $v) }}">
    @endforeach
</form>

<script>
    window.addEventListener('load', function () {
        var f = document.getElementById('unionpay-form');
        if (f) { f.submit(); }
    });
    // 兜底：若 load 已触发也再提交一次
    setTimeout(function () {
        var f = document.getElementById('unionpay-form');
        if (f && !f.dataset.submitted) { f.dataset.submitted = '1'; f.submit(); }
    }, 800);
</script>
