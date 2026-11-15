<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="mini-grid" style="margin-bottom:18px">
    <div class="mini-stat">
        <div class="mini-stat-label">累计收入</div>
        <div class="mini-stat-value">{{ format_money((int) $revenue) }}</div>
    </div>
    <div class="mini-stat">
        <div class="mini-stat-label">已支付订单</div>
        <div class="mini-stat-value">{!! (int) $paidCount !!}</div>
    </div>
    <div class="mini-stat">
        <div class="mini-stat-label">筛选结果</div>
        <div class="mini-stat-value">{!! (int) $pager['total'] !!}</div>
    </div>
</div>

<div class="toolbar">
    <a class="btn btn-sm" href="{{ url('admin/shop/packages') }}">套餐</a>
    <a class="btn btn-sm" href="{{ url('admin/shop/coupons') }}">优惠券</a>
    <div class="spacer"></div>
    <form method="get" action="{{ url('admin/shop/orders') }}" class="toolbar" style="margin:0">
        <select class="select" name="status" style="height:34px;width:auto">
            <option value="">全部状态</option>
            @foreach ([0 => '待支付', 1 => '已支付', 2 => '已取消', 3 => '已退款'] as $val => $label)
                <option value="{!! $val !!}" {!! (string) $status === (string) $val ? 'selected' : '' !!}>{{ $label }}</option>
            @endforeach
        </select>
        <input class="input" name="keyword" value="{{ $keyword }}" placeholder="订单号 / 用户名" style="height:34px;width:190px">
        <button class="btn btn-sm" type="submit">查询</button>
    </form>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>订单号</th><th>用户</th><th>套餐</th><th>金额</th><th>通道</th><th>状态</th><th>时间</th><th></th>
                </tr>
                </thead>
                <tbody>
                @if ($orders === [])
                    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:34px">没有订单</td></tr>
                @endif
                @foreach ($orders as $order)
                    <tr>
                        <td><span class="order-no">{{ (string) $order['order_no'] }}</span></td>
                        <td>
                            <div>{{ (string) ($order['user_name'] ?? '已删除') }}</div>
                            <div class="hint">{{ (string) ($order['user_email'] ?? '') }}</div>
                        </td>
                        <td>{{ (string) $order['package_name'] }}</td>
                        <td>
                            {{ format_money((int) $order['total_cents']) }}                            @if ((int) $order['discount_cents'] > 0)
                                <div class="hint">已减 {{ format_money((int) $order['discount_cents']) }}</div>
                            @endif
                        </td>
                        <td><code style="font-size:11.5px">{{ (string) ($order['gateway'] ?? '-') }}</code></td>
                        <td><span class="badge badge-{{ $order['status_tone'] }}">{{ $order['status_text'] }}</span></td>
                        <td style="font-size:12px;color:var(--muted)">{{ (string) $order['created_at'] }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            @if ((int) $order['status'] === 1)
                                <form method="post" action="{{ url('admin/shop/orders/' . (int) $order['id'] . '/refund') }}"
                                      data-confirm="标记为已退款？用户套餐不会自动收回，请自行调整。" style="display:inline">
                                    {!! csrf_field() !!}                                    <button class="btn btn-sm" type="submit">标记退款</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{!! paginate_links((int) $pager['page'], (int) $pager['last_page'], ['status' => $status, 'keyword' => $keyword]) !!}