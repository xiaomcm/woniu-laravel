<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$paid = $order !== null && (int) $order['status'] === \App\Models\Order::STATUS_PAID;
?>
<div class="result-page">
    <div class="result-badge {!! $paid ? 'ok' : 'wait' !!}">
        <svg class="ic" style="width:30px;height:30px"><use href="#i-{!! $paid ? 'check' : 'clock' !!}"/></svg>
    </div>

    @if ($order === null)
        <h1 class="result-h1">未找到订单</h1>
        <p class="result-p">订单号缺失或不存在。如果已经付款，请提交工单并提供支付流水号。</p>
        <div class="result-cta">
            <a class="btn btn-primary" href="{{ url('shop') }}">返回套餐</a>
            <a class="btn" href="{{ url('support/tickets/new') }}">提交工单</a>
        </div>
    @elseif ($paid)
        <h1 class="result-h1">支付成功</h1>
        <p class="result-p">{{ (string) $order['package_name'] }} 已开通，配额已经生效。</p>
        <div class="result-panel">
            <div class="kv-row"><span class="kv-key">订单号</span><span class="kv-val mono">{{ (string) $order['order_no'] }}</span></div>
            <div class="kv-row"><span class="kv-key">支付金额</span><span class="kv-val">{{ format_money((int) $order['total_cents']) }}</span></div>
            @if ($tradeNo !== '')
                <div class="kv-row"><span class="kv-key">支付流水</span><span class="kv-val mono">{{ $tradeNo }}</span></div>
            @endif
            <div class="kv-row"><span class="kv-key">完成时间</span><span class="kv-val">{{ (string) ($order['paid_at'] ?? '-') }}</span></div>
        </div>
        <div class="result-cta">
            <a class="btn btn-primary" href="{{ url('dashboard') }}">进入控制台</a>
            <a class="btn" href="{{ url('shop/orders') }}">查看订单</a>
        </div>
    @else
        <h1 class="result-h1">等待支付确认</h1>
        <p class="result-p">支付结果由通道异步回调确认，通常几秒内到账。</p>
        <div class="result-panel" data-order-poll="{{ (string) $order['order_no'] }}"
             data-success-url="{{ url('shop/orders/' . (string) $order['order_no']) }}">
            <div class="kv-row"><span class="kv-key">订单号</span><span class="kv-val mono">{{ (string) $order['order_no'] }}</span></div>
            <div class="kv-row"><span class="kv-key">应付金额</span><span class="kv-val">{{ format_money((int) $order['total_cents']) }}</span></div>
            <div class="kv-row"><span class="kv-key">当前状态</span><span class="kv-val">{!! badge((string) $order['status_text'], (string) $order['status_tone']) !!}</span></div>
        </div>
        <div class="result-cta">
            <a class="btn btn-primary" href="{{ url('shop/orders/' . (string) $order['order_no']) }}">刷新订单</a>
            <a class="btn" href="{{ url('support/tickets/new') }}">遇到问题</a>
        </div>
    @endif
</div>
