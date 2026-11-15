<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="stat-grid">
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-image"/></svg> 图片总数</div>
        <div class="stat-value">{!! (int) $overview['images'] !!}</div>
        <div class="stat-sub">今日新增 {!! (int) $overview['images_today'] !!} 张</div>
    </div>
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-users"/></svg> 用户</div>
        <div class="stat-value">{!! (int) $overview['users'] !!}</div>
        <div class="stat-sub">今日注册 {!! (int) $overview['users_today'] !!} 人</div>
    </div>
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-storage"/></svg> 占用空间</div>
        <div class="stat-value">{{ format_bytes((int) $overview['used']) }}</div>
        <div class="stat-sub">相册 {!! (int) $overview['albums'] !!} 个</div>
    </div>
    <div class="stat">
        <div class="stat-label"><svg class="ic" style="width:15px;height:15px"><use href="#i-shield"/></svg> 待审核</div>
        <div class="stat-value">{!! (int) $overview['pending'] !!}</div>
        <div class="stat-sub"><a href="{{ url('admin/images?status=0') }}">去处理 →</a></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>近 14 天上传趋势</h2>
        <div class="spacer"></div>
        <span class="badge">共 {!! array_sum($series) !!} 张</span>
    </div>
    <div class="card-body">
        <div class="chart" style="height:160px">
            @foreach ($series as $date => $count)
                <div class="chart-col" title="{{ $date . ' · ' . $count . ' 张' }}">
                    <div class="chart-bar" style="height: {!! max(3, (int) round($count / $seriesMax * 100)) !!}%"></div>
                    <div class="chart-label">{{ date('m-d', strtotime($date)) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head">
            <h2>最新上传</h2>
            <div class="spacer"></div>
            <a class="btn btn-sm btn-ghost" href="{{ url('admin/images') }}">全部</a>
        </div>
        <div class="card-body">
            @if ($recentImages === [])
                <div class="empty" style="padding:30px"><div>暂无图片</div></div>
            @else
                <div class="image-grid" style="grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px">
                    @foreach ($recentImages as $image)
                        <a class="image-card" href="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1' }}" target="_blank" rel="noopener"
                           data-lightbox-src="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1&file=1' }}"
                           data-lightbox-original="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1' }}"
                           data-lightbox-caption="{{ (string) ($image['name'] ?? '') }}">
                            <img src="{{ url('admin/images/' . (int) $image['id'] . '/preview') }}" alt=""{{ lazy_attr() }}>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>上传排行</h2></div>
        <div class="card-body tight">
            @if ($topUsers === [])
                <div class="empty" style="padding:26px">暂无数据</div>
            @else
                @foreach ($topUsers as $row)
                    <div class="kv">
                        <span>{{ (string) $row['name'] }}</span>
                        <span>{!! (int) $row['image_count'] !!} 张 · {{ format_bytes((int) $row['total_size']) }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>操作日志</h2>
        <div class="spacer"></div>
        <a class="btn btn-sm" href="{{ url('admin/logs') }}">查看全部</a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>时间</th><th>用户</th><th>动作</th><th>IP</th><th>详情</th></tr></thead>
                <tbody>
                @if ($logs === [])
                    <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:26px">暂无日志</td></tr>
                @else
                    @foreach ($logs as $log)
                        <tr>
                            <td style="white-space:nowrap">{{ (string) $log['created_at'] }}</td>
                            <td>{{ (string) ($log['user_name'] ?? '游客') }}</td>
                            <td><span class="badge">{{ (string) $log['action'] }}</span></td>
                            <td>{{ (string) $log['ip'] }}</td>
                            <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ (string) $log['detail'] }}">
                                {{ (string) $log['detail'] }}                            </td>
                        </tr>
                    @endforeach
                @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
