<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="tabs" style="margin-bottom:18px">
    @foreach ($types as $key => $meta)
        <a class="tab {!! $key === $type ? 'active' : '' !!}" href="{{ url('admin/drivers?type=' . $key) }}">
            {{ $meta['label'] }}            <span class="tab-count">{!! (int) $meta['count'] !!}</span>
        </a>
    @endforeach
</div>

<div class="card" style="margin-bottom:18px">
    <div class="card-head">
        <div>
            <div class="card-title">{{ $typeMeta['label'] }}驱动器</div>
            <div class="card-sub">{{ $typeMeta['desc'] }}</div>
        </div>
    </div>
    <div class="card-body">
        <div class="provider-grid">
            @foreach ($providers as $key => $provider)
                <a class="provider-card" href="{{ url('admin/drivers/create?type=' . $type . '&provider=' . $key) }}">
                    <span class="provider-name">{{ $provider['name'] }}</span>
                    <span class="provider-desc">{{ (string) ($provider['desc'] ?? '') }}</span>
                    <span class="provider-add">添加</span>
                </a>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="card-title">已配置</div>
        <div class="spacer"></div>
        <span class="hint">角色组与存储可以同时绑定多个驱动器，组级优先</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>名称</th><th>实现</th><th>状态</th><th>归属角色组</th><th>归属存储</th><th></th>
                </tr>
                </thead>
                <tbody>
                @if ($drivers === [])
                    <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:34px">还没有配置{{ $typeMeta['label'] }}驱动器，从上面的卡片添加一个。</td></tr>
                @endif
                @foreach ($drivers as $driver)
                    <?php $bind = $bindings[(int) $driver['id']] ?? ['groups' => [], 'storages' => []]; ?>
                    <tr>
                        <td>
                            <strong>{{ (string) $driver['name'] }}</strong>
                            @if ((string) ($driver['remark'] ?? '') !== '')
                                <div class="hint">{{ (string) $driver['remark'] }}</div>
                            @endif
                        </td>
                        <td><code style="font-size:12px">{{ (string) $driver['provider'] }}</code></td>
                        <td>
                            @if ((int) $driver['status'] === 1)
                                <span class="badge badge-success">启用</span>
                            @else
                                <span class="badge">停用</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--muted)">
                            <?php
                            $names = [];
                            foreach ($groups as $group) {
                                if (in_array((int) $group['id'], $bind['groups'], true)) {
                                    $names[] = (string) $group['name'];
                                }
                            }
                            echo $names === [] ? '未绑定' : e(implode('、', $names));
                            ?>
                        </td>
                        <td style="font-size:12px;color:var(--muted)">
                            <?php
                            $names = [];
                            foreach ($storages as $storage) {
                                if (in_array((int) $storage['id'], $bind['storages'], true)) {
                                    $names[] = (string) $storage['name'];
                                }
                            }
                            echo $names === [] ? '未绑定' : e(implode('、', $names));
                            ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button" data-driver-test="{!! (int) $driver['id'] !!}">测试</button>
                            <a class="btn btn-sm" href="{{ url('admin/drivers/' . (int) $driver['id'] . '/edit') }}">编辑</a>
                            <form method="post" action="{{ url('admin/drivers/' . (int) $driver['id'] . '/delete') }}"
                                  data-confirm="确定删除该驱动器？绑定关系会一并清除。" style="display:inline">
                                {!! csrf_field() !!}                                <button class="btn btn-sm btn-danger" type="submit">删除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

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
