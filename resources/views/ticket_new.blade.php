<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

use App\Models\Ticket;

$priorities = [0 => '不急', 1 => '一般', 2 => '紧急'];
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">提交工单</h1>
        <p class="explore-sub">描述越具体，定位越快。截图可以先上传到图床再把链接贴进来。</p>
    </div>
    <a class="btn btn-sm" href="{{ url('support/tickets') }}">返回列表</a>
</div>

<form method="post" action="{{ url('support/tickets') }}" class="card">
    {!! csrf_field() !!}    <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
        <div class="field">
            <label class="label" for="subject">标题</label>
            <input class="input" id="subject" name="subject" maxlength="200" required
                   placeholder="例如：上传大于 5MB 的图片返回 500">
        </div>

        <div class="form-grid">
            <div class="field">
                <label class="label" for="category">问题类型</label>
                <select class="select" id="category" name="category">
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="priority">紧急程度</label>
                <select class="select" id="priority" name="priority">
                    @foreach ($priorities as $value => $label)
                        <option value="{!! (int) $value !!}" {!! (int) $value === 1 ? 'selected' : '' !!}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label class="label" for="content">问题描述</label>
            <textarea class="textarea" id="content" name="content" rows="9" required
                      placeholder="操作步骤、报错原文、浏览器与设备、发生时间，有订单号也一并写上"></textarea>
            <div class="hint" style="margin-top:6px">至少 10 个字</div>
        </div>

        <div style="display:flex;gap:10px">
            <button class="btn btn-primary" type="submit">提交工单</button>
            <a class="btn btn-ghost" href="{{ url('support/tickets') }}">取消</a>
        </div>
    </div>
</form>
