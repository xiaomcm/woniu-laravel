<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="result-page">
    <div class="result-badge wait">
        <svg class="ic" style="width:28px;height:28px"><use href="#i-lock"/></svg>
    </div>

    <h1 class="result-h1">该{{ $kind }}已加密</h1>
    <p class="result-p">「{{ $name }}」需要访问密码，验证后 {!! (int) round((int) setting('share_password_ttl', '7200') / 60) !!} 分钟内再次访问免重复输入。</p>

    <form method="post" action="{{ $action }}" class="result-panel" style="max-width:360px">
        {!! csrf_field() !!}        <div class="field" style="margin:0">
            <label class="label" for="share-password">访问密码</label>
            <input class="input" id="share-password" name="password" type="password"
                   autocomplete="off" required autofocus placeholder="请输入分享密码">
        </div>
        <button class="btn btn-primary btn-block" type="submit" style="margin-top:14px">解锁查看</button>
    </form>

    <div class="result-cta">
        <a class="btn btn-sm btn-ghost" href="{{ $backUrl }}">返回</a>
    </div>
</div>
