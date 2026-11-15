<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$trashed = !empty($filters['trashed']);
$query = [
    'keyword' => $filters['keyword'] ?? '',
    'status'  => $filters['status'] ?? '',
    'trashed' => $trashed ? 1 : 0,
];
?>
<div class="toolbar">
    <form method="get" action="{{ url('admin/images') }}" class="toolbar" style="margin:0;gap:8px">
        <input class="input input-sm" style="width:200px" name="keyword" value="{{ (string) ($filters['keyword'] ?? '') }}" placeholder="搜索文件名 / 用户…">
        <select class="select input-sm" style="width:auto" name="status">
            <option value="">全部状态</option>
            <option value="0" {!! (string) ($filters['status'] ?? '') === '0' ? 'selected' : '' !!}>待审核</option>
            <option value="1" {!! (string) ($filters['status'] ?? '') === '1' ? 'selected' : '' !!}>已通过</option>
            <option value="2" {!! (string) ($filters['status'] ?? '') === '2' ? 'selected' : '' !!}>已拒绝</option>
        </select>
        <button class="btn btn-sm" type="submit">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-search"/></svg> 筛选
        </button>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-sm {!! $trashed ? '' : 'btn-ghost' !!}" href="{{ url('admin/images') }}">正常</a>
    <a class="btn btn-sm {!! $trashed ? 'btn-ghost' : '' !!}" href="{{ url('admin/images?trashed=1') }}">回收站</a>
</div>

<div class="card">
    <div class="card-head">
        <h2>全部图片 <span class="badge" style="margin-left:6px">{!! (int) $pager['total'] !!}</span></h2>
    </div>
    <div class="card-body">
        @if ($images === [])
            <div class="empty">
                <svg class="ic"><use href="#i-image"/></svg>
                <h3>没有符合条件的图片</h3>
            </div>
        @else
            <div class="image-grid" data-image-grid data-token="{{ csrf_token() }}">
                @foreach ($images as $image)
                    <div class="image-card" data-id="{!! (int) $image['id'] !!}" data-url="{{ (string) $image['url'] }}">
                        <span class="check"></span>
                        <span class="status-dot">
                            <span class="badge {!! (int) $image['status'] === 1 ? 'badge-success' : ((int) $image['status'] === 0 ? 'badge-warning' : 'badge-danger') !!}">
                                {!! (int) $image['status'] === 1 ? '正常' : ((int) $image['status'] === 0 ? '待审' : '拒绝') !!}                            </span>
                        </span>
                        <img src="{{ url('admin/images/' . (int) $image['id'] . '/preview') }}" alt=""{{ lazy_attr() }}
                             data-lightbox-src="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1&file=1' }}"
                             data-lightbox-original="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1' }}"
                             data-lightbox-caption="{{ (string) $image['name'] }}" title="点击查看大图">
                        <div class="image-overlay">
                            <div class="image-meta">
                                <strong>{{ (string) $image['name'] }}</strong>
                                {{ (string) ($image['user_name'] ?? '游客') }} · {{ format_bytes((int) $image['size']) }}                            </div>
                            <div class="image-ops">
                                <button class="op-btn" type="button" data-copy="{{ (string) $image['url'] }}" title="复制链接">
                                    <svg class="ic"><use href="#i-link"/></svg>
                                </button>
                                <a class="op-btn" href="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1' }}" target="_blank" rel="noopener"
                                   data-lightbox-src="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1&file=1' }}"
                                   data-lightbox-original="{{ url('admin/images/' . (int) $image['id'] . '/preview') . '?raw=1' }}"
                                   data-lightbox-self="1"
                                   data-lightbox-caption="{{ (string) $image['name'] }}" title="预览大图">
                                    <svg class="ic"><use href="#i-eye"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            {!! paginate_links((int) $pager['page'], (int) $pager['last_page'], $query) !!}        @endif
    </div>
</div>

<div class="bulkbar" data-bulkbar>
    <span class="count">已选 0 项</span>
    <span class="sep"></span>
    <button class="btn btn-sm" type="button" data-action="approve" data-endpoint="{{ url('admin/images/audit') }}" data-need-selection>
        <svg class="ic" style="width:14px;height:14px"><use href="#i-check"/></svg> 通过
    </button>
    <button class="btn btn-sm" type="button" data-action="reject" data-endpoint="{{ url('admin/images/audit') }}" data-need-selection>
        <svg class="ic" style="width:14px;height:14px"><use href="#i-close"/></svg> 拒绝
    </button>
    <button class="btn btn-sm btn-danger" type="button" data-action="delete" data-endpoint="{{ url('admin/images/delete') }}" data-need-selection>
        <svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg> 删除
    </button>
    <button class="btn btn-sm btn-danger" type="button" data-action="destroy" data-endpoint="{{ url('admin/images/delete') }}" data-need-selection>
        彻底删除
    </button>
</div>
