<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$trashed = !empty($filters['trashed']);
$query = [
    'keyword'  => $filters['keyword'] ?? '',
    'album_id' => $filters['album_id'] ?? '',
    'status'   => $filters['status'] ?? '',
    'sort'     => $filters['sort'] ?? 'newest',
    'trashed'  => $trashed ? 1 : 0,
];
?>
<div class="toolbar">
    <form method="get" action="{{ url('images') }}" class="toolbar" style="margin:0;gap:8px">
        <input class="input input-sm" style="width:200px" name="keyword" value="{{ (string) ($filters['keyword'] ?? '') }}" placeholder="搜索文件名…">
        <select class="select input-sm" style="width:auto" name="album_id">
            <option value="">全部相册</option>
            <option value="-1" {!! (string) ($filters['album_id'] ?? '') === '-1' ? 'selected' : '' !!}>未分类</option>
            @foreach ($albums as $album)
                <option value="{!! (int) $album['id'] !!}" {!! (int) ($filters['album_id'] ?? 0) === (int) $album['id'] ? 'selected' : '' !!}>
                    {{ (string) $album['name'] }}                </option>
            @endforeach
        </select>
        <select class="select input-sm" style="width:auto" name="status">
            <option value="">全部状态</option>
            <option value="0" {!! (string) ($filters['status'] ?? '') === '0' ? 'selected' : '' !!}>待审核</option>
            <option value="1" {!! (string) ($filters['status'] ?? '') === '1' ? 'selected' : '' !!}>已通过</option>
            <option value="2" {!! (string) ($filters['status'] ?? '') === '2' ? 'selected' : '' !!}>已拒绝</option>
        </select>
        <select class="select input-sm" style="width:auto" name="sort">
            <option value="newest" {!! ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' !!}>最新上传</option>
            <option value="oldest" {!! ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' !!}>最早上传</option>
        </select>
        @if ($trashed)<input type="hidden" name="trashed" value="1">@endif
        <button class="btn btn-sm" type="submit">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-search"/></svg> 筛选
        </button>
    </form>

    <div class="spacer"></div>

    <a class="btn btn-sm {!! $trashed ? '' : 'btn-ghost' !!}" href="{{ url('images') }}">正常</a>
    <a class="btn btn-sm {!! $trashed ? 'btn-ghost' : '' !!}" href="{{ url('images?trashed=1') }}">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg> 回收站
    </a>
</div>

<div class="card">
    <div class="card-head">
        <h2>{!! $trashed ? '回收站' : '图片' !!} <span class="badge" style="margin-left:6px">{!! (int) $pager['total'] !!}</span></h2>
        <div class="spacer"></div>
        <a class="btn btn-sm btn-primary" href="{{ url('upload') }}">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 上传
        </a>
    </div>

    <div class="card-body">
        @if ($images === [])
            <div class="empty">
                <svg class="ic"><use href="#i-image"/></svg>
                <h3>{!! $trashed ? '回收站是空的' : '还没有图片' !!}</h3>
                <div>{!! $trashed ? '删除的图片会先进入这里' : '去上传第一张图片吧' !!}</div>
            </div>
        @else
            <div class="image-grid" data-image-grid data-token="{{ csrf_token() }}">
                @foreach ($images as $image)
                    <div class="image-card"
                         data-id="{!! (int) $image['id'] !!}"
                         data-url="{{ (string) $image['url'] }}">
                        <span class="check" title="选择"></span>
                        @if ((int) $image['status'] !== 1)
                            <span class="status-dot">
                                <span class="badge {!! (int) $image['status'] === 0 ? 'badge-warning' : 'badge-danger' !!}">
                                    {!! (int) $image['status'] === 0 ? '待审核' : '已拒绝' !!}                                </span>
                            </span>
                        @endif
                        <img src="{{ image_thumb($image, true) }}" data-w="{!! (int) $image['width'] !!}" data-h="{!! (int) $image['height'] !!}" alt="{{ (string) $image['name'] }}"{{ lazy_attr() }}
                             @if ((string) setting('lightbox_enabled', '1') === '1')
                                 data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" title="点击查看大图"
                             @endif
                        >
                        @if (trim((string) ($image['password'] ?? '')) !== '')
                            <span class="lock-dot" title="已设置访问密码">
                                <svg class="ic"><use href="#i-lock"/></svg>加密
                            </span>
                        @endif
                        <div class="image-overlay">
                            <div class="image-meta">
                                <strong>{{ (string) $image['name'] }}</strong>
                                {{ format_bytes((int) $image['size']) }}                                @if (!empty($image['album_name'])) · {{ (string) $image['album_name'] }}@endif
                            </div>
                            <div class="image-ops">
                                @if ((string) setting('lightbox_enabled', '1') === '1')
                                    <button class="op-btn" type="button" data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" data-lightbox-self="1" title="灯箱预览">
                                        <svg class="ic"><use href="#i-expand"/></svg>
                                    </button>
                                @endif

                                <button class="op-btn" type="button" data-copy="{{ (string) $image['url'] }}" title="复制 URL">
                                    <svg class="ic"><use href="#i-link"/></svg>
                                </button>
                                <button class="op-btn" type="button" data-copy="{{ '![' . $image['name'] . '](' . $image['url'] . ')' }}" title="复制 Markdown">
                                    <svg class="ic"><use href="#i-copy"/></svg>
                                </button>
                                <button class="op-btn" type="button" title="编辑"
                                        data-fill="{{ json_encode([
                                            'name'           => $image['name'],
                                            'album_id'       => $image['album_id'] ?? '',
                                            'description'    => (string) ($image['description'] ?? ''),
                                            'tags'           => (string) ($image['tags'] ?? ''),
                                            'is_public'      => (int) $image['is_public'],
                                            'password'       => '',
                                            'clear_password' => 0,
                                        ], JSON_UNESCAPED_UNICODE) }}"
                                        data-locked="{!! trim((string) ($image['password'] ?? '')) !== '' ? '1' : '0' !!}"
                                        data-fill-target="#image-edit-form"
                                        data-action="{{ url('images/' . (int) $image['id']) }}"
                                        data-modal="#edit-modal">
                                    <svg class="ic"><use href="#i-settings"/></svg>
                                </button>
                                <a class="op-btn" href="{{ url('i/' . $image['sha1']) }}" target="_blank" rel="noopener" title="详情页">
                                    <svg class="ic"><use href="#i-eye"/></svg>
                                </a>
                                @if ($trashed)
                                    <button class="op-btn" type="button" title="恢复" data-restore="{!! (int) $image['id'] !!}">
                                        <svg class="ic"><use href="#i-refresh"/></svg>
                                    </button>
                                @else
                                    <button class="op-btn" type="button" title="删除" data-delete-image="{!! (int) $image['id'] !!}">
                                        <svg class="ic"><use href="#i-trash"/></svg>
                                    </button>
                                @endif
                                <a class="op-btn" href="{{ (string) $image['url'] }}" target="_blank" rel="noopener" title="打开原图">
                                    <svg class="ic"><use href="#i-external"/></svg>
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
    <button class="btn btn-sm" type="button" data-action="copy" data-need-selection>
        <svg class="ic" style="width:14px;height:14px"><use href="#i-copy"/></svg> 复制链接
    </button>
    <button class="btn btn-sm" type="button" data-action="download" data-endpoint="{{ url('images/download') }}" data-need-selection>
        <svg class="ic" style="width:14px;height:14px"><use href="#i-package"/></svg> 打包下载
    </button>
    @if ($trashed)
        <button class="btn btn-sm" type="button" data-action="restore" data-endpoint="{{ url('images/restore') }}" data-need-selection>
            <svg class="ic" style="width:14px;height:14px"><use href="#i-refresh"/></svg> 恢复
        </button>
        <button class="btn btn-sm btn-danger" type="button" data-action="destroy" data-endpoint="{{ url('images/destroy') }}" data-need-selection>
            <svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg> 彻底删除
        </button>
    @else
        <button class="btn btn-sm" type="button" data-action="move" data-need-selection>
            <svg class="ic" style="width:14px;height:14px"><use href="#i-folder"/></svg> 移动相册
        </button>
        <button class="btn btn-sm btn-danger" type="button" data-action="delete" data-endpoint="{{ url('images/delete') }}" data-need-selection>
            <svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg> 删除
        </button>
    @endif
