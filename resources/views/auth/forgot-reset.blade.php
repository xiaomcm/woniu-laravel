<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<h2>重置密码</h2>
<div class="sub">验证码已发至 {{ $email }}</div>

@if (!empty($error))
    <div class="alert alert-error">{{ $error }}</div>
@endif

<form method="post" action="{{ url('forgot/reset') }}" data-once="1">
    {!! csrf_field() !!}
    <div class="field">
        <label class="label" for="code">邮箱验证码</label>
        <input class="input" id="code" name="code" inputmode="numeric" maxlength="6" placeholder="6 位验证码" required autofocus autocomplete="one-time-code">
        <p class="hint" style="margin:6px 0 0">10 分钟内有效，没收到可以回上一步重新获取</p>
    </div>
    <div class="field">
        <label class="label" for="password">新密码</label>
        <input class="input" id="password" type="password" name="password" minlength="6" placeholder="至少 6 位" required autocomplete="new-password">
    </div>
    <div class="field">
        <label class="label" for="password_confirmation">确认新密码</label>
        <input class="input" id="password_confirmation" type="password" name="password_confirmation" minlength="6" placeholder="再输一次新密码" required autocomplete="new-password">
    </div>
    <button class="btn btn-primary btn-block" type="submit" style="height:42px;margin-top:6px">确认重置</button>
</form>

<div class="auth-foot">
    <a href="{{ url('forgot') }}">重新获取验证码</a> · <a href="{{ url('login') }}">返回登录</a>
</div>
