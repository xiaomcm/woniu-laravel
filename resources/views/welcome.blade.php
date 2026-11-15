<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

?>
<div style="margin-bottom:22px">
    <h2>欢迎使用{{ (string) ($siteSettings['site_name'] ?? '蜗牛图床') }}</h2>
    <div class="sub">登录后即可上传、管理并分享你的图片</div>
</div>

<div style="display:grid;gap:10px">
    <a class="btn btn-primary btn-block" href="{{ url('login') }}" style="height:42px">
        <svg class="ic" style="width:16px;height:16px"><use href="#i-logout"/></svg> 登录账号
    </a>
    @if (\App\Core\Setting::bool('register_enabled', true))
        <a class="btn btn-block" href="{{ url('register') }}" style="height:42px">注册新账号</a>
    @endif
    @if (\App\Core\Setting::bool('explore_enabled', true))
        <a class="btn btn-ghost btn-block" href="{{ url('explore') }}" style="height:42px">
            <svg class="ic" style="width:16px;height:16px"><use href="#i-compass"/></svg> 逛逛发现广场
        </a>
    @endif
</div>

<div style="margin-top:26px;display:grid;gap:12px">
    <div class="kv"><span>存储策略</span><span>{!! count(\App\Models\Storage::enabled()) !!} 个</span></div>
    <div class="kv"><span>已收录图片</span><span>{!! \App\Models\Image::countAll() !!} 张</span></div>
    <div class="kv"><span>注册用户</span><span>{!! \App\Models\User::countAll() !!} 人</span></div>
</div>

<div class="auth-foot">
    接口文档：<code class="inline">POST /api/v1/upload</code> · <code class="inline">Authorization: Bearer &lt;token&gt;</code>
</div>
