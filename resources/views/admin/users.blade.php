<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="toolbar">
    <form method="get" action="{{ url('admin/users') }}" class="toolbar" style="margin:0;gap:8px">
        <input class="input input-sm" style="width:200px" name="keyword" value="{{ $keyword }}" placeholder="昵称 / 邮箱">
        <button class="btn btn-sm" type="submit">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-search"/></svg> 搜索
        </button>
    </form>
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#user-modal" data-reset-form="#user-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建用户
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>ID</th><th>用户</th><th>用户组</th><th>图片</th><th>已用</th><th>状态</th><th>注册时间</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{!! (int) $user['id'] !!}</td>
                        <td>
                            <div style="font-weight:600">{{ (string) $user['name'] }}                                @if ((int) $user['is_admin'] === 1)<span class="badge badge-primary">管理员</span>@endif
                            </div>
                            <div style="font-size:12px;color:var(--muted)">{{ (string) $user['email'] }}</div>
                        </td>
                        <td>{{ (string) ($user['group_name'] ?? '-') }}</td>
                        <td>{!! (int) \App\Core\Database::value('SELECT COUNT(*) FROM ' . \App\Core\Database::table('images') . ' WHERE user_id = :u AND deleted_at IS NULL', ['u' => (int) $user['id']]) !!}</td>
                        <td>{{ format_bytes(\App\Models\User::usedBytes((int) $user['id'])) }}</td>
                        <td>
                            <span class="badge {!! (int) $user['status'] === 1 ? 'badge-success' : 'badge-danger' !!}">
                                {!! (int) $user['status'] === 1 ? '正常' : '禁用' !!}                            </span>
                        </td>
                        <td style="white-space:nowrap">{{ (string) $user['created_at'] }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'name'        => $user['name'],
                                        'email'       => $user['email'],
                                        'group_id'    => $user['group_id'],
                                        'capacity_mb' => $user['capacity'] === null ? '' : round($user['capacity'] / 1048576, 2),
                                        'status'      => (int) $user['status'],
                                        'is_admin'    => (int) $user['is_admin'],
                                    ], JSON_UNESCAPED_UNICODE) }}"
                                    data-fill-target="#user-edit-form"
                                    data-action="{{ url('admin/users/' . (int) $user['id']) }}"
                                    data-modal="#user-edit-modal">编辑</button>
                            <form method="post" action="{{ url('admin/users/' . (int) $user['id'] . '/delete') }}"
                                  data-confirm="确定删除用户「{{ (string) $user['name'] }}」？其图片记录会保留。" style="display:inline">
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

{!! paginate_links((int) $pager['page'], (int) $pager['last_page'], ['keyword' => $keyword]) !!}
<div class="modal" id="user-modal">
    <form class="modal-box" id="user-create-form" method="post" action="{{ url('admin/users') }}">
        <div class="modal-head">
            <h3>新建用户</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="form-row">
                <div class="field"><label class="label">用户名</label><input class="input" name="name" required></div>
                <div class="field"><label class="label">邮箱</label><input class="input" type="email" name="email" required></div>
            </div>
            <div class="form-row">
                <div class="field"><label class="label">密码</label><input class="input" type="password" name="password" required></div>
                <div class="field"><label class="label">用户组</label>
                    <select class="select" name="group_id">
                        @foreach ($groups as $group)
                            <option value="{!! (int) $group['id'] !!}">{{ (string) $group['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field"><label class="label">独立容量 <span class="hint">MB，留空继承组</span></label>
                    <input class="input" name="capacity_mb" placeholder="如 1024">
                </div>
                <div class="field"><label class="label">管理员</label><br>
                    <label class="switch"><input type="checkbox" name="is_admin" value="1"><span class="track"></span><span>授予后台权限</span></label>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">创建</button>
        </div>
    </form>
</div>

<div class="modal" id="user-edit-modal">
    <form class="modal-box" id="user-edit-form" method="post" action="">
        <div class="modal-head">
            <h3>编辑用户</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="form-row">
                <div class="field">
                    <label class="label">用户名 <span class="hint">注册后不可修改</span></label>
                    <input class="input" name="name" disabled>
                </div>
                <div class="field"><label class="label">邮箱</label><input class="input" type="email" name="email"></div>
            </div>
            <div class="form-row">
                <div class="field"><label class="label">新密码 <span class="hint">留空不改</span></label><input class="input" type="password" name="password"></div>
                <div class="field"><label class="label">用户组</label>
                    <select class="select" name="group_id">
                        @foreach ($groups as $group)
                            <option value="{!! (int) $group['id'] !!}">{{ (string) $group['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field"><label class="label">独立容量 <span class="hint">MB，留空继承组</span></label>
                    <input class="input" name="capacity_mb">
                </div>
                <div class="field"><label class="label">状态</label><br>
                    <input type="hidden" name="status" value="0">
                    <label class="switch"><input type="checkbox" name="status" value="1"><span class="track"></span><span>账号可用</span></label>
                </div>
            </div>
            <input type="hidden" name="is_admin" value="0">
            <label class="switch"><input type="checkbox" name="is_admin" value="1"><span class="track"></span><span>管理员权限</span></label>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
</div>
