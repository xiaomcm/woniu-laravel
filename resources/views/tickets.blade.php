<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

use App\Models\Ticket;

$categories = Ticket::categories();
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">我的工单</h1>
        <p class="explore-sub">共 {!! (int) $pager['total'] !!} 条工单</p>
    </div>
    @if ($enabled)
        <a class="btn btn-sm btn-primary" href="{{ url('support/tickets/new') }}">提交工单</a>
    @endif
</div>

@if (!$enabled)
    <div class="empty">
        <svg class="ic" style="width:30px;height:30px"><use href="#i-ticket"/></svg>
        <p>站点暂未开放工单，可以通过意见反馈联系我们</p>
        <a class="btn btn-sm" href="{{ url('feedback') }}">去反馈</a>
    </div>
@elseif ($tickets === [])
    <div class="empty">
        <svg class="ic" style="width:30px;height:30px"><use href="#i-ticket"/></svg>
        <p>还没有工单记录</p>
        <a class="btn btn-sm btn-primary" href="{{ url('support/tickets/new') }}">提交第一条</a>
    </div>
@else
    <div class="thread">
        @foreach ($tickets as $ticket)
            <?php $status = (int) $ticket['status']; ?>
            <div class="thread-item">
                <div class="thread-head">
                    <a class="thread-name thread-link" href="{{ url('support/tickets/' . (int) $ticket['id']) }}">{{ (string) $ticket['subject'] }}</a>
                    {!! badge(Ticket::statusLabel($status), Ticket::statusTone($status)) !!}                    <span class="thread-time">{{ (string) $ticket['last_reply_at'] }}</span>
                    <form method="post" action="{{ url('support/tickets/' . (int) $ticket['id'] . '/delete') }}" class="thread-del"
                          data-confirm="确定删除这条工单？连同全部回复一起删除，不可恢复">
                        {!! csrf_field() !!}                        <button class="btn btn-sm btn-danger" type="submit">删除</button>
                    </form>
                </div>
                <a class="thread-body thread-link" href="{{ url('support/tickets/' . (int) $ticket['id']) }}">{{ $categories[$ticket['category']] ?? '其他' }} · {!! (int) ($ticket['reply_count'] ?? 0) !!} 条回复 · #{!! (int) $ticket['id'] !!}</a>
            </div>
        @endforeach
    </div>

    {!! paginate_links((int) $pager['page'], (int) $pager['last_page']) !!}@endif
