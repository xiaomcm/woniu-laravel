<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
$status = (int) $ticket['status'];
?>
<div class="toolbar">
    <a class="btn btn-sm" href="{{ url('admin/support/tickets') }}">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-arrow-left"/></svg> 返回工单列表
    </a>
    <div class="spacer"></div>
    <form method="post" action="{{ url('admin/support/tickets/' . (int) $ticket['id'] . '/close') }}" style="display:inline">
        {!! csrf_field() !!}        <button class="btn btn-sm" type="submit">{!! $status === 3 ? '重新打开' : '关闭工单' !!}</button>
    </form>
    <form method="post" action="{{ url('admin/support/tickets/' . (int) $ticket['id'] . '/delete') }}" style="display:inline;margin-left:8px"
          data-confirm="确定删除这条工单？连同全部回复一起删除，不可恢复">
        {!! csrf_field() !!}        <button class="btn btn-sm btn-danger" type="submit">删除工单</button>
    </form>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-head">
        <div>
            <div class="card-title">{{ (string) $ticket['subject'] }}</div>
            <div class="card-sub">
                #{!! (int) $ticket['id'] !!} ·
                {{ $categories[(string) $ticket['category']] ?? '其他' }} ·
                提交于 {{ (string) $ticket['created_at'] }}            </div>
        </div>
        <div class="spacer"></div>
        <span class="badge badge-{{ \App\Models\Ticket::statusTone($status) }}">{{ \App\Models\Ticket::statusLabel($status) }}</span>
    </div>
</div>

<div class="thread" style="margin-bottom:16px">
    @foreach ($replies as $reply)
        <div class="thread-item {!! (int) $reply['is_admin'] === 1 ? 'admin' : '' !!}">
            <div class="thread-head">
                <span class="thread-name">{{ (string) ($reply['user_name'] ?? '用户') }}</span>
                @if ((int) $reply['is_admin'] === 1)<span class="thread-tag">客服</span>@endif
                <span class="thread-time">{{ (string) $reply['created_at'] }}</span>
            </div>
            <div class="thread-body">{{ (string) $reply['content'] }}</div>
        </div>
    @endforeach
</div>

<form class="card" method="post" action="{{ url('admin/support/tickets/' . (int) $ticket['id'] . '/reply') }}">
    {!! csrf_field() !!}    <div class="card-head"><div class="card-title">回复用户</div></div>
    <div class="card-body">
        <textarea class="input" name="content" rows="5" required placeholder="说明处理结果或需要用户补充的信息"></textarea>
    </div>
    <div class="card-foot">
        <div class="spacer"></div>
        <button class="btn btn-sm btn-primary" type="submit">发送回复</button>
    </div>
</form>
