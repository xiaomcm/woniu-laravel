<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="toolbar">
    <span class="hint">页面地址形如 <code>/p/about</code></span>
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#page-modal" data-reset-form="#page-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建页面
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>标题</th><th>路径</th><th>页脚</th><th>排序</th><th>状态</th><th>更新时间</th><th></th></tr>
                </thead>
                <tbody>
                @if ($pages === [])
                    <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:34px">还没有独立页面</td></tr>
                @endif
                @foreach ($pages as $page)
                    <tr>
                        <td><strong>{{ (string) $page['title'] }}</strong></td>
                        <td><code style="font-size:12px">/p/{{ (string) $page['slug'] }}</code></td>
                        <td>{!! (int) $page['show_in_footer'] === 1 ? '显示' : '隐藏' !!}</td>
                        <td>{!! (int) $page['sortable'] !!}</td>
                        <td>
                            @if ((int) $page['status'] === 1)
                                <span class="badge badge-success">已发布</span>
                            @else
                                <span class="badge">草稿</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--muted)">{{ (string) ($page['updated_at'] ?? $page['created_at']) }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <a class="btn btn-sm" href="{{ url('p/' . (string) $page['slug']) }}" target="_blank">预览</a>
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'title'          => $page['title'],
                                        'slug'           => $page['slug'],
                                        'content'        => $page['content'],
                                        'sortable'       => (int) $page['sortable'],
                                        'status_present' => 1,
                                        'status'         => (int) $page['status'],
                                        'show_in_footer' => (int) $page['show_in_footer'],
                                    ], JSON_UNESCAPED_UNICODE) }}"
                                    data-fill-target="#page-edit-form"
                                    data-action="{{ url('admin/content/pages/' . (int) $page['id']) }}"
                                    data-modal="#page-edit-modal">编辑</button>
                            <form method="post" action="{{ url('admin/content/pages/' . (int) $page['id'] . '/delete') }}"
                                  data-confirm="确定删除该页面？" style="display:inline">
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
$pageForm = static function (string $id, string $action) use ($__env): void { ?>
    <form class="modal-box" id="{{ $id }}" method="post" action="{{ $action }}" style="max-width:720px">
        <div class="modal-head">
            <h3 data-form-title>独立页面</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <input type="hidden" name="status_present" value="1">
            <div class="form-row">
                <div class="field">
                    <label class="label">页面标题</label>
                    <input class="input" name="title" required>
                </div>
                <div class="field">
                    <label class="label">访问路径 <span class="hint">英文、数字、短横线</span></label>
                    <input class="input" name="slug" placeholder="about">
                </div>
            </div>
            <div class="field">
                <label class="label">正文 <span class="hint">支持 HTML</span></label>
                <textarea class="input" name="content" rows="12" placeholder="<h1>关于我们</h1><p>……</p>"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">排序</label>
                    <input class="input" name="sortable" value="0">
                </div>
                <div class="field">
                    <label class="label">选项</label>
                    <div style="display:flex;gap:18px;align-items:center;height:38px;flex-wrap:wrap">
                        <label class="switch"><input type="checkbox" name="status" value="1" checked><span class="track"></span><span>发布</span></label>
                        <label class="switch"><input type="checkbox" name="show_in_footer" value="1" checked><span class="track"></span><span>页脚显示</span></label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
<?php }; ?>

<div class="modal" id="page-modal"><?php $pageForm('page-create-form', url('admin/content/pages')); ?></div>
<div class="modal" id="page-edit-modal"><?php $pageForm('page-edit-form', ''); ?></div>
