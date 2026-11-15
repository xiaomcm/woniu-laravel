{{-- 安装向导（Laravel 版）。处理逻辑在 App\Http\Controllers\InstallController --}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装向导 · 蜗牛图床</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #f6f7fb; color: #1f2430;
            font: 14px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            padding: 40px 20px 80px;
        }
        .wrap { max-width: 760px; margin: 0 auto; }
        .head { display: flex; align-items: center; gap: 12px; margin-bottom: 26px; }
        .mark {
            width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center;
            background: linear-gradient(135deg, #5b7cfa, #7c5cff); color: #fff; font-weight: 800;
            box-shadow: 0 8px 18px -8px rgba(76, 110, 245, .9);
        }
        h1 { font-size: 20px; margin: 0; letter-spacing: -.02em; }
        .sub { color: #7b8494; font-size: 13px; }
        .card {
            background: #fff; border: 1px solid #e8eaf1; border-radius: 14px;
            box-shadow: 0 1px 2px rgba(16,24,40,.05), 0 8px 24px -16px rgba(16,24,40,.2);
            overflow: hidden; margin-bottom: 18px;
        }
        .card-head { padding: 14px 20px; border-bottom: 1px solid #e8eaf1; font-weight: 650; font-size: 14px; }
        .card-body { padding: 20px; }
        .checks { display: grid; gap: 8px; }
        .check { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px dashed #eef0f6; }
        .check:last-child { border-bottom: none; }
        .ok { color: #12a594; font-weight: 600; }
        .no { color: #e5484d; font-weight: 600; }
        .tips { margin-top: 14px; padding: 10px 12px; border-radius: 10px; background: #fff8e6; border: 1px solid #ffe3a3; font-size: 12px; color: #7a5b00; line-height: 1.7; }
        .tips div + div { margin-top: 2px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        label.lb { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; }
        .field { margin-bottom: 15px; }
        input, select {
            width: 100%; height: 38px; padding: 0 12px; border: 1px solid #dfe2ec;
            border-radius: 10px; font-size: 13px; background: #fff; color: #1f2430;
        }
        input:focus, select:focus { outline: none; border-color: #4c6ef5; box-shadow: 0 0 0 3px #eef1ff; }
        .hint { color: #7b8494; font-size: 12px; margin-top: 5px; }
        button {
            width: 100%; height: 44px; border: none; border-radius: 10px; cursor: pointer;
            background: #4c6ef5; color: #fff; font-size: 14px; font-weight: 600;
            box-shadow: 0 6px 16px -8px rgba(76, 110, 245, .9);
        }
        button:hover { background: #3b5bdb; }
        .alert { padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
        .alert-error { background: #fdecec; color: #e5484d; }
        .alert-ok { background: #e7f8f5; color: #12a594; }
        .alert ul { margin: 6px 0 0; padding-left: 18px; }
        a.btn {
            display: inline-block; padding: 10px 18px; border-radius: 10px;
            background: #4c6ef5; color: #fff; text-decoration: none; font-weight: 600;
        }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div class="mark">L</div>
        <div>
            <h1>蜗牛图床 · 安装向导</h1>
            <div class="sub">Laravel 13 实现</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:40px 24px">
                <div style="font-size:40px;line-height:1">🎉</div>
                <h2 style="margin:10px 0 6px">安装完成</h2>
                <p class="sub" style="margin-bottom:20px">安装向导已自动锁定（install.lock），无法重复执行。您现在可以直接使用站点。</p>
                <a class="btn" href="{{ url('/') }}">进入图床</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($errors !== []): ?>
            <div class="alert alert-error">
                {{ htmlspecialchars($errors[0], ENT_QUOTES) }}
                <?php if (count($errors) > 1): ?>
                    <ul>
                        <?php foreach (array_slice($errors, 1) as $error): ?>
                            <li>{{ htmlspecialchars($error, ENT_QUOTES) }}</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-head">环境检查</div>
            <div class="card-body">
                <div class="checks">
                    <?php foreach ($checks as $label => $passed): ?>
                        <div class="check">
                            <span>{{ htmlspecialchars($label, ENT_QUOTES) }}</span>
                            <span class="{{ $passed ? 'ok' : 'no' }}">{{ $passed ? '通过' : '不满足' }}</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($tips)): ?>
                    <div class="tips">
                        <?php foreach ($tips as $tip): ?>
                            <div>· <?php echo htmlspecialchars($tip, ENT_QUOTES); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="{{ url('install') }}">
            <div class="card">
                <div class="card-head">数据库</div>
                <div class="card-body">
                    <div class="grid">
                        <div class="field">
                            <label class="lb">驱动</label>
                            <select name="driver" id="driver">
                                <option value="mysql" {{ ($oldValues['driver'] ?? 'mysql') === 'mysql' ? 'selected' : '' }}>MySQL / MariaDB</option>
                                <option value="sqlite" {{ ($oldValues['driver'] ?? '') === 'sqlite' ? 'selected' : '' }}>SQLite（免配置）</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="lb">表前缀</label>
                            <input name="prefix" value="{{ $old('prefix') }}" placeholder="留空即可">
                        </div>
                    </div>

                    <div id="mysql-fields">
                        <div class="grid">
                            <div class="field">
                                <label class="lb">主机</label>
                                <input name="host" value="{{ $old('host', '127.0.0.1') }}">
                            </div>
                            <div class="field">
                                <label class="lb">端口</label>
                                <input name="port" value="{{ $old('port', '3306') }}">
                            </div>
                        </div>
                        <div class="grid">
                            <div class="field">
                                <label class="lb">数据库名</label>
                                <input name="database" value="{{ $old('database', 'woniu') }}">
                            </div>
                            <div class="field">
                                <label class="lb">用户名</label>
                                <input name="username" value="{{ $old('username', 'root') }}">
                            </div>
                        </div>
                        <div class="field">
                            <label class="lb">密码</label>
                            <input type="password" name="password" value="{{ $old('password') }}">
                            <div class="hint">数据库不存在时会自动创建</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">站点与管理员</div>
                <div class="card-body">
                    <div class="field">
                        <label class="lb">站点地址</label>
                        <input name="site_url" value="{{ $old('site_url', $detectedUrl) }}">
                        <div class="hint">用于生成图片外链，反代或 CDN 场景请填写实际访问地址</div>
                    </div>
                    <div class="grid">
                        <div class="field">
                            <label class="lb">管理员昵称</label>
                            <input name="admin_name" value="{{ $old('admin_name', 'admin') }}" required>
                        </div>
                        <div class="field">
                            <label class="lb">管理员邮箱</label>
                            <input type="email" name="admin_email" value="{{ $old('admin_email') }}" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="lb">管理员密码</label>
                        <input type="password" name="admin_password" placeholder="至少 5 位" required>
                    </div>
                </div>
            </div>

            <button type="submit" {{ $checksPassed ? '' : 'disabled style="opacity:.5;pointer-events:none"' }}>开始安装</button>
        </form>
    <?php endif; ?>
</div>
<script>
    (function () {
        var driver = document.getElementById('driver');
        var fields = document.getElementById('mysql-fields');
        if (!driver || !fields) { return; }
        function sync() { fields.style.display = driver.value === 'sqlite' ? 'none' : ''; }
        driver.addEventListener('change', sync);
        sync();
    })();
</script>
</body>
</html>
