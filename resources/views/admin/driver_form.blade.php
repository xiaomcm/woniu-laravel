<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$isEdit = $driver !== null;
$action = $isEdit ? url('admin/drivers/' . (int) $driver['id']) : url('admin/drivers');
?>
<div class="toolbar">
    <a class="btn btn-sm" href="{{ url('admin/drivers?type=' . $type) }}">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-arrow-left"/></svg> 返回
    </a>
    <div class="spacer"></div>
    <span class="hint">{{ (string) ($meta['desc'] ?? '') }}</span>
</div>

<form method="post" action="{{ $action }}" class="card" data-driver-form>
    {!! csrf_field() !!}    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="provider" value="{{ $provider }}">

    <div class="card-head">
        <div>
            <div class="card-title">{{ $meta['name'] }}</div>
            <div class="card-sub">提供商标识 <code>{{ $provider }}</code></div>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field">
                <label class="label">显示名称</label>
                <input class="input" name="name" value="{{ (string) ($driver['name'] ?? $meta['name']) }}" required>
            </div>
            <div class="field">
                <label class="label">排序 <span class="hint">越小越靠前</span></label>
                <input class="input" name="sortable" value="{!! (int) ($driver['sortable'] ?? 0) !!}">
            </div>
        </div>

        <div class="field">
            <label class="label">备注</label>
            <input class="input" name="remark" value="{{ (string) ($driver['remark'] ?? '') }}" placeholder="内部备注，仅后台可见">
        </div>

        @if (!empty($meta['fields']))
            <div class="section-title">连接配置</div>
            <?php foreach ($meta['fields'] as $field):
                $key = (string) $field['key'];
                $value = (string) ($config[$key] ?? '');
                $type = (string) ($field['type'] ?? 'text');
                $required = !empty($field['required']);
                ?>
                <div class="field">
                    <label class="label">
                        {{ (string) $field['label'] }}                        @if ($required)<span style="color:#e5484d">*</span>@endif
                        @if (!empty($field['hint']))<span class="hint">{{ (string) $field['hint'] }}</span>@endif
                    </label>
                    @if ($type === 'textarea')
                        <textarea class="input" name="{{ $key }}" rows="4" {!! $required ? 'required' : '' !!}                                  placeholder="{{ (string) ($field['placeholder'] ?? '') }}">{{ $value }}</textarea>
                    @elseif ($type === 'select')
                        <select class="select" name="{{ $key }}">
                            @foreach ((array) ($field['options'] ?? []) as $optKey => $optLabel)
                                <option value="{{ (string) $optKey }}" {!! (string) $optKey === $value ? 'selected' : '' !!}>{{ (string) $optLabel }}</option>
                            @endforeach
                        </select>
                    @elseif ($type === 'password')
                        <input class="input" type="password" name="{{ $key }}" value="{{ $value }}"
                               autocomplete="new-password" {!! $required && $value === '' ? 'required' : '' !!}                               placeholder="{{ (string) ($field['placeholder'] ?? '') }}">
                    @else
                        <input class="input" type="{!! $type === 'number' ? 'number' : 'text' !!}" name="{{ $key }}"
                               value="{{ $value }}" {!! $required ? 'required' : '' !!}                               placeholder="{{ (string) ($field['placeholder'] ?? '') }}">
                    @endif
                </div>
            @endforeach
        @endif

        <div class="section-title">生效范围</div>
        <p class="hint" style="margin:-4px 0 12px">
            绑定到角色组表示该组用户上传/访问时使用此驱动；绑定到存储表示该存储上的图片统一走此驱动。
            两处都绑定时，角色组的配置优先。
        </p>

        <div class="field">
            <label class="label">归属角色组</label>
            <div class="perm-grid">
                @foreach ($groups as $group)
                    <label class="switch">
                        <input type="checkbox" name="group_ids[]" value="{!! (int) $group['id'] !!}"
                            {!! in_array((int) $group['id'], $bound['groups'], true) ? 'checked' : '' !!}>
                        <span class="track"></span><span>{{ (string) $group['name'] }}</span>
                    </label>
                @endforeach
                @if ($groups === [])<span class="hint">暂无角色组</span>@endif
            </div>
        </div>

        <div class="field">
            <label class="label">归属存储</label>
            <div class="perm-grid">
                @foreach ($storages as $storage)
                    <label class="switch">
                        <input type="checkbox" name="storage_ids[]" value="{!! (int) $storage['id'] !!}"
                            {!! in_array((int) $storage['id'], $bound['storages'], true) ? 'checked' : '' !!}>
                        <span class="track"></span><span>{{ (string) $storage['name'] }}</span>
                    </label>
                @endforeach
                @if ($storages === [])<span class="hint">暂无存储策略</span>@endif
            </div>
        </div>

        <div class="field">
            <label class="switch">
                <input type="checkbox" name="status" value="1" {!! !$isEdit || (int) $driver['status'] === 1 ? 'checked' : '' !!}>
                <span class="track"></span><span>启用该驱动器</span>
            </label>
        </div>
    </div>

    <div class="card-foot">
        <a class="btn btn-sm" href="{{ url('admin/drivers?type=' . $type) }}">取消</a>
        <div class="spacer"></div>
        @if ($isEdit)
            <button class="btn btn-sm" type="button" data-driver-test="{!! (int) $driver['id'] !!}">测试连接</button>
        @endif
        <button class="btn btn-sm btn-primary" type="submit">保存</button>
    </div>
</form>

<div class="modal" id="driver-test-modal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>测试驱动器</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="driver-test-id">
            <div class="field">
                <label class="label">测试收件邮箱 <span class="hint">邮件驱动填写</span></label>
                <input class="input" id="driver-test-to" placeholder="you@example.com">
            </div>
            <div class="field">
                <label class="label">测试手机号 <span class="hint">短信驱动填写</span></label>
                <input class="input" id="driver-test-phone" placeholder="13800138000">
            </div>
            <div class="field">
                <label class="label">测试图片地址 <span class="hint">审核驱动填写</span></label>
                <input class="input" id="driver-test-url" placeholder="https://cdn.example.com/a.jpg">
            </div>
            <div class="test-result" id="driver-test-result" style="display:none"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>关闭</button>
            <button class="btn btn-sm btn-primary" type="button" id="driver-test-run">开始测试</button>
        </div>
    </div>
</div>
