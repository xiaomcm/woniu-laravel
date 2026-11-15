<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

use App\Models\Ticket;

$status = (int) $ticket['status'];
$closed = $status === Ticket::STATUS_CLOSED;
$priorityLabels = [0 => '不急', 1 => '一般', 2 => '紧急'];
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">{{ (string) $ticket['subject'] }}</h1>
        <p class="explore-sub">
            #{!! (int) $ticket['id'] !!}            <span class="dot">·</span> {{ $categories[$ticket['category']] ?? '其他' }}            <span class="dot">·</span> {{ $priorityLabels[(int) $ticket['priority']] ?? '一般' }}            <span class="dot">·</span> {{ (string) $ticket['created_at'] }}        </p>
    </div>
    {!! badge(Ticket::statusLabel($status), Ticket::statusTone($status)) !!}    <a class="btn btn-sm" href="{{ url('support/tickets') }}" style="margin-left:10px">返回列表</a>
    <form method="post" action="{{ url('support/tickets/' . (int) $ticket['id'] . '/delete') }}" style="display:inline;margin-left:10px"
          data-confirm="确定删除这条工单？连同全部回复一起删除，不可恢复">
        {!! csrf_field() !!}        <button class="btn btn-sm btn-danger" type="submit">删除</button>
    </form>
</div>

<div class="checkout-grid">
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="thread">
            @foreach ($replies as $reply)
                <div class="thread-item {!! (int) $reply['is_admin'] === 1 ? 'admin' : '' !!}">
                    <div class="thread-head">
                        <span class="thread-name">{{ (string) ($reply['user_name'] ?? '用户') }}</span>
                        @if ((int) $reply['is_admin'] === 1)
                            <span class="thread-tag">管理员</span>
                        @endif
                        <span class="thread-time">{{ (string) $reply['created_at'] }}</span>
                    </div>
                    <div class="thread-body">{{ (string) $reply['content'] }}</div>
                </div>
            @endforeach
        </div>

        @if ($closed)
            <div class="card">
                <div class="card-body">
                    <p class="hint" style="margin:0">该工单已关闭。如果问题还没解决，可以新建一个工单继续。</p>
                    <a class="btn btn-sm btn-primary" href="{{ url('support/tickets/new') }}" style="margin-top:12px">新建工单</a>
                </div>
            </div>
        @else
            <form method="post" action="{{ url('support/tickets/' . (int) $ticket['id'] . '/reply') }}" class="card">
                {!! csrf_field() !!}                <div class="card-head"><div class="card-title">补充说明</div></div>
                <div class="card-body">
                    <div class="field">
                        <textarea class="textarea" name="content" rows="5" required
                                  placeholder="补充复现步骤、截图链接或当前进展"></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit" style="margin-top:12px">发送</button>
                </div>
            </form>
        @endif
    </div>

    <div class="card" style="align-self:start">
        <div class="card-head"><div class="card-title">工单信息</div></div>
        <div class="card-body">
            <div class="kv-row"><span class="kv-key">状态</span><span class="kv-val">{!! badge(Ticket::statusLabel($status), Ticket::statusTone($status)) !!}</span></div>
            <div class="kv-row"><span class="kv-key">类型</span><span class="kv-val">{{ $categories[$ticket['category']] ?? '其他' }}</span></div>
            <div class="kv-row"><span class="kv-key">紧急程度</span><span class="kv-val">{{ $priorityLabels[(int) $ticket['priority']] ?? '一般' }}</span></div>
            <div class="kv-row"><span class="kv-key">提交时间</span><span class="kv-val">{{ (string) $ticket['created_at'] }}</span></div>
            <div class="kv-row"><span class="kv-key">最后回复</span><span class="kv-val">{{ (string) $ticket['last_reply_at'] }}</span></div>
            <div class="kv-row"><span class="kv-key">回复数</span><span class="kv-val">{!! count($replies) !!} 条</span></div>

            <p class="hint" style="margin:14px 0 0;line-height:1.75">
                工单按提交顺序处理，紧急程度仅作参考。涉及付款请在描述里附上订单号。
            </p>
        </div>
    </div>
</div>
