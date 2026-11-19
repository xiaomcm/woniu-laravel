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
@if (!empty($ok))
    <div class="alert alert-success">{{ $ok }}</div>
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

@if (count($socials ?? []) > 0)
    <div class="social-login">
        <div class="social-login-title">第三方账号登录</div>
        <div class="social-login-list">
            @foreach ($socials as $social)
                <a class="btn btn-block social-btn" href="{{ url('oauth/' . (int) $social['id']) }}">{{ (string) $social['name'] }}</a>
            @endforeach
        </div>
    </div>
@endif

<div class="auth-foot">
    <a href="{{ url('forgot') }}">忘记密码？</a>
    <span class="dot">·</span><a href="{{ url('register') }}">注册账号</a>
</div>
