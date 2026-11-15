<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="toolbar">
    <span class="hint">未读 {!! (int) $newCount !!} 条</span>
    <div class="spacer"></div>
    <form method="get" action="{{ url('admin/support/feedbacks') }}" class="toolbar" style="margin:0">
        <select class="select" name="status" style="height:34px;width:auto">
            <option value="">全部</option>
            @foreach ([0 => '未读', 1 => '已读', 2 => '已回复'] as $val => $label)
                <option value="{!! $val !!}" {!! (string) $status === (string) $val ? 'selected' : '' !!}>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm" type="submit">筛选</button>
    </form>
</div>

<div class="thread">
    @if ($feedbacks === [])
        <div class="card"><div class="card-body" style="text-align:center;color:var(--muted);padding:34px">还没有收到意见</div></div>
    @endif
    @foreach ($feedbacks as $item)
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">
                        {{ $kinds[(string) $item['kind']] ?? '其他' }}                        <span class="badge badge-{!! (int) $item['status'] === 2 ? 'success' : ((int) $item['status'] === 0 ? 'warning' : 'muted') !!}">
                            {!! (int) $item['status'] === 2 ? '已回复' : ((int) $item['status'] === 0 ? '未读' : '已读') !!}                        </span>
                    </div>
                    <div class="card-sub">
                        {{ (string) ($item['user_name'] ?? '游客') }}                        @if ((string) ($item['contact'] ?? '') !== '') · {{ (string) $item['contact'] }}@endif
                        · {{ (string) $item['created_at'] }}                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="thread-body">{{ (string) $item['content'] }}</div>
                @if ((string) ($item['reply'] ?? '') !== '')
                    <div class="thread-item admin" style="margin-top:12px">
                        <div class="thread-head"><span class="thread-name">我的回复</span><span class="thread-time">{{ (string) $item['replied_at'] }}</span></div>
                        <div class="thread-body">{{ (string) $item['reply'] }}</div>
                    </div>
                @endif
                <form method="post" action="{{ url('admin/support/feedbacks/' . (int) $item['id']) }}" style="margin-top:12px">
                    {!! csrf_field() !!}                    <textarea class="input" name="reply" rows="2" placeholder="回复内容，留空则仅标记已读"></textarea>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px">
                        <button class="btn btn-sm btn-primary" type="submit">保存</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>

{!! paginate_links((int) $pager['page'], (int) $pager['last_page'], ['status' => $status]) !!}