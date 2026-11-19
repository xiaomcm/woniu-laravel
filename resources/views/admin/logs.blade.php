<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$queryBase = $query ?? [];
?>
<div class="tabs" style="margin-bottom:18px">
    <a class="tab {!! $tab === 'db' ? 'active' : '' !!}" href="{{ url('admin/logs') }}">
        操作日志
        <span class="tab-count">{!! (int) $pager['total'] !!}</span>
    </a>
    <a class="tab {!! $tab === 'files' ? 'active' : '' !!}" href="{{ url('admin/logs?tab=files') }}">
        运行日志
        <span class="tab-count">{!! count($files) !!}</span>
    </a>
</div>

@if ($tab === 'files')
    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">运行日志文件</div>
                <div class="card-sub">位于 storage/logs，记录异常与业务事件，可单独查看或删除</div>
            </div>
            <div class="spacer"></div>
            @if ($files !== [])
                <form method="post" action="{{ url('admin/logs/files/clear') }}"
                      data-confirm="确认删除全部 {!! count($files) !!} 个日志文件？此操作不可恢复">
                    {!! csrf_field() !!}                    <button class="btn btn-sm btn-danger" type="submit">
                        <svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg> 清空全部
                    </button>
                </form>
            @endif
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>文件名</th><th>大小</th><th>最后写入</th><th style="text-align:right">操作</th></tr>
                    </thead>
                    <tbody>
                    @if ($files === [])
                        <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:34px">没有日志文件</td></tr>
                    @endif
                    @foreach ($files as $file)
                        <?php
                        $bytes = (int) $file['size'];
                        $sizeText = $bytes >= 1048576
                            ? round($bytes / 1048576, 2) . ' MB'
                            : ($bytes >= 1024 ? round($bytes / 1024, 1) . ' KB' : $bytes . ' B');
                        ?>
                        <tr>
                            <td><span class="mono">{{ (string) $file['name'] }}</span></td>
                            <td style="font-size:12.5px;color:var(--muted)">{{ $sizeText }}</td>
                            <td style="font-size:12px;color:var(--muted)">{{ (string) $file['mtime'] }}</td>
                            <td style="text-align:right;white-space:nowrap">
                                <a class="btn btn-sm" target="_blank" rel="noopener"
                                   href="{{ url('admin/logs/files/view') . '?name=' . rawurlencode((string) $file['name']) }}">查看</a>
                                <form method="post" action="{{ url('admin/logs/files/delete') }}" style="display:inline"
                                      data-confirm="确认删除 {{ (string) $file['name'] }} ？">
                                    {!! csrf_field() !!}                                    <input type="hidden" name="name" value="{{ (string) $file['name'] }}">
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="toolbar">
        <form method="get" action="{{ url('admin/logs') }}" class="toolbar" style="margin:0;flex-wrap:wrap">
            <input class="input" name="keyword" value="{{ (string) $filters['keyword'] }}"
                   placeholder="动作 / 详情 / IP / 地区 / 设备" style="height:34px;width:180px">
            <select class="select" name="action" style="height:34px;width:auto">
                <option value="">全部动作</option>
                @foreach ($actions as $row)
                    <option value="{{ (string) $row['action'] }}" {!! (string) $filters['action'] === (string) $row['action'] ? 'selected' : '' !!}>
                        {{ (string) $row['action'] }}（{!! (int) $row['total'] !!}）
                    </option>
                @endforeach
            </select>
            <select class="select" name="user_id" style="height:34px;width:auto">
                <option value="0">全部用户</option>
                @foreach ($users as $user)
                    <option value="{!! (int) $user['id'] !!}" {!! (int) $filters['user_id'] === (int) $user['id'] ? 'selected' : '' !!}>
                        {{ (string) $user['name'] }}                    </option>
                @endforeach
            </select>
            <select class="select" name="days" style="height:34px;width:auto">
                <option value="0">全部时间</option>
                @foreach ([1 => '今天', 7 => '近 7 天', 30 => '近 30 天', 90 => '近 90 天'] as $val => $label)
                    <option value="{!! $val !!}" {!! (int) $filters['days'] === (int) $val ? 'selected' : '' !!}>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary" type="submit">筛选</button>
            @if ($filters['keyword'] !== '' || $filters['action'] !== '' || (int) $filters['user_id'] > 0 || (int) $filters['days'] > 0)
                <a class="btn btn-sm" href="{{ url('admin/logs') }}">重置</a>
            @endif
        </form>
        <div class="spacer"></div>
        <form method="post" action="{{ url('admin/logs/clear') }}" class="toolbar" style="margin:0"
              data-confirm="确认清理日志？此操作不可恢复">
            {!! csrf_field() !!}            <select class="select" name="days" style="height:34px;width:auto">
                <option value="0">全部日志</option>
                <option value="30">30 天前</option>
                <option value="90">90 天前</option>
            </select>
            <button class="btn btn-sm btn-danger" type="submit">
                <svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg> 清理
            </button>
        </form>
    </div>

    <form method="post" action="{{ url('admin/logs/delete') }}" data-confirm="确认删除选中的日志？">
        {!! csrf_field() !!}        <div class="card">
            <div class="card-body" style="padding:0">
                <div class="table-wrap" data-check-scope>
                    <table class="table">
                        <thead>
                        <tr>
                            <th style="width:36px">
                                <input type="checkbox" data-check-all>
                            </th>
                            <th>时间</th><th>用户</th><th>动作</th><th>地区 / IP / 设备</th><th>详情</th>
                            <th style="text-align:right">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if ($logs === [])
                            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:34px">没有符合条件的日志</td></tr>
                        @endif
                        @foreach ($logs as $log)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{!! (int) $log['id'] !!}" data-row-check></td>
                                <td style="white-space:nowrap;font-size:12.5px">{{ (string) $log['created_at'] }}</td>
                                <td>{{ (string) ($log['user_name'] ?? '游客') }}</td>
                                <td><span class="badge">{{ (string) $log['action'] }}</span></td>
                                <td style="font-size:12.5px;color:var(--muted);line-height:1.5;white-space:nowrap">
                                    <div>{{ (string) ($log['location'] ?? '') ?: '未知地区' }}</div>
                                    <div>{{ (string) $log['ip'] }}</div>
                                    @if ((string) ($log['device'] ?? '') !== '')
                                        <div style="font-size:11.5px">{{ (string) $log['device'] }}</div>
                                    @endif
                                </td>
                                <td style="font-size:12.5px;color:var(--muted);max-width:320px">
                                    {{ str_limit((string) ($log['detail'] ?? ''), 80) }}                                </td>
                                <td style="text-align:right">
                                    <button class="btn btn-sm btn-danger" type="submit"
                                            formaction="{{ url('admin/logs/' . (int) $log['id'] . '/delete') }}"
                                            name="id" value="{!! (int) $log['id'] !!}"
                                            data-confirm="删除这条日志？">删除</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($logs !== [])
                <div class="card-foot" style="display:flex;align-items:center;gap:10px">
                    <button class="btn btn-sm btn-danger" type="submit" data-batch-submit>
                        删除选中
                    </button>
                    <span class="hint" data-check-count>未选择</span>
                </div>
            @endif
        </div>
    </form>

    {!! paginate_links((int) $pager['page'], (int) $pager['last_page'], $queryBase) !!}@endif
