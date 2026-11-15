<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="toolbar">
    <a class="btn btn-sm" href="{{ url('admin/shop/packages') }}">套餐</a>
    <a class="btn btn-sm" href="{{ url('admin/shop/orders') }}">订单</a>
    <div class="spacer"></div>
    <button class="btn btn-sm btn-primary" type="button" data-modal-open="#coupon-modal" data-reset-form="#coupon-create-form">
        <svg class="ic" style="width:15px;height:15px"><use href="#i-plus"/></svg> 新建优惠券
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>优惠码</th><th>类型</th><th>门槛</th><th>用量</th><th>有效期</th><th>范围</th><th>状态</th><th></th>
                </tr>
                </thead>
                <tbody>
                @if ($coupons === [])
                    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:34px">还没有优惠券</td></tr>
                @endif
                @foreach ($coupons as $coupon)
                    <tr>
                        <td>
                            <code style="font-weight:700">{{ (string) $coupon['code'] }}</code>
                            <div class="hint">{{ (string) $coupon['name'] }}</div>
                        </td>
                        <td>{{ $coupon['type'] === 'percent' ? '折扣' : '立减' }} {{ (string) $coupon['value_text'] }}</td>
                        <td>{!! (int) $coupon['min_amount_cents'] > 0 ? e(format_money((int) $coupon['min_amount_cents'])) : '无' !!}</td>
                        <td>{!! (int) $coupon['used'] !!} / {!! (int) $coupon['quota'] > 0 ? (int) $coupon['quota'] : '不限' !!}</td>
                        <td style="font-size:12px;color:var(--muted)">
                            {{ (string) ($coupon['starts_at'] ?? '不限') }}<br>
                            至 {{ (string) ($coupon['expires_at'] ?? '不限') }}                        </td>
                        <td style="font-size:12px;color:var(--muted)">
                            {!! (string) ($coupon['scope'] ?? '') === 'all' || (string) ($coupon['scope'] ?? '') === '' ? '全部套餐' : '指定套餐' !!}                        </td>
                        <td>
                            @if ((int) $coupon['status'] === 1)
                                <span class="badge badge-success">启用</span>
                            @else
                                <span class="badge">停用</span>
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-sm" type="button"
                                    data-fill="{{ json_encode([
                                        'code'         => $coupon['code'],
                                        'name'         => $coupon['name'],
                                        'type'         => $coupon['type'],
                                        'value'        => $coupon['type'] === 'percent' ? (int) $coupon['value'] : money_input((int) $coupon['value']),
                                        'min_amount'   => money_input((int) $coupon['min_amount_cents']),
                                        'max_discount' => money_input((int) $coupon['max_discount_cents']),
                                        'quota'        => (int) $coupon['quota'],
                                        'per_user'     => (int) $coupon['per_user'],
                                        'scope'        => (string) ($coupon['scope'] ?? 'all'),
                                        'starts_at'    => str_replace(' ', 'T', substr((string) ($coupon['starts_at'] ?? ''), 0, 16)),
                                        'expires_at'   => str_replace(' ', 'T', substr((string) ($coupon['expires_at'] ?? ''), 0, 16)),
                                        'status_present' => 1,
                                        'status'       => (int) $coupon['status'],
                                    ], JSON_UNESCAPED_UNICODE) }}"
                                    data-fill-target="#coupon-edit-form"
                                    data-action="{{ url('admin/shop/coupons/' . (int) $coupon['id']) }}"
                                    data-modal="#coupon-edit-modal">编辑</button>
                            <form method="post" action="{{ url('admin/shop/coupons/' . (int) $coupon['id'] . '/delete') }}"
                                  data-confirm="确定删除该优惠券？" style="display:inline">
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
$couponForm = static function (string $id, string $action) use ($packages, $__env): void { ?>
    <form class="modal-box" id="{{ $id }}" method="post" action="{{ $action }}">
        <div class="modal-head">
            <h3 data-form-title>优惠券</h3>
            <div class="spacer"></div>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {!! csrf_field() !!}            <input type="hidden" name="status_present" value="1">
            <div class="form-row">
                <div class="field">
                    <label class="label">优惠码</label>
                    <input class="input" name="code" required placeholder="WONIU2026" style="text-transform:uppercase">
                </div>
                <div class="field">
                    <label class="label">名称</label>
                    <input class="input" name="name" value="新用户券">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">类型</label>
                    <select class="select" name="type">
                        <option value="fixed">立减金额</option>
                        <option value="percent">按比例折扣</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label">面值 <span class="hint">元 / 百分比</span></label>
                    <input class="input" name="value" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">最低订单金额 <span class="hint">元</span></label>
                    <input class="input" name="min_amount" value="0">
                </div>
                <div class="field">
                    <label class="label">最高抵扣 <span class="hint">元，0 为不限</span></label>
                    <input class="input" name="max_discount" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">总可用次数 <span class="hint">0 为不限</span></label>
                    <input class="input" name="quota" value="0">
                </div>
                <div class="field">
                    <label class="label">每人限用</label>
                    <input class="input" name="per_user" value="1">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">生效时间</label>
                    <input class="input" type="datetime-local" name="starts_at">
                </div>
                <div class="field">
                    <label class="label">过期时间</label>
                    <input class="input" type="datetime-local" name="expires_at">
                </div>
            </div>
            <div class="field">
                <label class="label">适用范围 <span class="hint">all 或套餐 ID，用逗号分隔</span></label>
                <input class="input" name="scope" value="all" placeholder="all">
                <div class="hint" style="margin-top:6px">
                    当前套餐：@foreach ($packages as $index => $pkg){!! $index > 0 ? ' · ' : '' !!}{!! (int) $pkg['id'] !!}={{ (string) $pkg['name'] }}@endforeach
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" name="status" value="1" checked><span class="track"></span><span>启用</span>
            </label>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm" type="button" data-modal-close>取消</button>
            <button class="btn btn-sm btn-primary" type="submit">保存</button>
        </div>
    </form>
<?php }; ?>

<div class="modal" id="coupon-modal"><?php $couponForm('coupon-create-form', url('admin/shop/coupons')); ?></div>
<div class="modal" id="coupon-edit-modal"><?php $couponForm('coupon-edit-form', ''); ?></div>
