<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="toolbar">
    <span class="hint">待处理 {!! (int) $pending !!} 条</span>
    <div class="spacer"></div>
    <form method="get" action="{{ url('admin/support/reports') }}" class="toolbar" style="margin:0">
        <select class="select" name="status" style="height:34px;width:auto">
            <option value="">全部状态</option>
            @foreach ([0 => '待处理', 1 => '已处理', 2 => '已驳回'] as $val => $label)
                <option value="{!! $val !!}" {!! (string) $status === (string) $val ? 'selected' : '' !!}>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm" type="submit">筛选</button>
    </form>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>内容</th><th>类型</th><th>说明</th><th>举报人</th><th>状态</th><th>时间</th><th></th></tr>
                </thead>
                <tbody>
                @if ($reports === [])
                    <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:34px">没有举报记录</td></tr>
                @endif
                @foreach ($reports as $report)
                    <tr>
                        <td>
                            @if (!empty($report['image_id']) && !empty($report['image_sha1']))
                                <a href="{{ url('admin/images?keyword=' . (string) $report['image_sha1']) }}" style="color:var(--primary)">
                                    {{ str_limit((string) ($report['image_name'] ?? ('图片 #' . $report['image_id'])), 30) }}                                </a>
                            @elseif (!empty($report['image_id']))
                                <span>图片 #{!! (int) $report['image_id'] !!}</span>
                            @elseif (!empty($report['album_id']))
                                相册 {{ (string) ($report['album_name'] ?? ('#' . $report['album_id'])) }}                            @else
                                <span class="hint">内容已删除</span>
                            @endif
                        </td>
                        <td style="font-size:12.5px">{{ $categories[(string) $report['category']] ?? '其他' }}</td>
                        <td style="font-size:12.5px;color:var(--muted);max-width:240px">{{ str_limit((string) ($report['reason'] ?? ''), 60) }}</td>
                        <td style="font-size:12.5px">{{ (string) ($report['reporter_name'] ?? '游客') }}</td>
                        <td>
                            <span class="badge badge-{{ \App\Models\Report::statusTone((int) $report['status']) }}">
                                {{ \App\Models\Report::statusLabel((int) $report['status']) }}                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--muted)">{{ (string) $report['created_at'] }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            @if ((int) $report['status'] === 0)
                                <button class="btn btn-sm" type="button"
                                        data-modal-open="#report-modal"
                                        data-report-id="{!! (int) $report['id'] !!}"
                                        data-report-url="{{ url('admin/support/reports/' . (int) $report['id']) }}"
                                        data-report-image="{!! (int) ($report['image_id'] ?? 0) !!}"
                                        data-report-target="{!! !empty($report['image_id']) ? '图片「' . e((string) ($report['image_name'] ?? ('#' . $report['image_id']))) . '」' : '相册' !!}">处理</button>
                            @else
                                <span class="hint">{{ (string) ($report['handle_note'] ?? '') }}</span>
                            @endif
                            <form method="post" action="{{ url('admin/support/reports/' . (int) $report['id'] . '/delete') }}" style="display:inline;margin-left:6px"
                                  data-confirm="确定删除这条举报记录？删除后不可恢复">
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

{!! paginate_links((int) $pager['page'], (int) $pager['last_page'], ['status' => $status]) !!}
<div class="modal" id="report-modal">
    <form class="modal-box" id="report-form" method="post" action="">
        <div class="modal-head">
            <h3>处理举报</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <p class="hint" style="margin:0 0 14px">正在处理：<span id="report-target-name"></span></p>
            <div class="field">
                <label class="label">处理结果</label>
                <select class="select" name="decision">
                    <option value="handle">举报成立，下线内容</option>
                    <option value="reject">举报不成立，驳回</option>
                </select>
            </div>
            <div class="field">
                <label class="label">备注 <span class="hint">会记录在案，可选</span></label>
                <textarea class="input" name="note" rows="3" placeholder="例如：确认含违规内容，已下线并通知上传者"></textarea>
            </div>
            <label class="switch" id="report-offline-row">
                <input type="hidden" name="offline" value="0">
                <input type="checkbox" name="offline" value="1" checked>
                <span class="track"></span><span>同时把这张图设为未通过</span>
            </label>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">提交</button>
        </div>
    </form>
</div>
