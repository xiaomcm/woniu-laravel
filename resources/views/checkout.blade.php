<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
// 用户组权益读套餐绑定的组（买后真正生效的配置）
$group = $groupLimits[(int) ($package['group_id'] ?? 0)]
    ?? ['name' => '', 'max_file_size' => 0, 'daily_limit' => 0, 'benefits' => []];
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">确认订单</h1>
        <p class="explore-sub">请核对套餐信息与支付方式</p>
    </div>
</div>

<form method="post" action="{{ url('shop/pay') }}" data-checkout-form data-package-id="{!! (int) $package['id'] !!}">
    {!! csrf_field() !!}    <input type="hidden" name="package_id" value="{!! (int) $package['id'] !!}">

    <div class="checkout-grid">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div class="card">
                <div class="card-head"><div class="card-title">套餐信息</div></div>
                <div class="card-body">
                    <div style="font-size:17px;font-weight:700">{{ (string) $package['name'] }}</div>
                    <div class="hint" style="margin-top:6px">{{ (string) ($package['description'] ?? '') }}</div>
                    <div class="kv-row" style="margin-top:12px"><span class="kv-key">有效期</span><span class="kv-val">{!! (int) $package['duration_days'] > 0 ? (int) $package['duration_days'] . ' 天' : '永久' !!}</span></div>
                    <div class="kv-row"><span class="kv-key">容量</span><span class="kv-val">{!! (int) $package['capacity'] > 0 ? e(format_bytes((int) $package['capacity'])) : '不限' !!}</span></div>
                    <div class="kv-row"><span class="kv-key">单文件上限</span><span class="kv-val">{!! (int) $group['max_file_size'] > 0 ? e(format_bytes((int) $group['max_file_size'])) : '不限' !!}</span></div>
                    <div class="kv-row"><span class="kv-key">每日上传</span><span class="kv-val">{!! (int) $group['daily_limit'] > 0 ? (int) $group['daily_limit'] . ' 张' : '不限' !!}</span></div>
                    @if ((int) $limit > 0)
                        <div class="kv-row"><span class="kv-key">限购次数</span><span class="kv-val">每人 {!! (int) $limit !!} 次（已购买 {!! (int) $purchased !!} 次）</span></div>
                    @endif
                    @if ($group['name'] !== '' && count($group['benefits']) > 0)
                        <div class="checkout-group-benefits">
                            <div class="kv-key" style="margin:12px 0 6px">购买后升级「{{ $group['name'] }}」，享以下权益</div>
                            <ul class="plan-features">
                                @foreach ($group['benefits'] as $benefit)
                                    <li>{{ $benefit }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title">支付方式</div>
                    <div class="spacer"></div>
                    <span class="hint">异步通知以实际到账为准</span>
                </div>
                <div class="card-body">
                    @if (!empty($free))
                        <div class="pay-tip" style="margin-top:0">
                            <svg class="ic" style="width:15px;height:15px"><use href="#i-gift"/></svg>
                            <span>该套餐为 0 元，提交后立即开通，无需选择支付方式</span>
                        </div>
                    @elseif ($drivers === [])
                        <p class="hint" style="margin:0">站点还没有配置支付通道，请联系管理员。</p>
                    @else
                        <div class="pay-methods">
                            @foreach ($drivers as $index => $driver)
                                <?php $checked = (int) $driver['id'] === (int) $suggestId || ($suggestId === 0 && $index === 0); ?>
                                <label class="pay-method {!! $checked ? 'active' : '' !!}">
                                    <input type="radio" name="driver_id" value="{!! (int) $driver['id'] !!}" {!! $checked ? 'checked' : '' !!}>
                                    <svg class="ic" style="width:19px;height:19px"><use href="#i-card"/></svg>
                                    <span>
                                        <span class="pay-method-name">{{ (string) $driver['name'] }}</span>
                                        <span class="pay-method-sub">{{ (string) $driver['provider'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div class="card-title">优惠码</div></div>
                <div class="card-body">
                    <div style="display:flex;gap:10px">
                        <input class="input" name="coupon" placeholder="输入优惠码，区分大小写" style="text-transform:uppercase">
                        <button class="btn btn-sm" type="button" data-apply-coupon>应用</button>
                    </div>
                    <div class="hint" data-coupon-hint style="margin-top:8px"></div>
                </div>
            </div>
        </div>

        <div class="card" style="position:sticky;top:78px">
            <div class="card-head"><div class="card-title">费用明细</div></div>
            <div class="card-body">
                <div class="summary-row">
                    <span class="label">套餐金额</span>
                    <span>{{ format_money((int) $package['price_cents']) }}</span>
                </div>
                <div class="summary-row" data-discount-row style="display:none">
                    <span class="label">优惠</span>
                    <span data-discount-text style="color:var(--danger)">- {{ format_money(0) }}</span>
                </div>
                <div class="summary-row total">
                    <span>应付</span>
                    <span data-total-text>{{ format_money((int) $package['price_cents']) }}</span>
                </div>
                @if (!empty($limitReached))
                    <div class="pay-tip" style="margin-top:14px">
                        <svg class="ic" style="width:15px;height:15px"><use href="#i-info"/></svg>
                        <span>该套餐每人限购 {!! (int) $limit !!} 次，你已购买 {!! (int) $purchased !!} 次，无法再次购买</span>
                    </div>
                @endif
                <button class="btn btn-primary btn-block" type="submit" style="margin-top:14px"
                        {!! ($drivers === [] && empty($free)) || !empty($limitReached) ? 'disabled' : '' !!}>{!! !empty($limitReached) ? '已达限购上限' : (!empty($free) ? '立即开通' : '去支付') !!}</button>
                <p class="hint" style="margin:12px 0 0;line-height:1.7">
                    支付成功后套餐立即生效，无需人工审核。如果长时间未到账，可提交工单并提供订单号。
                </p>
            </div>
        </div>
    </div>
</form>