</div>

<!-- 编辑图片 -->
<div class="modal" id="edit-modal">
    <form class="modal-box" id="image-edit-form" method="post" action="" data-ajax data-modal="#edit-modal">
        <div class="modal-head">
            <h3>编辑图片</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="field">
                <label class="label">名称</label>
                <input class="input" name="name" maxlength="100">
            </div>
            <div class="field">
                <label class="label">描述 <span class="hint">展示在详情页</span></label>
                <textarea class="textarea" name="description" rows="2" maxlength="500" placeholder="写点什么…"></textarea>
            </div>
            <div class="field">
                <label class="label">标签 <span class="hint">逗号或空格分隔，最多 8 个</span></label>
                <input class="input" name="tags" maxlength="255" placeholder="风景, 壁纸">
            </div>
            <div class="field">
                <label class="label">所属相册</label>
                <select class="select" name="album_id">
                    <option value="">未分类</option>
                    @foreach ($albums as $album)
                        <option value="{!! (int) $album['id'] !!}">{{ (string) $album['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <label class="switch" style="margin-bottom:0">
                <input type="hidden" name="is_public_present" value="1">
                <input type="checkbox" name="is_public" value="1" checked>
                <span class="track"></span><span>公开展示（出现在广场与详情页）</span>
            </label>
            <div class="field" style="margin-top:14px">
                <label class="label">访问密码 <span class="hint">4-32 位，留空表示不修改</span></label>
                <input class="input" type="text" name="password" maxlength="32" autocomplete="new-password"
                       placeholder="设置后凭密码查看，留空不修改">
                <div class="hint" data-password-state style="margin-top:6px"></div>
            </div>
            <label class="switch" style="margin-bottom:0;display:none" data-clear-password>
                <input type="checkbox" name="clear_password" value="1">
                <span class="track"></span><span>清除已有密码（恢复公开访问）</span>
            </label>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
</div>

<!-- 移动相册 -->
<div class="modal" id="move-modal" data-endpoint="{{ url('images/move') }}">
    <form class="modal-box">
        <div class="modal-head">
            <h3>移动到相册</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="ids-holder" value="">
            <div class="field" style="margin-bottom:0">
                <label class="label">目标相册</label>
                <select class="select" name="album_id">
                    <option value="">未分类</option>
                    @foreach ($albums as $album)
                        <option value="{!! (int) $album['id'] !!}">{{ (string) $album['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">移动</button>
        </div>
    </form>
</div>
