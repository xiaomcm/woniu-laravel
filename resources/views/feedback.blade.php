<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$kinds = $kinds ?? [];
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">意见反馈</h1>
        <p class="explore-sub">功能建议、体验问题、走不通的流程，都可以直接写在这里</p>
    </div>
</div>

<div class="checkout-grid">
    <form method="post" action="{{ url('feedback') }}" class="card">
        {!! csrf_field() !!}        <div class="card-head"><div class="card-title">写点什么</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div class="field">
                <label class="label" for="kind">反馈类型</label>
                <select class="select" id="kind" name="kind">
                    @foreach ($kinds as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label class="label" for="content">具体内容</label>
                <textarea class="textarea" id="content" name="content" rows="8" required
                          placeholder="你想要什么功能、在哪一步卡住了、期望的结果是什么"></textarea>
                <div class="hint" style="margin-top:6px">至少 5 个字，最多 2000 字</div>
            </div>

            <div class="field">
                <label class="label" for="contact">联系方式（选填）</label>
                <input class="input" id="contact" name="contact" maxlength="120"
                       value="{{ (string) ($user['email'] ?? '') }}"
                       placeholder="邮箱或 QQ，方便我们回复你">
            </div>

            <div style="display:flex;gap:10px">
                <button class="btn btn-primary" type="submit">提交反馈</button>
                <a class="btn btn-ghost" href="{{ url('/') }}">返回</a>
            </div>
        </div>
    </form>

    <div class="card" style="align-self:start">
        <div class="card-head"><div class="card-title">其他反馈渠道</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            <div>
                <div style="font-weight:650;font-size:13.5px">工单系统</div>
                <p class="hint" style="margin:4px 0 8px;line-height:1.7">涉及账号、订单、上传失败等需要跟进的问题，用工单更方便追溯。</p>
                <a class="btn btn-sm btn-block" href="{{ url('support/tickets') }}">去提交工单</a>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:14px">
                <div style="font-weight:650;font-size:13.5px">内容举报</div>
                <p class="hint" style="margin:4px 0 0;line-height:1.7">在图片或相册详情页点击「举报」，管理员会核实处理。</p>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:14px">
                <div style="font-weight:650;font-size:13.5px">公告与规则</div>
                <p class="hint" style="margin:4px 0 8px;line-height:1.7">站点规则、服务条款都写在独立页面里。</p>
                <a class="btn btn-sm btn-block" href="{{ url('notices') }}">查看公告</a>
            </div>
        </div>
    </div>
</div>
