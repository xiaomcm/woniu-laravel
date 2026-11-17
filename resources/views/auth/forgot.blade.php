<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<h2>找回密码</h2>
<div class="sub">输入注册邮箱，我们会发送验证码帮你重设密码</div>

@if (!empty($error))
    <div class="alert alert-error">{{ $error }}</div>
@endif

<form method="post" action="{{ url('forgot/send') }}" data-forgot-form data-once="1">
    {!! csrf_field() !!}
    <div class="field">
        <label class="label" for="forgot-email">注册邮箱</label>
        <input class="input" id="forgot-email" type="email" name="email" placeholder="you@example.com" required autofocus autocomplete="email">
    </div>
    <div id="forgot-result" class="hint" style="min-height:20px;margin:6px 0"></div>
    <button class="btn btn-primary btn-block" type="submit" style="height:42px;margin-top:6px">发送验证码</button>
</form>

<div class="auth-foot">
    <a href="{{ url('login') }}">返回登录</a>
</div>

<script>
(function () {
    var form = document.querySelector('[data-forgot-form]');
    var out = document.getElementById('forgot-result');
    if (!form) { return; }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var email = (document.getElementById('forgot-email').value || '').trim();
        if (!email) { return; }
        var btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        out.textContent = '正在发送…';
        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            out.textContent = j.message || '';
            out.style.color = j.status ? '#16a34a' : '#dc2626';
            if (j.status) {
                // 发码成功就带着邮箱跳到重置页，验证码填在那里
                setTimeout(function () {
                    location.href = '{{ url('forgot/reset') }}';
                }, 900);
            } else {
                btn.disabled = false;
            }
        })
        .catch(function () {
            out.textContent = '发送失败，请稍后重试';
            out.style.color = '#dc2626';
            btn.disabled = false;
        });
    });
})();
</script>
