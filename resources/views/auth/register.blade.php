<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$captcha = $captcha ?? ['enabled' => false, 'kind' => 'none'];
$js = static fn ($v): string => (string) json_encode(
    (string) ($v ?? ''),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<h2>注册账号</h2>
<div class="sub">创建账号后即可开始上传图片</div>

@if (!empty($closed))
    <div class="alert alert-warning">当前站点已关闭注册，请联系管理员。</div>
    <a class="btn btn-block" href="{{ url('login') }}">去登录</a>
@else
    @if (!empty($error))
        <div class="alert alert-error">{{ $error }}</div>
    @endif

    <form method="post" action="{{ url('register') }}" data-once="1">
        {!! csrf_field() !!}        <div class="field">
            <label class="label" for="name">用户名</label>
            <input class="input" id="name" name="name" maxlength="24" placeholder="2-24 个字符，唯一且注册后不可修改" required autofocus>
            <p class="hint" style="margin:6px 0 0">不允许使用 admin、root 之类的保留词</p>
        </div>
        <div class="field">
            <label class="label" for="email">邮箱</label>
            <input class="input" id="email" type="email" name="email" placeholder="you@example.com" required>
        </div>

        @if (\App\Core\Setting::bool('register_email_verify', false))
            <div class="field">
                <label class="label" for="email_code">邮箱验证码</label>
                <div style="display:flex;gap:8px">
                    <input class="input" id="email_code" name="email_code" inputmode="numeric" maxlength="6" placeholder="6 位验证码" required style="flex:1">
                    <button class="btn" type="button" id="send-code-btn" style="height:38px;white-space:nowrap">获取验证码</button>
                </div>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('send-code-btn');
                var email = document.getElementById('email');
                var token = '{{ csrf_token() }}';
                var timer = null;
                btn.addEventListener('click', function () {
                    if (btn.disabled) { return; }
                    if (!email.value || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) {
                        alert('请先填写正确的邮箱'); email.focus(); return;
                    }
                    btn.disabled = true;
                    var fd = new FormData();
                    fd.append('_token', token);
                    fd.append('email', email.value);
                    fetch('{{ url('register/code') }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            alert(j.message || (j.status ? '验证码已发送' : '发送失败'));
                            if (!j.status) { btn.disabled = false; return; }
                            var left = 60;
                            btn.textContent = left + ' 秒后重发';
                            timer = setInterval(function () {
                                left--;
                                if (left <= 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '获取验证码'; return; }
                                btn.textContent = left + ' 秒后重发';
                            }, 1000);
                        })
                        .catch(function () { alert('网络错误，请重试'); btn.disabled = false; });
                });
            })();
            </script>
        @endif

        {{-- 注册验证码：由后台「系统设置 · 注册验证码」决定形态 --}}
        @if (($captcha['enabled'] ?? false) && ($captcha['kind'] ?? 'none') === 'image')
            <div class="field">
                <label class="label" for="captcha_code">图形验证码</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input class="input" id="captcha_code" name="captcha_code" maxlength="8" placeholder="输入图中字符，不区分大小写" required style="flex:1" autocomplete="off">
                    <img id="captcha-img" src="{{ url('captcha/image') }}" alt="验证码" title="点击换一张" style="height:38px;border-radius:8px;cursor:pointer;border:1px solid var(--border)">
                </div>
                <p class="hint" style="margin:6px 0 0">看不清？点击图片换一张</p>
            </div>
            <script>
            (function () {
                var img = document.getElementById('captcha-img');
                img.addEventListener('click', function () {
                    img.src = '{{ url('captcha/image') }}' + '?t=' + Date.now();
                });
            })();
            </script>
        @elseif (($captcha['enabled'] ?? false) && ($captcha['kind'] ?? 'none') === 'slide' && ($captcha['provider'] ?? '') === 'tencent')
            <div class="field">
                <label class="label">人机验证</label>
                <input type="hidden" name="captcha_ticket" id="captcha_ticket">
                <input type="hidden" name="captcha_randstr" id="captcha_randstr">
                <button class="btn btn-block" type="button" id="tencent-captcha-btn">点击完成滑块验证</button>
                <p class="hint" id="captcha-tip" style="margin:6px 0 0">未完成验证</p>
            </div>
            <script src="https://ssl.captcha.qq.com/TCaptcha.js"></script>
            <script>
            (function () {
                var appId = {!! $js($captcha['app_id'] ?? '') !!};
                var btn = document.getElementById('tencent-captcha-btn');
                var tip = document.getElementById('captcha-tip');
                var ticket = document.getElementById('captcha_ticket');
                var rand = document.getElementById('captcha_randstr');
                btn.addEventListener('click', function () {
                    if (typeof TencentCaptcha === 'undefined') {
                        tip.textContent = '验证码组件加载失败，请刷新页面重试'; tip.style.color = '#dc2626'; return;
                    }
                    new TencentCaptcha(appId, function (res) {
                        if (res.ret === 0 && res.ticket) {
                            ticket.value = res.ticket;
                            rand.value = res.randstr || '';
                            tip.textContent = '验证通过，可以提交注册了'; tip.style.color = '#16a34a';
                            btn.textContent = '✓ 已完成验证';
                        } else {
                            tip.textContent = '未完成验证，请重试'; tip.style.color = '#dc2626';
                        }
                    }).show();
                });
            })();
            </script>
        @elseif (($captcha['enabled'] ?? false) && ($captcha['kind'] ?? 'none') === 'slide' && ($captcha['provider'] ?? '') === 'aliyun')
            <div class="field">
                <label class="label">人机验证</label>
                <input type="hidden" name="captcha_verify_param" id="captcha_verify_param">
                <div id="aliyun-captcha-box"></div>
                <button class="btn btn-block" type="button" id="aliyun-captcha-btn">点击完成滑块验证</button>
                <p class="hint" id="captcha-tip" style="margin:6px 0 0">未完成验证</p>
            </div>
            <script>window.AliyunCaptchaConfig = { region: {!! $js($captcha['region'] ?? 'cn-hangzhou') !!} };</script>
            <script src="https://o.alicdn.com/captcha-frontend/aliyunCaptcha/AliyunCaptcha.js"></script>
            <script>
            (function () {
                var token = document.getElementById('captcha_verify_param');
                var btn = document.getElementById('aliyun-captcha-btn');
                var tip = document.getElementById('captcha-tip');
                if (typeof initAliyunCaptcha === 'undefined') {
                    tip.textContent = '验证码组件加载失败，请刷新页面重试'; tip.style.color = '#dc2626'; return;
                }
                initAliyunCaptcha({
                    SceneId: {!! $js($captcha['scene_id'] ?? '') !!},
                    prefix: {!! $js($captcha['prefix'] ?? '') !!},
                    mode: 'popup',
                    element: '#aliyun-captcha-box',
                    button: '#aliyun-captcha-btn',
                    captchaVerifyCallback: async function (captchaVerifyParam) {
                        token.value = captchaVerifyParam;
                        tip.textContent = '验证通过，可以提交注册了'; tip.style.color = '#16a34a';
                        btn.textContent = '✓ 已完成验证';
                        return { captchaResult: true };
                    },
                    onBizResultCallback: function () {}
                });
            })();
            </script>
        @elseif (($captcha['enabled'] ?? false) && ($captcha['kind'] ?? 'none') === 'sms')
            <div class="field">
                <label class="label" for="captcha-phone">手机号</label>
                <input class="input" id="captcha-phone" name="phone" placeholder="用于接收短信验证码" required autocomplete="tel">
            </div>
            <div class="field">
                <label class="label" for="captcha_sms_code">短信验证码</label>
                <div style="display:flex;gap:8px">
                    <input class="input" id="captcha_sms_code" name="captcha_sms_code" inputmode="numeric" maxlength="6" placeholder="6 位验证码" required style="flex:1" autocomplete="off">
                    <button class="btn" type="button" id="captcha-sms-btn" style="height:38px;white-space:nowrap">获取验证码</button>
                </div>
                <p class="hint" id="captcha-sms-tip" style="margin:6px 0 0"></p>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('captcha-sms-btn');
                var phone = document.getElementById('captcha-phone');
                var tip = document.getElementById('captcha-sms-tip');
                var token = '{{ csrf_token() }}';
                var timer = null;
                btn.addEventListener('click', function () {
                    if (btn.disabled) { return; }
                    var value = (phone.value || '').trim();
                    if (!/^\+?[0-9][0-9\-]{5,19}$/.test(value)) {
                        tip.textContent = '请先填写正确的手机号'; tip.style.color = '#dc2626'; phone.focus(); return;
                    }
                    btn.disabled = true;
                    var fd = new FormData();
                    fd.append('_token', token);
                    fd.append('phone', value);
                    fetch('{{ url('captcha/sms') }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            tip.textContent = j.message || (j.status ? '验证码已发送' : '发送失败');
                            tip.style.color = j.status ? '#16a34a' : '#dc2626';
                            if (!j.status) { btn.disabled = false; return; }
                            var left = 60;
                            btn.textContent = left + ' 秒后重发';
                            timer = setInterval(function () {
                                left--;
                                if (left <= 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '获取验证码'; return; }
                                btn.textContent = left + ' 秒后重发';
                            }, 1000);
                        })
                        .catch(function () {
                            tip.textContent = '网络错误，请重试'; tip.style.color = '#dc2626'; btn.disabled = false;
                        });
                });
            })();
            </script>
        @endif

        <div class="field">
            <label class="label" for="password">密码</label>
            <input class="input" id="password" type="password" name="password" placeholder="至少 6 位" required>
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">确认密码</label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation" required>
        </div>

        @if ((string) \App\Core\Setting::get('register_invite', '') !== '')
            <div class="field">
                <label class="label">邀请码</label>
                <input class="input" name="invite" placeholder="请输入邀请码" required>
            </div>
        @endif

        @if ((string) \App\Core\Setting::get('register_agree_terms', '1') === '1')
            <div class="field">
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.6;cursor:pointer">
                    <input type="checkbox" name="agree_terms" value="1" required style="margin-top:3px;flex:none">
                    <span>我已阅读并同意 <a href="{{ url('p/terms') }}" target="_blank" rel="noopener">《服务条款》</a> 和 <a href="{{ url('p/privacy') }}" target="_blank" rel="noopener">《隐私政策》</a></span>
                </label>
            </div>
        @endif

        <button class="btn btn-primary btn-block" type="submit" style="height:42px;margin-top:6px">注册</button>
    </form>

    <div class="auth-foot">已有账号？<a href="{{ url('login') }}">直接登录</a></div>
@endif
