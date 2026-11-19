/* 蜗牛图床 WONIU | 作者 QQ 34995549 | 官网 www.zhujitown.com */
/*
 * 灯箱：全屏预览 + 自由拖拽 + 键盘切换 + 滚轮缩放
 *
 * 任意缩放级别都能把图片拖到屏幕范围内任意位置（鼠标 / 触屏均可），
 * 位移不做 clamp，缩放也不会把位置弹回中心；双击或按 0 复位。
 *
 * 原生实现，不依赖任何第三方库。
 * 页面里任何带 data-lightbox-src 的元素都会变成灯箱入口：
 *   data-lightbox-src     —— 打开时展示的图片地址（一般填原图直链）
 *   data-lightbox-caption —— 底部显示的说明文字（一般是图片名）
 * 同一页面里的所有入口会自动组成一组，可以用 ← → 在组内切换。
 *
 * 图片本体也可以直接当入口：把 data-lightbox-src 挂在 <img> 上，
 * 点击图片本身就弹出灯箱，不必再点角落的放大按钮。
 * 同一张图的「放大按钮」用 data-lightbox-self="1" 标记，它只是入口的一个快捷方式，
 * 不会在灯箱分组里重复占位（否则左右切换会一直切到同一张图）。
 *
 * 监听器直接绑在入口元素上（不用事件委托）：
 * 图片管理页的卡片本身带点击行为，委托的话按钮的点击会先冒到卡片上，
 * 直接绑定才能在按钮这一层把事件拦下来。
 */
