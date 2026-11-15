<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$image = $image ?? [];
$links = $links ?? [];
$copyRows = [
    ['label' => '直链', 'tip' => 'URL', 'value' => (string) ($links['url'] ?? '')],
    ['label' => 'HTML', 'tip' => 'img', 'value' => (string) ($links['html'] ?? '')],
    ['label' => 'Markdown', 'tip' => 'md', 'value' => (string) ($links['markdown'] ?? '')],
    ['label' => 'BBCode', 'tip' => 'bb', 'value' => (string) ($links['bbcode'] ?? '')],
];
?>
<div class="site-wrap">
    <div class="detail-head">
        <a class="btn btn-ghost btn-sm" href="{{ url('explore') }}">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-arrow-left"/></svg> 返回发现
        </a>
        <span class="detail-title">{{ (string) $image['name'] }}</span>
    </div>

    <div class="detail-wrap">
        <div class="detail-main">
            <a class="detail-canvas" href="{{ (string) $image['url'] }}" target="_blank" rel="noopener" @if ((string) setting('lightbox_enabled', '1') === '1') data-lightbox-src="{{ (string) $image['url'] }}" data-lightbox-caption="{{ (string) $image['name'] }}" @endif>

                <img src="{{ (string) $image['url'] }}" alt="{{ (string) $image['name'] }}">
            </a>

            @if (!empty($image['description']))
                <p class="detail-desc">{!! nl2br(e((string) $image['description'])) !!}</p>
            @endif

            @if (!empty($tags))
                <div class="tag-row" style="margin-top:14px">
                    @foreach ($tags as $tag)
                        <a class="tag-chip" href="{{ url('explore') . '?tag=' . rawurlencode((string) $tag) }}">{{ (string) $tag }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="detail-side">
            <div class="card">
                <div class="card-head"><h3>图片信息</h3></div>
                <div class="card-body">
                    <div class="kv-row">
                        <span class="kv-key">尺寸</span>
                        <span class="kv-val">{!! (int) $image['width'] !!} × {!! (int) $image['height'] !!} px</span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-key">大小</span>
                        <span class="kv-val">{{ format_bytes((int) $image['size']) }}</span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-key">格式</span>
                        <span class="kv-val">{{ strtoupper((string) $image['extension']) }}</span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-key">浏览</span>
                        <span class="kv-val">{!! (int) $image['views'] !!} 次</span>
                    </div>
                    <div class="kv-row">
                        <span class="kv-key">上传时间</span>
                        <span class="kv-val">{{ (string) $image['created_at'] }}</span>
                    </div>
                    @if ((int) $image['status'] !== 1)
                        <div class="kv-row">
                            <span class="kv-key">状态</span>
                            <span class="kv-val"><span class="badge badge-warning">审核中</span></span>
                        </div>
                    @endif
                    @if ((int) $image['is_public'] !== 1)
                        <div class="kv-row">
                            <span class="kv-key">可见性</span>
                            <span class="kv-val"><span class="badge">仅自己可见</span></span>
                        </div>
                    @endif
                    @if (!empty($hasPassword))
                        <div class="kv-row">
                            <span class="kv-key">访问密码</span>
                            <span class="kv-val"><span class="badge badge-warning">已加密</span></span>
                        </div>
                    @endif
                </div>
            </div>

            @if ($isOwner)
                <div class="card">
                    <div class="card-head"><h3>分享设置</h3></div>
                    <div class="card-body">
                        <form method="post" action="{{ url('images/' . (int) $image['id'] . '/share') }}">
                            {!! csrf_field() !!}                            <input type="hidden" name="is_public" value="0">
                            <label class="switch" style="margin-bottom:12px">
                                <input type="checkbox" name="is_public" value="1" {!! (int) $image['is_public'] === 1 ? 'checked' : '' !!}>
                                <span class="track"></span>
                                <span>公开到发现页</span>
                            </label>

                            <div class="field" style="margin-bottom:10px">
                                <label class="label" for="share-pwd">
                                    访问密码
                                    <span class="hint">{!! !empty($hasPassword) ? '已设置，留空则保持不变' : '留空表示不加密' !!}</span>
                                </label>
                                <input class="input" id="share-pwd" name="password" type="text" maxlength="32"
                                       autocomplete="new-password" placeholder="4-32 位，设置后访客需输入">
                            </div>

                            @if (!empty($hasPassword))
                                <label class="switch" style="margin-bottom:12px">
                                    <input type="checkbox" name="clear_password" value="1">
                                    <span class="track"></span>
                                    <span>清除密码</span>
                                </label>
                            @endif

                            <button class="btn btn-sm btn-primary btn-block" type="submit">保存分享设置</button>
                        </form>

                        <div class="copy-row" style="margin-top:12px">
                            <span class="copy-label">分享链接</span>
                            <code class="copy-val">{{ url('i/' . (string) $image['sha1']) }}</code>
                            <button class="btn btn-sm" type="button" data-copy="{{ url('i/' . (string) $image['sha1']) }}">
                                <svg class="ic" style="width:13px;height:13px"><use href="#i-copy"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if (!empty($owner))
                <div class="card">
                    <div class="card-head"><h3>上传者</h3></div>
                    <div class="card-body">
                        <a class="owner-row" href="{{ url('u/' . (int) $owner['id']) }}">
                            <span class="avatar">{{ mb_substr((string) $owner['name'], 0, 1) }}</span>
                            <span class="owner-name">{{ (string) $owner['name'] }}</span>
                        </a>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-head"><h3>引用代码</h3></div>
                <div class="card-body">
                    @foreach ($copyRows as $row)
                        <?php if ((string) $row['value'] === '') { continue; } ?>
                        <div class="copy-row">
                            <span class="copy-label">{{ $row['label'] }}</span>
                            <code class="copy-val" title="{{ (string) $row['value'] }}">{{ (string) $row['value'] }}</code>
                            <button class="btn btn-sm" type="button" data-copy="{{ (string) $row['value'] }}">
                                <svg class="ic" style="width:13px;height:13px"><use href="#i-copy"/></svg>
                            </button>
                        </div>
                    @endforeach
                    <?php
                    $baseUrl = (string) $image['url'];
                    $localServed = $baseUrl !== '' && strpos($baseUrl, '/uploads/') !== false;
                    ?>
                    @if ($localServed)
                        <div class="copy-row" style="flex-wrap:wrap;gap:8px">
                            <span class="copy-label">尺寸变体</span>
                            @foreach ([['w' => 400, 'label' => '400w'], ['w' => 800, 'label' => '800w'], ['w' => 1200, 'label' => '1200w'], ['w' => 800, 'f' => 'webp', 'label' => '800w webp']] as $preset)
                                <?php
                                $variant = $baseUrl . '?' . http_build_query(array_filter([
                                    'w' => $preset['w'],
                                    'f' => $preset['f'] ?? '',
                                ]));
                                ?>
                                <a class="btn btn-sm btn-ghost" href="{{ $variant }}" target="_blank" rel="noopener">{{ $preset['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                    @if (!empty($cloudVariants))
                        <div class="copy-row" style="flex-wrap:wrap;gap:8px">
                            <span class="copy-label">云处理</span>
                            @foreach ($cloudVariants as $width => $derived)
                                <a class="btn btn-sm btn-ghost" href="{{ $derived }}" target="_blank" rel="noopener">{!! (int) $width !!}px</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="detail-actions">
                        <a class="btn btn-sm btn-primary" href="{{ (string) $image['url'] }}" download>
                            <svg class="ic" style="width:14px;height:14px"><use href="#i-download"/></svg> 下载原图
                        </a>
                        @if ($isOwner)
                            <a class="btn btn-sm" href="{{ url('images') }}">
                                <svg class="ic" style="width:14px;height:14px"><use href="#i-settings"/></svg> 管理图片
                            </a>
                        @elseif ((string) setting('report_enabled', '1') === '1')
                            <button class="btn btn-sm btn-ghost" type="button" data-modal-open="#report-modal">
                                <svg class="ic" style="width:14px;height:14px"><use href="#i-flag"/></svg> 举报
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </aside>
    </div>

    @if (!empty($related))
        <section class="related">
            <h2 class="related-title">相关推荐</h2>
            <div class="gallery gallery-fixed gallery-related">
                @foreach ($related as $item)
                    <a class="gallery-item" href="{{ url('i/' . $item['sha1']) }}">
                        <img src="{{ image_cover($item) }}" width="{!! \App\Services\Cover::W !!}" height="{!! \App\Services\Cover::H !!}" data-w="{!! \App\Services\Cover::W !!}" data-h="{!! \App\Services\Cover::H !!}" alt="{{ (string) $item['name'] }}"{{ lazy_attr() }}
                             @if ((string) setting('lightbox_enabled', '1') === '1')
                                 data-lightbox-src="{{ (string) $item['url'] }}" data-lightbox-caption="{{ (string) $item['name'] }}" title="点击查看大图"
                             @endif
                        >
                        @if ((string) setting('lightbox_enabled', '1') === '1')
                            <button class="lb-trigger" type="button" data-lightbox-src="{{ (string) $item['url'] }}" data-lightbox-caption="{{ (string) $item['name'] }}" data-lightbox-self="1" title="灯箱预览" aria-label="灯箱预览">

                                <svg class="ic"><use href="#i-expand"/></svg>
                            </button>
                        @endif
                        <div class="gallery-mask">
                            <span class="gallery-name">{{ (string) $item['name'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>

@if (!$isOwner && (string) setting('report_enabled', '1') === '1')
    <div class="modal" id="report-modal">
        <form class="modal-box" method="post" action="{{ url('report') }}" data-ajax>
            <div class="modal-head">
                <h3>举报这张图片</h3>
                <div class="spacer"></div>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>
                    <svg class="ic" style="width:15px;height:15px"><use href="#i-close"/></svg>
                </button>
            </div>
            <div class="modal-body">
                {!! csrf_field() !!}                <input type="hidden" name="image_id" value="{!! (int) $image['id'] !!}">

                <div class="field">
                    <label class="label">举报类型</label>
                    <select class="select" name="category">
                        @foreach (\App\Models\Report::categories() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="label">具体说明 <span class="hint">可选</span></label>
                    <textarea class="textarea" name="reason" maxlength="480" rows="4"
                              placeholder="说明违规点，附上线索更好核实"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-sm" type="button" data-modal-close>取消</button>
                <button class="btn btn-sm btn-danger" type="submit">提交举报</button>
            </div>
        </form>
    </div>
@endif
