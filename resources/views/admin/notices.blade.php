<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="toolbar">
    <span class="hint">公告显示在前台顶部，置顶优先，可设置过期时间</span>
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#notice-modal" data-reset-form="#notice-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 发布公告
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>标题</th><th>级别</th><th>置顶</th><th>全站弹窗</th><th>状态</th><th>过期时间</th><th>发布时间</th><th></th></tr>
                </thead>
                <tbody>
                @if ($notices === [])
                    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:34px">还没有公告</td></tr>
                @endif
                @foreach ($notices as $notice)
                    <tr>
                        <td>
                            <strong>{{ (string) $notice['title'] }}</strong>
                            <div class="hint">{{ str_limit(strip_tags((string) $notice['content']), 50) }}</div>
                        </td>
                        <td>{{ $levels[(string) $notice['level']] ?? '提示' }}</td>
                        <td>{!! (int) $notice['pinned'] === 1 ? '是' : '-' !!}</td>
                        <td>
                            @if ((int) ($notice['popup'] ?? 0) === 1)
                                <span class="badge badge-warning">弹窗</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if ((int) $notice['status'] === 1)
                                <span class="badge badge-success">展示中</span>
                            @else
                                <span class="badge">已隐藏</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--muted)">{{ (string) ($notice['expire_at'] ?? '永久') }}</td>
                        <td style="font-size:12px;color:var(--muted)">{{ (string) $notice['created_at'] }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'title'          => $notice['title'],
                                        'content'        => $notice['content'],
                                        'level'          => $notice['level'],
                                        'pinned'         => (int) $notice['pinned'],
                                        'popup'          => (int) ($notice['popup'] ?? 0),
                                        'status_present' => 1,
                                        'status'         => (int) $notice['status'],
                                        'expire_at'      => str_replace(' ', 'T', substr((string) ($notice['expire_at'] ?? ''), 0, 16)),
                                    ], JSON_UNESCAPED_UNICODE) }}"
                                    data-fill-target="#notice-edit-form"
                                    data-action="{{ url('admin/content/notices/' . (int) $notice['id']) }}"
                                    data-modal="#notice-edit-modal">编辑</button>
                            <form method="post" action="{{ url('admin/content/notices/' . (int) $notice['id'] . '/delete') }}"
                                  data-confirm="确定删除该公告？" style="display:inline">
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

<?php
$noticeForm = static function (string $id, string $action) use ($levels, $__env): void { ?>
    <form class="modal-box" id="{{ $id }}" method="post" action="{{ $action }}">
        <div class="modal-head">
            <h3 data-form-title>站内公告</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <input type="hidden" name="status_present" value="1">
            <div class="field">
                <label class="label">标题</label>
                <input class="input" name="title" required>
            </div>
            <div class="field">
                <label class="label">内容 <span class="hint">支持 HTML</span></label>
                <textarea class="input" name="content" rows="5"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">级别</label>
                    <select class="select" name="level">
                        @foreach ($levels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label">过期时间 <span class="hint">留空为永久</span></label>
                    <input class="input" type="datetime-local" name="expire_at">
                </div>
            </div>
            <div style="display:flex;gap:20px;flex-wrap:wrap">
                <label class="switch"><input type="checkbox" name="status" value="1" checked><span class="track"></span><span>立即展示</span></label>
                <label class="switch"><input type="checkbox" name="pinned" value="1"><span class="track"></span><span>置顶</span></label>
                <label class="switch" title="开启后全站每个页面都会弹出这条公告，用户关闭后不再打扰"><input type="checkbox" name="popup" value="1"><span class="track"></span><span>全站弹窗</span></label>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
<?php }; ?>

<div class="modal" id="notice-modal"><?php $noticeForm('notice-create-form', url('admin/content/notices')); ?></div>
<div class="modal" id="notice-edit-modal"><?php $noticeForm('notice-edit-form', ''); ?></div>
