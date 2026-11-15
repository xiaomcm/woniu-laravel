<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="toolbar">
    <span class="hint">待处理 {!! (int) $openCount !!} 个</span>
    <div class="spacer"></div>
    <form method="get" action="{{ url('admin/support/tickets') }}" class="toolbar" style="margin:0">
        <select class="select" name="status" style="height:34px;width:auto">
            <option value="">全部状态</option>
            @foreach ([0 => '待处理', 1 => '处理中', 2 => '已回复', 3 => '已关闭'] as $val => $label)
                <option value="{!! $val !!}" {!! (string) $status === (string) $val ? 'selected' : '' !!}>{{ $label }}</option>
            @endforeach
        </select>
        <input class="input" name="keyword" value="{{ $keyword }}" placeholder="标题 / 用户名" style="height:34px;width:190px">
        <button class="btn btn-sm" type="submit">查询</button>
    </form>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>#</th><th>标题</th><th>用户</th><th>分类</th><th>回复</th><th>状态</th><th>时间</th><th></th></tr>
                </thead>
                <tbody>
                @if ($tickets === [])
                    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:34px">没有工单</td></tr>
                @endif
                @foreach ($tickets as $ticket)
                    <tr>
                        <td style="color:var(--muted)">{!! (int) $ticket['id'] !!}</td>
                        <td><strong>{{ str_limit((string) $ticket['subject'], 46) }}</strong></td>
                        <td>{{ (string) ($ticket['user_name'] ?? '-') }}</td>
                        <td style="font-size:12px;color:var(--muted)">{{ \App\Models\Ticket::categories()[(string) $ticket['category']] ?? '其他' }}</td>
                        <td>{!! (int) $ticket['reply_count'] !!}</td>
                        <td>
                            <span class="badge badge-{{ \App\Models\Ticket::statusTone((int) $ticket['status']) }}">
                                {{ \App\Models\Ticket::statusLabel((int) $ticket['status']) }}                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--muted)">{{ (string) $ticket['created_at'] }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <a class="btn btn-sm" href="{{ url('admin/support/tickets/' . (int) $ticket['id']) }}">查看</a>
                            <form method="post" action="{{ url('admin/support/tickets/' . (int) $ticket['id'] . '/delete') }}" style="display:inline;margin-left:6px"
                                  data-confirm="确定删除这条工单？连同全部回复一起删除，不可恢复">
                                {!! csrf_field() !!}                                <button class="btn btn-sm btn-danger" type="submit">删除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{!! paginate_links((int) $pager['page'], (int) $pager['last_page'], ['status' => $status, 'keyword' => $keyword]) !!}