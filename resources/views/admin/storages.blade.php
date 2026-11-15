<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="toolbar">
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#storage-modal" data-reset-form="#storage-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建存储策略
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>名称</th><th>类型</th><th>访问域名</th><th>图片数</th><th>默认</th><th>状态</th><th></th></tr></thead>
                <tbody>
                @foreach ($storages as $storage)
                    <tr>
                        <td><strong>{{ (string) $storage['name'] }}</strong></td>
                        <td><span class="badge">{{ $types[$storage['type']] ?? $storage['type'] }}</span></td>
                        <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:12px">
                            {{ (string) ($storage['url'] ?: '使用默认地址') }}                        </td>
                        <td>{!! \App\Models\Storage::imageCount((int) $storage['id']) !!}</td>
                        <td>{!! (int) $storage['is_default'] === 1 ? '<span class="badge badge-primary">默认</span>' : '-' !!}</td>
                        <td>
                            <span class="badge {!! (int) $storage['status'] === 1 ? 'badge-success' : 'badge-danger' !!}">
                                {!! (int) $storage['status'] === 1 ? '启用' : '停用' !!}                            </span>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'name'       => $storage['name'],
                                        'type'       => $storage['type'],
                                        'url'        => $storage['url'],
                                        'is_default' => (int) $storage['is_default'],
                                        'status'     => (int) $storage['status'],
                                        'config_array' => $storage['config_array'],
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                    data-fill-target="#storage-edit-form"
                                    data-action="{{ url('admin/storages/' . (int) $storage['id']) }}"
                                    data-modal="#storage-modal">编辑</button>
                            <form method="post" action="{{ url('admin/storages/' . (int) $storage['id'] . '/delete') }}"
                                  data-confirm="确定删除该存储策略？" style="display:inline">
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
$storageForm = static function (string $formId, string $action) use ($types, $__env): void { ?>
    <form class="modal-box wide" id="{{ $formId }}" method="post" action="{{ $action }}" data-storage-form>
        <div class="modal-head">
            <h3 data-form-title>{!! $action === '' ? '新建存储策略' : '编辑存储策略' !!}</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="form-row">
                <div class="field">
                    <label class="label">名称</label>
                    <input class="input" name="name" placeholder="例如：阿里云 OSS" required>
                </div>
                <div class="field">
                    <label class="label">存储类型</label>
                    <select class="select" name="type">
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div data-config-for="local">
                <div class="field">
                    <label class="label">存储目录 <span class="hint">相对项目根目录</span></label>
                    <input class="input" name="config[root]" value="storage/uploads">
                </div>
            </div>

            <div data-config-for="s3">
                <div class="form-row">
                    <div class="field"><label class="label">Endpoint</label><input class="input" name="config[endpoint]" placeholder="https://s3.us-east-1.amazonaws.com"></div>
                    <div class="field"><label class="label">Bucket</label><input class="input" name="config[bucket]"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">Region</label><input class="input" name="config[region]" placeholder="us-east-1"></div>
                    <div class="field"><label class="label">AccessKey</label><input class="input" name="config[key]"></div>
                </div>
                <div class="field"><label class="label">SecretKey</label><input class="input" name="config[secret]" type="password"></div>
                <label class="switch"><input type="checkbox" name="config[path_style]" value="1"><span class="track"></span><span>使用 Path-Style（MinIO / R2 常用）</span></label>
            </div>

            <div data-config-for="oss">
                <div class="form-row">
                    <div class="field"><label class="label">Endpoint</label><input class="input" name="config[endpoint]" placeholder="oss-cn-hangzhou.aliyuncs.com"></div>
                    <div class="field"><label class="label">Bucket</label><input class="input" name="config[bucket]"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">AccessKeyId</label><input class="input" name="config[key]"></div>
                    <div class="field"><label class="label">AccessKeySecret</label><input class="input" type="password" name="config[secret]"></div>
                </div>
            </div>

            <div data-config-for="cos">
                <div class="form-row">
                    <div class="field"><label class="label">Bucket</label><input class="input" name="config[bucket]" placeholder="bucket-1250000000"></div>
                    <div class="field"><label class="label">Region</label><input class="input" name="config[region]" placeholder="ap-guangzhou"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">SecretId</label><input class="input" name="config[key]"></div>
                    <div class="field"><label class="label">SecretKey</label><input class="input" type="password" name="config[secret]"></div>
                </div>
            </div>

            <div data-config-for="qiniu">
                <div class="form-row">
                    <div class="field"><label class="label">Bucket</label><input class="input" name="config[bucket]"></div>
                    <div class="field"><label class="label">访问域名</label><input class="input" name="config[domain]" placeholder="https://cdn.example.com"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">AccessKey</label><input class="input" name="config[key]"></div>
                    <div class="field"><label class="label">SecretKey</label><input class="input" type="password" name="config[secret]"></div>
                </div>
                <div class="field"><label class="label">上传地址</label><input class="input" name="config[upload_url]" value="https://upload.qiniup.com"></div>
            </div>

            <div data-config-for="upyun">
                <div class="form-row">
                    <div class="field"><label class="label">Bucket（空间名）</label><input class="input" name="config[bucket]"></div>
                    <div class="field"><label class="label">Operator</label><input class="input" name="config[operator]"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">Password</label><input class="input" type="password" name="config[password]"></div>
                    <div class="field"><label class="label">访问域名</label><input class="input" name="config[domain]" placeholder="https://xxx.test.upcdn.net"></div>
                </div>
            </div>

            <div data-config-for="ftp">
                <div class="form-row">
                    <div class="field"><label class="label">主机</label><input class="input" name="config[host]"></div>
                    <div class="field"><label class="label">端口</label><input class="input" name="config[port]" value="21"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">用户名</label><input class="input" name="config[username]"></div>
                    <div class="field"><label class="label">密码</label><input class="input" type="password" name="config[password]"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">根目录</label><input class="input" name="config[root]" placeholder="/www/uploads"></div>
                    <div class="field"><label class="label">加密</label><br>
                        <label class="switch"><input type="checkbox" name="config[ssl]" value="1"><span class="track"></span><span>FTPS</span></label>
                    </div>
                </div>
            </div>

            <div data-config-for="webdav">
                <div class="field"><label class="label">WebDAV 地址</label><input class="input" name="config[url]" placeholder="https://dav.example.com/remote.php/dav/files/user"></div>
                <div class="form-row">
                    <div class="field"><label class="label">用户名</label><input class="input" name="config[username]"></div>
                    <div class="field"><label class="label">密码</label><input class="input" type="password" name="config[password]"></div>
                </div>
            </div>

            <div data-config-for="sftp">
                <div class="form-row">
                    <div class="field"><label class="label">主机</label><input class="input" name="config[host]" placeholder="sftp.example.com"></div>
                    <div class="field"><label class="label">端口</label><input class="input" name="config[port]" value="22"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">用户名</label><input class="input" name="config[username]"></div>
                    <div class="field"><label class="label">密码</label><input class="input" type="password" name="config[password]"></div>
                </div>
                <div class="field"><label class="label">根目录</label><input class="input" name="config[root]" placeholder="/var/www/uploads"></div>
            </div>

            <div data-config-for="minio">
                <div class="form-row">
                    <div class="field"><label class="label">Endpoint</label><input class="input" name="config[endpoint]" placeholder="http://localhost:9000"></div>
                    <div class="field"><label class="label">Bucket</label><input class="input" name="config[bucket]"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label class="label">Region</label><input class="input" name="config[region]" placeholder="us-east-1"></div>
                    <div class="field"><label class="label">AccessKey</label><input class="input" name="config[key]"></div>
                </div>
                <div class="field"><label class="label">SecretKey</label><input class="input" name="config[secret]" type="password"></div>
                <p class="hint">MinIO 默认使用 Path-Style 寻址，无需额外配置。</p>
            </div>

            <div class="field">
                <label class="label">自定义访问域名 <span class="hint">CDN 域名，留空用默认</span></label>
                <input class="input" name="url" placeholder="https://img.example.com">
            </div>

            <div style="display:flex;gap:20px;flex-wrap:wrap">
                <label class="switch"><input type="checkbox" name="is_default" value="1"><span class="track"></span><span>设为默认</span></label>
                <label class="switch"><input type="checkbox" name="status" value="1" checked><span class="track"></span><span>启用</span></label>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-storage-test>
                <svg class="ic" style="width:14px;height:14px"><use href="#i-refresh"/></svg> 测试连接
            </button>
            <div style="flex:1"></div>
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
<?php }; ?>

<div class="modal" id="storage-modal">
    <?php $storageForm('storage-create-form', url('admin/storages')); ?>
</div>
<div class="modal" id="storage-edit-modal">
    <?php $storageForm('storage-edit-form', ''); ?>
</div>
