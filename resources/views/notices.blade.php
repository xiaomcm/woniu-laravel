<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

use App\Models\Notice;
?>
<div class="explore-head">
    <div class="explore-head-main">
        <h1 class="explore-title">站内公告</h1>
        <p class="explore-sub">共 {!! count($notices) !!} 条公告</p>
    </div>
    <a class="btn btn-sm" href="{{ url('explore') }}">去发现</a>
</div>

@if ($notices === [])
    <div class="empty">
        <svg class="ic" style="width:30px;height:30px"><use href="#i-info"/></svg>
        <p>暂无公告</p>
    </div>
@else
    <div class="notice-list">
        @foreach ($notices as $item)
            <?php $level = (string) ($item['level'] ?? 'info'); ?>
            <div class="notice-item {{ $level }}">
                <div style="flex:1;min-width:0">
                    <div class="notice-title">
                        @if ((int) ($item['pinned'] ?? 0) === 1)
                            <span class="thread-tag" style="margin-right:6px">置顶</span>
                        @endif
                        {{ (string) $item['title'] }}                    </div>
                    <div class="notice-body">{!! nl2br(e((string) $item['content'])) !!}</div>
                    <div class="hint" style="margin-top:8px">
                        {{ Notice::levels()[$level] ?? '提示' }}                        <span class="dot">·</span> {{ (string) $item['created_at'] }}                        @if (!empty($item['expire_at']))
                            <span class="dot">·</span> {{ (string) $item['expire_at'] }} 到期
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
