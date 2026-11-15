<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="toolbar">
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#group-modal" data-reset-form="#group-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建用户组
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>名称</th><th>容量</th><th>单文件上限</th><th>允许格式</th>
                    <th>默认存储</th><th>单次数量</th><th>每日上限</th><th>用户数</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($groups as $group)
                    <tr>
                        <td>
                            <strong>{{ (string) $group['name'] }}</strong>
                            @if ((int) $group['is_default'] === 1)<span class="badge badge-primary">默认</span>@endif
                        </td>
                        <td>{!! (int) $group['capacity'] > 0 ? e(format_bytes((int) $group['capacity'])) : '不限' !!}</td>
                        <td>{!! (int) $group['max_file_size'] > 0 ? e(format_bytes((int) $group['max_file_size'])) : '不限' !!}</td>
                        <td style="max-width:240px;font-size:12px;color:var(--muted)">{{ (string) $group['allowed_extensions'] }}</td>
                        <td>
                            <?php
                            $storageName = '-';
                            foreach ($storages as $storage) {
                                if ((int) $storage['id'] === (int) ($group['default_storage_id'] ?? 0)) {
                                    $storageName = (string) $storage['name'];
                                }
                            }
                            echo e($storageName);
                            ?>
                        </td>
                        <td>{!! (int) $group['max_upload_num'] !!}</td>
                        <td>{!! (int) ($group['daily_limit'] ?? 0) > 0 ? (int) $group['daily_limit'] : '不限' !!}</td>
                        <td>{!! \App\Models\Group::userCount((int) $group['id']) !!}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'name'               => $group['name'],
                                        'capacity_mb'        => round((int) $group['capacity'] / 1048576, 2),
                                        'max_file_size_mb'   => round((int) $group['max_file_size'] / 1048576, 2),
                                        'allowed_extensions' => $group['allowed_extensions'],
                                        'default_storage_id' => (int) ($group['default_storage_id'] ?? 0),
                                        'max_upload_num'     => (int) $group['max_upload_num'],
                                        'daily_limit'        => (int) ($group['daily_limit'] ?? 0),
                                        'is_default'         => (int) $group['is_default'],
                                        'watermark'          => (int) $group['watermark'],
                                    ] + array_combine(
                                        array_map(static fn ($k) => 'perm_' . $k, array_keys(\App\Models\Group::permissions($group))),
                                        // 转成 1/0：布尔值塞进 JSON 后，前端 parseInt(true) 会得到 NaN，
                                        // 开关会被判成「关闭」，编辑一次就把权限清光
                                        array_map(static fn ($v) => $v ? 1 : 0, array_values(\App\Models\Group::permissions($group)))
                                    ), JSON_UNESCAPED_UNICODE) }}"
                                    data-fill-target="#group-edit-form"
                                    data-action="{{ url('admin/groups/' . (int) $group['id']) }}"
                                    data-title="编辑用户组"
                                    data-modal="#group-edit-modal">编辑</button>
                            <form method="post" action="{{ url('admin/groups/' . (int) $group['id'] . '/delete') }}"
                                  data-confirm="确定删除该用户组？" style="display:inline">
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
$groupForm = static function (string $id, string $action) use ($storages, $__env): void { ?>
    <form class="modal-box" id="{{ $id }}" method="post" action="{{ $action }}">
        <div class="modal-head">
            <h3 data-form-title>{!! $action === '' ? '新建用户组' : '编辑用户组' !!}</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="field">
                <label class="label">名称</label>
                <input class="input" name="name" required>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">总容量 <span class="hint">MB，0 为不限</span></label>
                    <input class="input" name="capacity_mb" value="0">
                </div>
                <div class="field">
                    <label class="label">单文件上限 <span class="hint">MB，0 为不限</span></label>
                    <input class="input" name="max_file_size_mb" value="5">
                </div>
            </div>
            <div class="field">
                <label class="label">允许的格式 <span class="hint">逗号分隔</span></label>
                <input class="input" name="allowed_extensions" value="jpg,jpeg,png,gif,bmp,webp,svg,ico">
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">默认存储策略</label>
                    <select class="select" name="default_storage_id">
                        <option value="0">跟随系统默认</option>
                        @foreach ($storages as $storage)
                            <option value="{!! (int) $storage['id'] !!}">{{ (string) $storage['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label">单次上传数量</label>
                    <input class="input" name="max_upload_num" value="10">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">每日上传上限 <span class="hint">张，0 为不限</span></label>
                    <input class="input" name="daily_limit" value="0">
                </div>
                <div class="field">
                    <label class="label">水印</label>
                    <label class="switch" style="height:38px"><input type="checkbox" name="watermark" value="1"><span class="track"></span><span>启用组水印</span></label>
                </div>
            </div>
            <div class="field">
                <label class="label">权限开关</label>
                <div class="perm-grid">
                    @foreach (['upload' => '允许上传', 'album' => '允许相册', 'api' => '允许 API', 'explore' => '展示到广场', 'download' => '允许打包下载', 'watermark' => '继承水印', 'guest' => '游客可用'] as $pKey => $pLabel)
                        <label class="switch">
                            <input type="checkbox" name="perm_{{ $pKey }}" value="1" checked>
                            <span class="track"></span><span>{{ $pLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <label class="switch"><input type="checkbox" name="is_default" value="1"><span class="track"></span><span>设为默认组</span></label>
            <p class="hint" style="margin:6px 0 0">新注册用户自动归入默认组。默认组同样可以改名称、容量、格式、存储与权限，只是不能取消「默认」标记（系统至少保留一个）</p>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
<?php }; ?>

<div class="modal" id="group-modal">
    <?php $groupForm('group-create-form', url('admin/groups')); ?>
</div>

<div class="modal" id="group-edit-modal">
    <?php $groupForm('group-edit-form', ''); ?>
</div>
