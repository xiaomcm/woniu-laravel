<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pending = (int) $order['status'] === \App\Models\Order::STATUS_PENDING;
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">订单详情</h1>
        <p class="explore-sub mono">{{ (string) $order['order_no'] }}</p>
    </div>
    <a class="btn btn-sm" href="{{ url('shop/orders') }}">返回列表</a>
</div>

<div class="checkout-grid">
    <div class="card">
        <div class="card-head">
            <div class="card-title">订单信息</div>
            <div class="spacer"></div>
            {!! badge((string) $order['status_text'], (string) $order['status_tone']) !!}        </div>
        <div class="card-body">
            <div class="kv-row"><span class="kv-key">套餐</span><span class="kv-val">{{ (string) $order['package_name'] }}</span></div>
            <div class="kv-row"><span class="kv-key">创建时间</span><span class="kv-val">{{ (string) $order['created_at'] }}</span></div>
            @if (!empty($order['expire_at']))
                <div class="kv-row"><span class="kv-key">支付截止</span><span class="kv-val">{{ (string) $order['expire_at'] }}</span></div>
            @endif
            @if (!empty($order['paid_at']))
                <div class="kv-row"><span class="kv-key">支付时间</span><span class="kv-val">{{ (string) $order['paid_at'] }}</span></div>
            @endif
            @if (!empty($order['trade_no']))
                <div class="kv-row"><span class="kv-key">支付流水</span><span class="kv-val mono">{{ (string) $order['trade_no'] }}</span></div>
            @endif
            @if (!empty($order['gateway']))
                <div class="kv-row"><span class="kv-key">支付通道</span><span class="kv-val">{{ (string) $order['gateway'] }}</span></div>
            @endif
        </div>
    </div>

    <div class="card" style="align-self:start">
        <div class="card-head"><div class="card-title">费用</div></div>
        <div class="card-body">
            <div class="summary-row">
                <span class="label">套餐金额</span>
                <span>{{ format_money((int) $order['amount_cents']) }}</span>
            </div>
            @if ((int) $order['discount_cents'] > 0)
                <div class="summary-row">
                    <span class="label">优惠</span>
                    <span style="color:var(--danger)">- {{ format_money((int) $order['discount_cents']) }}</span>
                </div>
            @endif
            <div class="summary-row total">
                <span>实付</span>
                <span>{{ format_money((int) $order['total_cents']) }}</span>
            </div>

            @if ($pending)
                <div data-order-poll="{{ (string) $order['order_no'] }}"
                     data-success-url="{{ url('shop/orders/' . (string) $order['order_no']) }}">
                    <p class="hint" style="margin:14px 0 10px;line-height:1.7">订单尚未支付，页面会自动检测到账状态。</p>
                </div>
                <a class="btn btn-primary btn-block" href="{{ url('shop/checkout/' . (int) $order['package_id']) }}">重新支付</a>
                <a class="btn btn-sm btn-ghost btn-block" href="{{ url('support/tickets/new') }}" style="margin-top:8px">支付遇到问题</a>
            @else
                <a class="btn btn-block" href="{{ url('shop') }}" style="margin-top:14px">继续选购</a>
            @endif
        </div>
    </div>
</div>
