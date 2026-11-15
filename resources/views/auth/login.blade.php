<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<h2>登录</h2>
<div class="sub">使用邮箱和密码登录你的账号</div>

@if (!empty($error))
    <div class="alert alert-error">{{ $error }}</div>
@endif

<form method="post" action="{{ url('login') }}" data-once="1">
    {!! csrf_field() !!}    <input type="hidden" name="redirect" value="{{ $redirect ?? '/' }}">

    <div class="field">
        <label class="label" for="account">用户名 / 邮箱</label>
        <input class="input" id="account" name="account" placeholder="用户名或注册邮箱" required autofocus autocomplete="username">
    </div>
    <div class="field">
        <label class="label" for="password">密码</label>
        <input class="input" id="password" type="password" name="password" placeholder="••••••••" required>
    </div>

    <button class="btn btn-primary btn-block" type="submit" style="height:42px;margin-top:6px">登录</button>
</form>

@if (\App\Core\Setting::bool('register_enabled', true))
    <div class="auth-foot">
        还没有账号？<a href="{{ url('register') }}">立即注册</a>
    </div>
@endif
