<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="mini-grid" style="margin-bottom:18px">
    <div class="mini-stat">
        <div class="mini-stat-label">累计收入</div>
        <div class="mini-stat-value">{{ format_money((int) $orderStat['revenue']) }}</div>
    </div>
    <div class="mini-stat">
        <div class="mini-stat-label">已支付订单</div>
        <div class="mini-stat-value">{!! (int) $orderStat['paid'] !!}</div>
    </div>
    <div class="mini-stat">
        <div class="mini-stat-label">在售套餐</div>
        <div class="mini-stat-value">{!! count($packages) !!}</div>
    </div>
</div>

<div class="toolbar">
    <a class="btn btn-sm" href="{{ url('admin/shop/orders') }}">订单</a>
    <a class="btn btn-sm" href="{{ url('admin/shop/coupons') }}">优惠券</a>
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#package-modal" data-reset-form="#package-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建套餐
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>套餐</th><th>价格</th><th>时长</th><th>容量</th><th>单文件</th><th>每日张数</th><th>限购</th><th>用户组</th><th>状态</th><th></th>
                </tr>
                </thead>
                <tbody>
                @if ($packages === [])
                    <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:34px">还没有套餐</td></tr>
                @endif
                @foreach ($packages as $package)
                    <?php
                    $groupName = '-';
                    $groupMaxFile = 0;
                    $groupDaily = 0;
                    foreach ($groups as $group) {
                        if ((int) $group['id'] === (int) $package['group_id']) {
                            $groupName = (string) $group['name'];
                            // 单文件/每日张数显示绑定用户组的实际配置（买后真正生效的值）
                            $groupMaxFile = (int) ($group['max_file_size'] ?? 0);
                            $groupDaily = (int) ($group['daily_limit'] ?? 0);
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <strong>{{ (string) $package['name'] }}</strong>
                            @if ((string) ($package['description'] ?? '') !== '')
                                <div class="hint">{{ str_limit((string) $package['description'], 40) }}</div>
                            @endif
                        </td>
                        <td>{{ format_money((int) $package['price_cents']) }}</td>
                        <td>{!! (int) $package['duration_days'] > 0 ? (int) $package['duration_days'] . ' 天' : '永久' !!}</td>
                        <td>{!! (int) $package['capacity'] > 0 ? e(format_bytes((int) $package['capacity'])) : '不限' !!}</td>
                        <td>{!! $groupMaxFile > 0 ? e(format_bytes($groupMaxFile)) : '不限' !!}</td>
                        <td>{!! $groupDaily > 0 ? $groupDaily : '不限' !!}</td>
                        <td>{!! (int) ($package['purchase_limit'] ?? 0) > 0 ? '限购 ' . (int) $package['purchase_limit'] . ' 次' : '不限' !!}</td>
                        <td>{{ $groupName }}</td>
                        <td>
                            @if ((int) $package['status'] === 1)
                                <span class="badge badge-success">在售</span>
                            @else
                                <span class="badge">下架</span>
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'name'              => $package['name'],
                                        'group_id'          => (int) $package['group_id'],
                                        'price'             => money_input((int) $package['price_cents']),
                                        'duration_days'     => (int) $package['duration_days'],
                                        'capacity_mb'       => round((int) $package['capacity'] / 1048576, 2),
                                        'purchase_limit'    => (int) ($package['purchase_limit'] ?? 0),
                                        'description'       => $package['description'],
                                        'features'          => implode("\n", $package['features_list']),
                                        'sortable'          => (int) $package['sortable'],
                                        'status_present'    => 1,
                                        'status'            => (int) $package['status'],
                                    ], JSON_UNESCAPED_UNICODE) }}"
                                    data-fill-target="#package-edit-form"
                                    data-action="{{ url('admin/shop/packages/' . (int) $package['id']) }}"
                                    data-modal="#package-edit-modal">编辑</button>
                            <form method="post" action="{{ url('admin/shop/packages/' . (int) $package['id'] . '/delete') }}"
                                  data-confirm="确定删除该套餐？已支付的订单不受影响。" style="display:inline">
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

<?php
$packageForm = static function (string $id, string $action) use ($groups, $groupBenefits, $__env): void { ?>
    <form class="modal-box" id="{{ $id }}" method="post" action="{{ $action }}">
        <div class="modal-head">
            <h3 data-form-title>套餐</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <input type="hidden" name="status_present" value="1">
            <div class="field">
                <label class="label">套餐名称</label>
                <input class="input" name="name" required placeholder="专业版（年付）">
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">价格 <span class="hint">元，0 为免费</span></label>
                    <input class="input" name="price" value="0">
                </div>
                <div class="field">
                    <label class="label">有效天数 <span class="hint">0 为永久有效</span></label>
                    <input class="input" name="duration_days" value="30">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">总容量 <span class="hint">MB，0 为不限</span></label>
                    <input class="input" name="capacity_mb" value="1024">
                </div>
                <div class="field">
                    <label class="label">限购次数 <span class="hint">0 为不限，每人最多买 N 次</span></label>
                    <input class="input" name="purchase_limit" value="0">
                </div>
            </div>
            <p class="hint" style="margin:2px 0 4px">单文件上限与每日上传张数由下方「升级到用户组」决定，买套餐后按该用户组的设置生效，不用在这里重复填。</p>
            <div class="form-row">
                <div class="field">
                    <label class="label">升级到用户组</label>
                    <select class="select" name="group_id">
                        @foreach ($groups as $group)
                            <option value="{!! (int) $group['id'] !!}">{{ (string) $group['name'] }}</option>
                        @endforeach
                    </select>
                    <div class="hint" style="margin-top:8px;line-height:1.7">
                        <strong>各组权益（买套餐后用户实际得到的）：</strong><br>
                        @foreach ($groups as $group)
                            · {{ (string) $group['name'] }}：{!! e(implode('、', $groupBenefits[(int) $group['id']] ?? [])) !!}<br>
                        @endforeach
                    </div>
                </div>
                <div class="field">
                    <label class="label">一句话描述</label>
                    <input class="input" name="description" placeholder="不限每日张数，单文件 20 MB">
                </div>
            </div>
            <div class="field">
                <label class="label">权益列表 <span class="hint">每行一条</span></label>
                <textarea class="input" name="features" rows="4" placeholder="50 GB 存储&#10;单文件 20 MB&#10;不限每日张数"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">排序 <span class="hint">越小越靠前</span></label>
                    <input class="input" name="sortable" value="0">
                </div>
                <div class="field">
                    <label class="label">状态</label>
                    <label class="switch" style="height:38px">
                        <input type="checkbox" name="status" value="1" checked><span class="track"></span><span>上架销售</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
<?php }; ?>

<div class="modal" id="package-modal"><?php $packageForm('package-create-form', url('admin/shop/packages')); ?></div>
<div class="modal" id="package-edit-modal"><?php $packageForm('package-edit-form', ''); ?></div>
