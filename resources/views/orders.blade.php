<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">我的订单</h1>
        <p class="explore-sub">共 {!! count($orders) !!} 笔订单</p>
    </div>
    <a class="btn btn-sm" href="{{ url('shop') }}">购买套餐</a>
</div>

@if ($orders === [])
    <div class="empty">
        <svg class="ic" style="width:32px;height:32px"><use href="#i-cart"/></svg>
        <p>还没有订单</p>
        <a class="btn btn-sm btn-primary" href="{{ url('shop') }}">去看看套餐</a>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>订单号</th>
                <th>套餐</th>
                <th>金额</th>
                <th>状态</th>
                <th>下单时间</th>
                <th style="width:90px"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td class="mono">{{ (string) $order['order_no'] }}</td>
                    <td>{{ (string) $order['package_name'] }}</td>
                    <td>
                        {{ format_money((int) $order['total_cents']) }}                        @if ((int) $order['discount_cents'] > 0)
                            <span class="hint" style="text-decoration:line-through">{{ format_money((int) $order['amount_cents']) }}</span>
                        @endif
                    </td>
                    <td>{!! badge((string) $order['status_text'], (string) $order['status_tone']) !!}</td>
                    <td class="hint">{{ (string) $order['created_at'] }}</td>
                    <td><a class="btn btn-sm btn-ghost" href="{{ url('shop/orders/' . (string) $order['order_no']) }}">详情</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
