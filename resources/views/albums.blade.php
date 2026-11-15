<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="toolbar">
    <div class="spacer"></div>
    <button class="btn btn-primary btn-sm" type="button" data-modal-open="#album-modal" data-reset-form="#album-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建相册
    </button>
</div>

<div class="card">
    <div class="card-head">
        <h2>我的相册 <span class="badge" style="margin-left:6px">{!! count($albums) !!}</span></h2>
    </div>
    <div class="card-body">
        @if ($albums === [])
            <div class="empty">
                <svg class="ic"><use href="#i-album"/></svg>
                <h3>还没有相册</h3>
                <div>新建相册，把图片按主题归类</div>
            </div>
        @else
            <div class="album-grid">
                @foreach ($albums as $album)
                    <div class="album-card">
                        <a class="album-cover" href="{{ url('albums/' . (int) $album['id']) }}">
                            @if (!empty($album['cover']))
                                <img src="{{ (string) $album['cover'] }}" alt=""{{ lazy_attr() }}>
                            @else
                                <div class="placeholder"><svg class="ic" style="width:32px;height:32px"><use href="#i-image"/></svg></div>
                            @endif
                        </a>
                        <div class="album-body">
                            <a class="album-name" href="{{ url('albums/' . (int) $album['id']) }}">{{ (string) $album['name'] }}</a>
                            <div class="album-sub">
                                <span>{!! (int) $album['image_num'] !!} 张</span>
                                <span>{{ substr((string) $album['created_at'], 0, 10) }}</span>
                                @if ((int) $album['is_public'] === 1)
                                    <span class="badge badge-success">公开</span>
                                @endif
                                @if (trim((string) ($album['password'] ?? '')) !== '')
                                    <span class="badge badge-warning">已加密</span>
                                @endif
                            </div>
                            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
                                <a class="btn btn-sm" href="{{ url('albums/' . (int) $album['id']) }}">查看</a>
                                <button class="btn btn-sm" type="button"
                                        data-fill="{{ json_encode(['name' => $album['name'], 'intro' => $album['intro'], 'is_public' => (int) $album['is_public']], JSON_UNESCAPED_UNICODE) }}"
                                        data-fill-target="#album-edit-form"
                                        data-action="{{ url('albums/' . (int) $album['id']) }}"
                                        data-modal="#album-edit-modal">编辑</button>
                                @if ((int) $album['is_public'] === 1 && !empty($album['key']))
                                    <button class="btn btn-sm btn-ghost" type="button" data-copy="{{ url('s/' . $album['key']) }}">复制分享</button>
                                @endif
                                <form method="post" action="{{ url('albums/' . (int) $album['id'] . '/delete') }}"
                                      data-confirm="删除后相册内图片会移动到未分类，确定删除？" style="margin-left:auto">
                                    {!! csrf_field() !!}                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- 新建相册 -->
<div class="modal" id="album-modal">
    <form class="modal-box" id="album-create-form" method="post" action="{{ url('albums') }}" data-ajax data-modal="#album-modal">
        <div class="modal-head">
            <h3>新建相册</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="field">
                <label class="label">相册名称</label>
                <input class="input" name="name" maxlength="40" placeholder="例如：产品图 / 壁纸" required>
            </div>
            <div class="field">
                <label class="label">简介 <span class="hint">可选</span></label>
                <textarea class="textarea" name="intro" maxlength="200" style="min-height:64px"></textarea>
            </div>
            <input type="hidden" name="is_public" value="0">
            <label class="switch">
                <input type="checkbox" name="is_public" value="1">
                <span class="track"></span>
                <span>生成公开分享链接</span>
            </label>
            <div class="field" style="margin-top:14px">
                <label class="label">分享密码 <span class="hint">可选，4-32 位</span></label>
                <input class="input" name="password" maxlength="32" autocomplete="new-password"
                       placeholder="留空表示不加密，访客凭链接直接查看">
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">创建</button>
        </div>
    </form>
</div>

<!-- 编辑相册 -->
<div class="modal" id="album-edit-modal">
    <form class="modal-box" id="album-edit-form" method="post" action="" data-ajax data-modal="#album-edit-modal">
        <div class="modal-head">
            <h3>编辑相册</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="field">
                <label class="label">相册名称</label>
                <input class="input" name="name" maxlength="40">
            </div>
            <div class="field">
                <label class="label">简介</label>
                <textarea class="textarea" name="intro" maxlength="200" style="min-height:64px"></textarea>
            </div>
            <input type="hidden" name="is_public" value="0">
            <label class="switch">
                <input type="checkbox" name="is_public" value="1">
                <span class="track"></span>
                <span>公开分享</span>
            </label>
            <div class="field" style="margin-top:14px">
                <label class="label">分享密码 <span class="hint">留空则保持原状</span></label>
                <input class="input" name="password" maxlength="32" autocomplete="new-password"
                       placeholder="重新设置密码，填 4-32 位">
            </div>
            <label class="switch" style="margin-top:12px">
                <input type="checkbox" name="clear_password" value="1">
                <span class="track"></span>
                <span>清除密码（取消加密）</span>
            </label>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
</div>
