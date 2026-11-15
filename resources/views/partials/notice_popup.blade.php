<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

/**
 * 全站公告弹窗。
 *
 * 后台公告勾选「全站弹窗」后，这条公告会在所有前台页面上弹一次。
 * 用户关闭后按「公告 ID + 更新时间」记在浏览器本地存储里，
 * 同一个公告不会反复打扰；公告内容更新过（时间戳变了）才会重新弹。
 */

$__popupItems = [];
try {
    $__popupRows = \App\Models\Notice::popupNotices(3);
    foreach ($__popupRows as $__popupRow) {
        $__popupItems[] = [
            'id'      => (int) $__popupRow['id'],
            'title'   => (string) $__popupRow['title'],
            'content' => (string) ($__popupRow['content'] ?? ''),
            'level'   => (string) ($__popupRow['level'] ?? 'info'),
            'stamp'   => (string) ($__popupRow['updated_at'] ?? $__popupRow['created_at'] ?? ''),
        ];
    }
} catch (\Throwable $e) {
    $__popupItems = [];
}

if ($__popupItems === []) {
    return;
}
?>
<div class="notice-popup" id="woniu-notice-popup" hidden aria-hidden="true">
    <div class="notice-popup-mask" data-notice-close></div>
    <div class="notice-popup-box" role="dialog" aria-modal="true" aria-labelledby="woniu-notice-title">
        <div class="notice-popup-head">
            <span class="notice-popup-badge" data-notice-level>提示</span>
            <h3 id="woniu-notice-title" data-notice-title></h3>
            <div class="spacer"></div>
            <button class="notice-popup-x" type="button" data-notice-close aria-label="关闭公告">
                <svg class="ic"><use href="#i-close"/></svg>
            </button>
        </div>
        <div class="notice-popup-body" data-notice-content></div>
        <div class="notice-popup-foot">
            <span class="hint" data-notice-progress></span>
            <div class="spacer"></div>
            <a class="btn btn-sm" href="{{ url('notices') }}" target="_blank" rel="noopener">查看全部公告</a>
            <button class="btn btn-sm btn-primary" type="button" data-notice-close>我知道了</button>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('woniu-notice-popup');
    if (!root) { return; }

    var ITEMS = @json($__popupItems);
    var KEY = 'woniu_notice_popup_closed';

    var titleEl = root.querySelector('[data-notice-title]');
    var contentEl = root.querySelector('[data-notice-content]');
    var levelEl = root.querySelector('[data-notice-level]');
    var progressEl = root.querySelector('[data-notice-progress]');

    var LEVELS = { info: '提示', success: '好消息', warning: '注意', danger: '重要' };

    function readSeen() {
        try {
            var raw = window.localStorage.getItem(KEY);
            var data = raw ? JSON.parse(raw) : {};
            return (data && typeof data === 'object') ? data : {};
        } catch (e) {
            return {};
        }
    }

    function writeSeen(seen) {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(seen));
        } catch (e) {
            /* 隐私模式下 localStorage 可能不可用，忽略即可 */
        }
    }

    var seen = readSeen();
    var pending = ITEMS.filter(function (item) {
        // 同一条公告内容变了（时间戳不同）就重新弹一次
        return String(seen[item.id] || '') !== String(item.stamp || '');
    });

    if (!pending.length) { return; }

    var cursor = 0;

    function render() {
        var item = pending[cursor];
        if (!item) { return; }
        titleEl.textContent = item.title || '';
        contentEl.innerHTML = item.content || '';
        levelEl.textContent = LEVELS[item.level] || '提示';
        root.setAttribute('data-notice-level-key', item.level || 'info');
        progressEl.textContent = pending.length > 1
            ? ('第 ' + (cursor + 1) + ' / ' + pending.length + ' 条')
            : '';
    }

    function open() {
        render();
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('notice-popup-open');
    }

    function close() {
        var item = pending[cursor];
        if (item) {
            seen[item.id] = item.stamp || '';
            writeSeen(seen);
        }
        cursor++;
        if (cursor < pending.length) {
            // 还有下一条就接着弹，全部关完才彻底结束
            render();
            return;
        }
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('notice-popup-open');
    }

    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-notice-close]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (root.hidden || e.key !== 'Escape') { return; }
        close();
    });

    open();
})();
</script>
