<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div class="two-col">
    <div class="card" style="margin-top:0">
        <div class="card-head"><h2>账户资料</h2></div>
        <div class="card-body">
            <form method="post" action="{{ url('settings/profile') }}">
                {!! csrf_field() !!}                <div class="field">
                    <label class="label">用户名</label>
                    <input class="input" value="{{ (string) $user['name'] }}" disabled>
                    <p class="hint" style="margin:6px 0 0">用户名注册后不可修改，登录时可填用户名或邮箱</p>
                </div>
                <div class="field">
                    <label class="label">邮箱</label>
                    <input class="input" type="email" name="email" value="{{ (string) $user['email'] }}" required>
                </div>
                <button class="btn btn-primary" type="submit">保存资料</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:0">
        <div class="card-head"><h2>修改密码</h2></div>
        <div class="card-body">
            <form method="post" action="{{ url('settings/password') }}">
                {!! csrf_field() !!}                <div class="field">
                    <label class="label">原密码</label>
                    <input class="input" type="password" name="old_password" required>
                </div>
                <div class="field">
                    <label class="label">新密码</label>
                    <input class="input" type="password" name="password" placeholder="至少 6 位" required>
                </div>
                <div class="field">
                    <label class="label">确认新密码</label>
                    <input class="input" type="password" name="password_confirmation" required>
                </div>
                <button class="btn btn-primary" type="submit">修改密码</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>容量与用量</h2></div>
    <div class="card-body tight">
        <div class="kv"><span>用户组</span><span>{{ (string) ($group['name'] ?? '默认组') }}</span></div>
        <?php
        $usedBytes = (int) $stats['used'];
        $capBytes = (int) $stats['capacity'];
        $percent = $capBytes > 0 ? min(100, (int) round($usedBytes / $capBytes * 100)) : 0;
        ?>
        <div class="kv">
            <span>存储空间</span>
            <span>{!! $capBytes > 0
                ? e(format_bytes($usedBytes)) . ' / ' . e(format_bytes($capBytes)) . '（' . $percent . '%）'
                : e(format_bytes($usedBytes)) . ' / 不限' !!}</span>
        </div>
        <div class="quota-bar" style="margin:2px 0 8px">
            {{-- 不限容量时也画一小段，直观表示「已用一些、但没有上限」 --}}
            <span style="width: {!! $capBytes > 0 ? $percent . '%' : ($usedBytes > 0 ? '6%' : '0') !!}"></span>
        </div>
        <div class="kv"><span>图片数量</span><span>{!! (int) $stats['images'] !!} 张</span></div>
        <div class="kv">
            <span>单次上传</span>
            <span>{!! (int) ($group['max_upload_num'] ?? 0) > 0 ? (int) $group['max_upload_num'] . ' 张' : '不限' !!}</span>
        </div>
        <div class="kv">
            <span>单文件限制</span>
            <span>{{ format_bytes((int) ($group['max_file_size'] ?? 0) ?: \App\Core\Setting::int('upload_max_size', 0)) }}</span>
        </div>
        <div class="kv"><span>允许格式</span><span>{{ (string) ($group['allowed_extensions'] ?? '') }}</span></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>API 令牌</h2>
        <div class="spacer"></div>
        <button class="btn btn-sm btn-primary" type="button" data-modal-open="#token-modal">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建令牌
        </button>
    </div>
    <div class="card-body">
        @if (!empty($newToken))
            <div class="alert alert-success">
                新令牌（仅显示一次）：<br>
                <code class="inline" style="margin-top:8px;display:inline-block">{{ $newToken }}</code>
                <button class="btn btn-sm" type="button" data-copy="{{ $newToken }}" style="margin-left:8px">复制</button>
            </div>
        @endif

        {{-- 用户自己的 API 总开关：关掉后令牌保留但全部调不通，重新打开即恢复 --}}
        <form method="post" action="{{ url('settings/api-enabled') }}" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--line)">
            {!! csrf_field() !!}
            <label class="switch">
                <input type="checkbox" name="api_enabled" value="1" onchange="this.form.submit()" {!! (int) ($user['api_enabled'] ?? 1) === 1 ? 'checked' : '' !!}>
                <span class="track"></span><span>启用我的 API 接口</span>
            </label>
            <span class="hint">关闭后本账号所有令牌立即停用，令牌不会删除，重新打开即可恢复</span>
            <a class="btn btn-sm" href="{{ url('api-docs') }}" style="margin-left:auto">查看 API 用法</a>
        </form>

        @if ($tokens === [])
            <div class="empty" style="padding:32px 10px">
                <svg class="ic"><use href="#i-shield"/></svg>
                <h3>还没有 API 令牌</h3>
                <div>创建后即可用 <code class="inline">Authorization: Bearer &lt;token&gt;</code> 调用 API，用法见<a href="{{ url('api-docs') }}">接口文档</a></div>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>名称</th>
                        <th>前缀</th>
                        <th>最近使用</th>
                        <th>过期时间</th>
                        <th>创建时间</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($tokens as $token)
                        <tr>
                            <td>{{ (string) $token['name'] }}</td>
                            <td><code class="inline">{{ (string) $token['token_prefix'] }}…</code></td>
                            <td>{{ (string) ($token['last_used_at'] ?? '从未') }}</td>
                            <td>{{ (string) ($token['expires_at'] ?? '永久') }}</td>
                            <td>{{ (string) $token['created_at'] }}</td>
                            <td style="text-align:right">
                                <form method="post" action="{{ url('settings/tokens/' . (int) $token['id'] . '/delete') }}"
                                      data-confirm="确定删除该令牌？使用它的程序将立即失效。">
                                    {!! csrf_field() !!}                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="form-help" style="margin-top:14px">
            调用示例：<code class="inline">curl -H "Authorization: Bearer 你的令牌" -F "files[]=@a.png" {{ url('api/v1/upload') }}</code>
        </div>
    </div>
</div>

<div class="modal" id="token-modal">
    <form class="modal-box" method="post" action="{{ url('settings/tokens') }}">
        <div class="modal-head">
            <h3>新建 API 令牌</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <div class="field">
                <label class="label">名称</label>
                <input class="input" name="name" placeholder="例如：PicGo / 图床客户端" maxlength="40">
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="label">过期时间 <span class="hint">留空为永久</span></label>
                <input class="input" type="date" name="expires_at">
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">创建</button>
        </div>
    </form>
</div>