(function () {
    'use strict';

    if (window.woniuLightbox) {
        return;
    }

    var SEL = '[data-lightbox-src]';
    var MIN_ZOOM = 0.4;
    var MAX_ZOOM = 6;

    var box = null;
    var stage = null;
    var pic = null;
    var loadingEl = null;
    var errorEl = null;
    var captionEl = null;
    var counterEl = null;
    var prevBtn = null;
    var nextBtn = null;
    var downloadEl = null;
    var originalEl = null;

    var items = [];
    var index = 0;
    var zoom = 1;
    var panX = 0;
    var panY = 0;
    var dragging = false;
    var dragX = 0;
    var dragY = 0;
    var dragStartX = 0;
    var dragStartY = 0;
    var dragMoved = false;       // 是否真的拖动过（区分「点击」与「拖拽」）
    var suppressClick = false;   // 拖拽结束后吃掉紧跟的 click，避免误关灯箱
    var activePointerId = null;

    /* ---------------------------------------------------------- 建 DOM */

    function create() {
        if (box) {
            return;
        }

        box = document.createElement('div');
        box.className = 'lb-overlay';
        box.id = 'woniu-lightbox';
        box.setAttribute('hidden', 'hidden');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.innerHTML = [
            '<div class="lb-stage">',
            '    <img class="lb-img" alt="">',
            '    <div class="lb-loading"><span class="lb-spinner"></span><span>加载中…</span></div>',
            '    <div class="lb-error">图片加载失败</div>',
            '</div>',
            '<button class="lb-btn lb-close" type="button" title="关闭 (Esc)" aria-label="关闭"><svg class="ic"><use href="#i-close"/></svg></button>',
            '<button class="lb-btn lb-prev" type="button" title="上一张 (←)" aria-label="上一张"><svg class="ic"><use href="#i-arrow-left"/></svg></button>',
            '<button class="lb-btn lb-next" type="button" title="下一张 (→)" aria-label="下一张"><svg class="ic"><use href="#i-arrow-right"/></svg></button>',
            '<div class="lb-bar">',
            '    <span class="lb-caption"></span>',
            '    <span class="lb-spacer"></span>',
            '    <span class="lb-counter"></span>',
            '    <a class="lb-action lb-download" href="#" download title="下载原图"><svg class="ic"><use href="#i-download"/></svg><span>下载</span></a>',
            '    <a class="lb-action lb-original" href="#" target="_blank" rel="noopener" title="在新窗口打开原图"><svg class="ic"><use href="#i-external"/></svg><span>原图</span></a>',
            '</div>'
        ].join('');

        document.body.appendChild(box);

        stage = box.querySelector('.lb-stage');
        pic = box.querySelector('.lb-img');
        loadingEl = box.querySelector('.lb-loading');
        errorEl = box.querySelector('.lb-error');
        captionEl = box.querySelector('.lb-caption');
        counterEl = box.querySelector('.lb-counter');
        prevBtn = box.querySelector('.lb-prev');
        nextBtn = box.querySelector('.lb-next');
        downloadEl = box.querySelector('.lb-download');
        originalEl = box.querySelector('.lb-original');

        box.querySelector('.lb-close').addEventListener('click', close);
        prevBtn.addEventListener('click', function () { move(-1); });
        nextBtn.addEventListener('click', function () { move(1); });

        // 只有点在遮罩空白处才关闭：点在图片、工具条、按钮上都不关。
        // 刚刚拖动过图片时，pointerup 后紧随的 click 不能算「点空白」，否则一松手灯箱就关了。
        box.addEventListener('click', function (e) {
            if (e.target === box || e.target === stage) {
                if (suppressClick) {
                    suppressClick = false;
                    return;
                }
                close();
                return;
            }
            suppressClick = false;
        });

        // 用 Pointer Events 统一鼠标 / 触摸 / 触控笔，触屏也能随手拖
        pic.draggable = false;
        stage.addEventListener('wheel', onWheel, { passive: false });
        stage.addEventListener('pointerdown', onPointerDown);
        stage.addEventListener('dblclick', onDoubleClick);

        document.addEventListener('keydown', onKeyDown);
    }

    /* ---------------------------------------------------------- 绑定入口 */

    function bind() {
        var nodes = document.querySelectorAll(SEL);
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.getAttribute('data-lb-bound') === '1') {
                continue;
            }
            node.setAttribute('data-lb-bound', '1');
            node.addEventListener('click', function (e) {
                // 拦在入口这一层：不让事件继续冒到卡片/链接上
                e.preventDefault();
                e.stopPropagation();
                open(this);
            });
            // 手机浏览器对 <a target="_blank"> 会在 click 之前就处理跳转，
            // 光靠 click 里的 preventDefault 拦不住，会在 touchstart 阶段先截下来。
            if (node.tagName === 'A') {
                node.addEventListener('touchstart', function (e) {
                    e.preventDefault();
                    open(this);
                }, { passive: false });
            }
        }
    }

    function collect(trigger) {
        var nodes = document.querySelectorAll(SEL);
        items = [];
        index = 0;

        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            var src = node.getAttribute('data-lightbox-src');
            // self 入口（放大按钮）只是快捷方式，不进组，否则同一张图会在组里出现两次
            if (!src || isSelf(node)) {
                continue;
            }
            items.push({
                src: src,
                caption: node.getAttribute('data-lightbox-caption') || '',
                // 可选：灯箱「原图」按钮单独指向一个可关闭的查看器页面。
                // 不配置时仍沿用 src，前台所有既有入口行为不变。
                original: node.getAttribute('data-lightbox-original') || src,
                node: node
            });
            if (node === trigger) {
                index = items.length - 1;
            }
        }
    }

    function isSelf(node) {
        return !!node && node.getAttribute('data-lightbox-self') === '1';
    }

    /* ---------------------------------------------------------- 开关 */

    function open(trigger) {
        create();
        bind();
        collect(trigger);

        if (items.length === 0) {
            // 整页只有一个 self 入口时，组里没东西，退化成单图打开
            var onlySrc = trigger ? trigger.getAttribute('data-lightbox-src') : '';
            if (!onlySrc) {
                return;
            }
            items = [{
                src: onlySrc,
                caption: trigger.getAttribute('data-lightbox-caption') || '',
                original: trigger.getAttribute('data-lightbox-original') || onlySrc,
                node: trigger
            }];
            index = 0;
        } else if (trigger && isSelf(trigger)) {
            // 放大按钮：定位到组里同一张图，保证左右切换仍然可用
            var selfSrc = trigger.getAttribute('data-lightbox-src');
            var hit = -1;
            for (var i = 0; i < items.length; i++) {
                if (items[i].src === selfSrc) {
                    hit = i;
                    break;
                }
            }
            if (hit >= 0) {
                index = hit;
            } else {
                items.push({
                    src: selfSrc,
                    caption: trigger.getAttribute('data-lightbox-caption') || '',
                    original: trigger.getAttribute('data-lightbox-original') || selfSrc,
                    node: trigger
                });
                index = items.length - 1;
            }
        }

        box.removeAttribute('hidden');
        document.body.classList.add('lb-locked');
        show();
    }

    function close() {
        if (!box) {
            return;
        }
        box.setAttribute('hidden', 'hidden');
        document.body.classList.remove('lb-locked');
        resetZoom();
        errorEl.classList.remove('lb-on');
        pic.removeAttribute('src');
    }

    /* ---------------------------------------------------------- 切换 */

    function move(step) {
        if (items.length < 2) {
            return;
        }
        index = (index + step + items.length) % items.length;
        show();
    }

    function show() {
        var item = items[index];
        if (!item) {
            return;
        }

        resetZoom();

        captionEl.textContent = item.caption;
        counterEl.textContent = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
        downloadEl.setAttribute('href', item.src);
        originalEl.setAttribute('href', item.original || item.src);
        prevBtn.hidden = items.length < 2;
        nextBtn.hidden = items.length < 2;

        errorEl.classList.remove('lb-on');
        loadingEl.classList.add('lb-on');

        pic.onload = function () {
            loadingEl.classList.remove('lb-on');
        };
        pic.onerror = function () {
            loadingEl.classList.remove('lb-on');
            errorEl.classList.add('lb-on');
        };

        if (pic.getAttribute('src') === item.src && pic.complete) {
            // 同一张图，直接撤掉加载态
            loadingEl.classList.remove('lb-on');
        } else {
            pic.setAttribute('src', item.src);
        }

        // 预热左右邻居，翻页时几乎无感
        preload(index + 1);
        preload(index - 1);
    }

    function preload(i) {
        if (items[i] && items[i].src) {
            var p = new Image();
            p.src = items[i].src;
        }
    }

    /* ---------------------------------------------------------- 缩放 */

    function resetZoom() {
        zoom = 1;
        panX = 0;
        panY = 0;
        applyTransform();
    }

    function applyTransform() {
        pic.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
        stage.classList.toggle('lb-zoomed', zoom > 1);
    }

    function setZoom(next) {
        var value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, next));
        if (value === zoom) {
            return;
        }
        zoom = value;
        // 缩放不再把位移清零：图片拖到哪就停在哪。
        // 复位仍由双击 / 0 键 / 换图 / 关闭统一处理。
        applyTransform();
    }

    function onWheel(e) {
        if (!box || box.hasAttribute('hidden')) {
            return;
        }
        e.preventDefault();
        box.classList.add('lb-no-anim');
        setZoom(zoom + (e.deltaY > 0 ? -0.15 : 0.15));
        window.clearTimeout(onWheel.timer);
        onWheel.timer = window.setTimeout(function () {
            box.classList.remove('lb-no-anim');
        }, 180);
    }

    function onDoubleClick(e) {
        e.preventDefault();
        if (zoom > 1) {
            resetZoom();
        } else {
            setZoom(2);
        }
    }

    /* ---------------------------------------------------------- 拖拽 */
    /*
     * 任意缩放级别都可以拖，位移不做 clamp —— 图片能停在屏幕范围内任何位置。
     * 用 Pointer Events 统一鼠标 / 触摸 / 触控笔；
     * 只有移动超过阈值才算拖拽，纯点击仍保留「点空白关闭」。
     * 复位统一交给双击、0 键、换图、关闭。
     */

    var DRAG_THRESHOLD = 3;

    function onPointerDown(e) {
        if (!box || box.hasAttribute('hidden')) {
            return;
        }
        if (e.pointerType === 'mouse' && e.button !== 0) {
            return;
        }

        // 不因为上一次 pointerup 丢失而卡死：新的按下直接接管
        dragging = true;
        dragMoved = false;
        suppressClick = false;
        activePointerId = e.pointerId;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        dragX = e.clientX - panX;
        dragY = e.clientY - panY;
        stage.classList.add('lb-dragging');
        box.classList.add('lb-no-anim');

        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', onPointerUp);
    }

    function onPointerMove(e) {
        if (!dragging || (activePointerId !== null && e.pointerId !== activePointerId)) {
            return;
        }
        var dx = e.clientX - dragStartX;
        var dy = e.clientY - dragStartY;
        if (!dragMoved && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
            dragMoved = true;
        }
        // 阈值内不移动，点击不会让图片产生微小抖动
        if (!dragMoved) {
            return;
        }
        panX = e.clientX - dragX;
        panY = e.clientY - dragY;
        applyTransform();
    }

    function onPointerUp(e) {
        if (!dragging) {
            return;
        }
        if (e && activePointerId !== null && e.pointerId !== activePointerId) {
            return;
        }
        dragging = false;
        activePointerId = null;
        stage.classList.remove('lb-dragging');
        box.classList.remove('lb-no-anim');
        // 真拖过就吃掉紧跟的 click；没拖动则保持「点空白关闭」
        suppressClick = dragMoved;

        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', onPointerUp);
    }

    /* ---------------------------------------------------------- 键盘 */

    function onKeyDown(e) {
        if (!box || box.hasAttribute('hidden')) {
            return;
        }

        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                close();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                move(-1);
                break;
            case 'ArrowRight':
                e.preventDefault();
                move(1);
                break;
            case '+':
            case '=':
                e.preventDefault();
                setZoom(zoom + 0.2);
                break;
            case '-':
            case '_':
                e.preventDefault();
                setZoom(zoom - 0.2);
                break;
            case '0':
                e.preventDefault();
                resetZoom();
                break;
        }
    }

    /* ---------------------------------------------------------- 启动 */

    window.woniuLightbox = {
        open: function (src, caption) {
            create();
            items = [{ src: src, caption: caption || '', node: null }];
            index = 0;
            box.removeAttribute('hidden');
            document.body.classList.add('lb-locked');
            show();
        },
        close: close
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
