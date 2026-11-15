<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$__noticeFluid = $noticeFluid ?? false;
$__noticeItems = [];
try {
    $__noticeItems = \App\Models\Notice::active(5);
} catch (\Throwable $e) {
}
$__noticeText = (string) ($siteSettings['site_notice'] ?? '');
if ((string) ($siteSettings['notice_enabled'] ?? '0') !== '1') {
    $__noticeText = '';
}
if ($__noticeText === '' && $__noticeItems === []) {
    return;
}
?>
<div class="notice-stack{!! $__noticeFluid ? ' fluid' : '' !!}">
    @if ($__noticeText !== '')
        <div class="site-notice">
            <svg class="ic" style="width:15px;height:15px"><use href="#i-info"/></svg>
            <span>{{ $__noticeText }}</span>
            @if ($__noticeItems !== [])
                <a class="notice-more-link" href="{{ url('notices') }}">查看全部公告 →</a>
            @endif
        </div>
    @endif
    @foreach ($__noticeItems as $__idx => $__item)
        <?php
        $__level = (string) ($__item['level'] ?? 'info');
        $__icon = ['success' => 'i-check', 'warning' => 'i-info', 'danger' => 'i-flag'][$__level] ?? 'i-info';
        ?>
        <div class="site-notice notice-{{ $__level }}">
            <svg class="ic" style="width:15px;height:15px"><use href="#{!! $__icon !!}"/></svg>
            <span><b>{{ (string) $__item['title'] }}</b> · {{ str_limit(strip_tags((string) $__item['content']), 90) }}</span>
            @if ($__noticeText === '' && $__idx === 0 && $__noticeItems !== [])
                <a class="notice-more-link" href="{{ url('notices') }}">查看全部公告 →</a>
            @endif
        </div>
    @endforeach
</div>
