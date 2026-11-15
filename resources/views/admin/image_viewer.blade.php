<?php
/**
 * 蜗牛图床 WONIU — 后台原图查看器
 * 独立 HTML（不走布局），供 ?raw=1 直接导航时使用，必须自带关闭能力。
 */
$name = (string) ($image['name'] ?? 'image');
$width = (int) ($image['width'] ?? 0);
$height = (int) ($image['height'] ?? 0);
$size = (int) ($image['size'] ?? 0);
$meta = trim(($width > 0 && $height > 0 ? $width . ' × ' . $height : '尺寸未知') . ' · ' . format_bytes($size));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>预览 {{ $name }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            background: radial-gradient(60% 60% at 50% 0%, #101a2e, #05080f 70%);
            color: #e9eefb; overflow: hidden;
            font-family: system-ui, -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        }
        body::before {
            content: ""; position: fixed; inset: 0; pointer-events: none; opacity: .35;
            background-image: linear-gradient(rgba(120,165,255,.06) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(120,165,255,.06) 1px, transparent 1px);
            background-size: 44px 44px;
        }
        .v-top {
            position: fixed; top: 0; left: 0; right: 0; z-index: 10;
            display: flex; align-items: center; gap: 14px;
            padding: 12px 18px; min-height: 62px;
            background: rgba(8, 13, 26, .78); -webkit-backdrop-filter: blur(16px); backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,.09);
        }
        .v-back {
            display: inline-flex; align-items: center; gap: 7px; flex: none;
            color: #a9b7d6; text-decoration: none; font-size: 13.5px; font-weight: 600;
            padding: 8px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,.1);
            transition: .18s;
        }
        .v-back:hover { color: #fff; background: rgba(255,255,255,.07); }
        .v-back svg { width: 15px; height: 15px; }
        .v-info { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
        .v-info strong { font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 46vw; }
        .v-info span { font-size: 12px; color: #7f8eae; font-family: ui-monospace, Menlo, Consolas, monospace; }
        .v-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; flex: none; }
        .v-zoom { display: flex; align-items: center; gap: 2px; padding: 3px; border-radius: 11px; border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); }
        .v-ico {
            width: 34px; height: 32px; display: grid; place-items: center; cursor: pointer;
            border: 0; border-radius: 8px; background: transparent; color: #cbd6ef; transition: .15s;
        }
        .v-ico:hover { background: rgba(255,255,255,.1); color: #fff; }
        .v-ico svg { width: 17px; height: 17px; }
        .v-percent { min-width: 52px; text-align: center; font-size: 12px; color: #a9b7d6; font-family: ui-monospace, Menlo, Consolas, monospace; }
        .v-btn {
            display: inline-flex; align-items: center; gap: 7px; cursor: pointer; text-decoration: none;
            padding: 8px 13px; border-radius: 10px; font-size: 13px; font-weight: 600;
            color: #dbe4f8; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); transition: .18s;
        }
        .v-btn:hover { background: rgba(255,255,255,.11); color: #fff; }
        .v-btn svg { width: 15px; height: 15px; }
        .v-close {
            width: 40px; height: 40px; display: grid; place-items: center; cursor: pointer;
            border-radius: 11px; border: 1px solid rgba(255,110,110,.35);
            background: linear-gradient(135deg, rgba(255,95,95,.18), rgba(255,140,90,.12));
            color: #ff9b9b; transition: .18s;
        }
        .v-close:hover { background: rgba(255,95,95,.32); color: #fff; transform: scale(1.06); }
        .v-close svg { width: 20px; height: 20px; }

        .v-stage {
            position: absolute; inset: 62px 0 34px 0; display: grid; place-items: center;
            overflow: hidden; cursor: grab;
            /* 屏幕范围即拖拽边界；触屏拖拽不被浏览器滚动抢走 */
            touch-action: none;
        }
        .v-stage.dragging { cursor: grabbing; }
        .v-stage img {
            display: block; max-width: calc(100vw - 64px); max-height: calc(100vh - 160px);
            object-fit: contain; border-radius: 10px;
            box-shadow: 0 30px 90px -30px rgba(0,0,0,.9), 0 0 0 1px rgba(255,255,255,.08);
            transform-origin: center center; user-select: none; -webkit-user-drag: none;
            touch-action: none;
            transition: transform .12s ease-out;
        }
        .v-stage img.dragging { transition: none; }
        .v-loading, .v-error { position: absolute; font-size: 14px; color: #93a1c2; display: none; }
        .v-loading.on, .v-error.on { display: block; }
        .v-error { color: #ff9b9b; }
        .v-hint {
            position: fixed; bottom: 0; left: 0; right: 0; height: 34px; z-index: 5;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-size: 12px; color: #6f7d9d; background: rgba(8,13,26,.6);
            border-top: 1px solid rgba(255,255,255,.06);
        }
        kbd { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; padding: 2px 6px; border-radius: 5px; border: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.06); color: #a9b7d6; }
        @media (max-width: 760px) {
            .v-info strong { max-width: 32vw; }
            .v-btn span, .v-back span { display: none; }
            .v-btn { padding: 8px; }
            .v-zoom { display: none; }
            .v-top { gap: 9px; padding: 10px 12px; }
        }
    </style>
</head>
<body data-source="{{ $source }}" data-back="{{ $backUrl }}" data-thumb="{{ $thumbUrl }}">

<div class="v-top">
    <a class="v-back" href="{{ $backUrl }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m0 0 6-6m-6 6 6 6"/></svg>
        <span>返回图片管理</span>
    </a>
    <div class="v-info">
        <strong title="{{ $name }}">{{ $name }}</strong>
        <span>{{ $meta }}</span>
    </div>
    <div class="v-actions">
        <div class="v-zoom">
            <button class="v-ico" type="button" data-zoom="out" title="缩小"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg></button>
            <span class="v-percent" data-percent>100%</span>
            <button class="v-ico" type="button" data-zoom="in" title="放大"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></button>
            <button class="v-ico" type="button" data-zoom="fit" title="还原"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9V4h5M21 9V4h-5M3 15v5h5M21 15v5h-5"/></svg></button>
        </div>
        <a class="v-btn" href="{{ $source }}" download title="下载原图">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>
            <span>下载</span>
        </a>
        <button class="v-btn" type="button" data-close title="关闭预览">
            <span>关闭</span>
        </button>
        <button class="v-close" type="button" data-close title="关闭 (Esc)" aria-label="关闭预览">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>
</div>

<div class="v-stage" data-stage>
    <div class="v-loading on" data-loading>图片加载中…</div>
    <div class="v-error" data-error>图片加载失败，可尝试「下载」或「返回图片管理」</div>
    <img data-img src="{{ $source }}" alt="{{ $name }}" draggable="false">
</div>

<div class="v-hint">
    <kbd>Esc</kbd> 关闭 · 点击空白处关闭 · 滚轮缩放 · 双击还原 · 拖动图片可自由移动
</div>

<script>
(function () {
    'use strict';
    var body = document.body;
    var BACK = body.getAttribute('data-back');
    var stage = document.querySelector('[data-stage]');
    var img = document.querySelector('[data-img]');
    var loading = document.querySelector('[data-loading]');
    var errorEl = document.querySelector('[data-error]');
    var percentEl = document.querySelector('[data-percent]');
    var zoom = 1, panX = 0, panY = 0;
    var MIN = 0.2, MAX = 8;
    var dragging = false, startX = 0, startY = 0;
    var dragStartX = 0, dragStartY = 0, dragMoved = false;
    var suppressClick = false, activePointerId = null;
    var DRAG_THRESHOLD = 3;

    /* 关闭：优先关掉由 target=_blank 打开的标签；被浏览器拦下时退回列表页，
       保证任何情况下都不需要刷新网页才能退出。 */
    function closeViewer() {
        window.close();
        setTimeout(function () {
            try {
                if (history.length > 1) { history.back(); }
                else { window.location.replace(BACK); }
            } catch (e) { window.location.replace(BACK); }
        }, 180);
    }

    function apply() {
        img.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
        percentEl.textContent = Math.round(zoom * 100) + '%';
    }
    function setZoom(next) {
        zoom = Math.max(MIN, Math.min(MAX, next));
        // 缩放保留位移：图片拖到哪就停在哪，复位交给 fit()（双击 / 0 键）
        apply();
    }
    function fit() { zoom = 1; panX = 0; panY = 0; apply(); }

    img.addEventListener('load', function () {
        loading.classList.remove('on');
        errorEl.classList.remove('on');
    });
    img.addEventListener('error', function () {
        loading.classList.remove('on');
        errorEl.classList.add('on');
    });

    /* 点在空白处（stage 本身）才关闭；刚拖过图时吃掉尾随的 click，避免一松手就退出 */
    stage.addEventListener('click', function (e) {
        if (e.target === stage || e.target === loading || e.target === errorEl) {
            if (suppressClick) { suppressClick = false; return; }
            closeViewer();
            return;
        }
        suppressClick = false;
    });
    stage.addEventListener('dblclick', function () { fit(); });
    stage.addEventListener('wheel', function (e) {
        e.preventDefault();
        setZoom(zoom * (e.deltaY < 0 ? 1.12 : 0.89));
    }, { passive: false });

    /* 任意缩放级别都能把图片拖到屏幕内任意位置；Pointer Events 同时支持鼠标与触屏 */
    function onPointerDown(e) {
        if (e.pointerType === 'mouse' && e.button !== 0) { return; }
        dragging = true; dragMoved = false; suppressClick = false;
        activePointerId = e.pointerId;
        dragStartX = e.clientX; dragStartY = e.clientY;
        startX = e.clientX - panX; startY = e.clientY - panY;
        stage.classList.add('dragging'); img.classList.add('dragging');
    }
    function onPointerMove(e) {
        if (!dragging || (activePointerId !== null && e.pointerId !== activePointerId)) { return; }
        var dx = e.clientX - dragStartX, dy = e.clientY - dragStartY;
        if (!dragMoved && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
            dragMoved = true;
        }
        if (!dragMoved) { return; }
        panX = e.clientX - startX; panY = e.clientY - startY; apply();
    }
    function onPointerUp(e) {
        if (!dragging) { return; }
        if (e && activePointerId !== null && e.pointerId !== activePointerId) { return; }
        dragging = false; activePointerId = null;
        suppressClick = dragMoved;
        stage.classList.remove('dragging'); img.classList.remove('dragging');
    }
    stage.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', onPointerUp);
    document.addEventListener('pointercancel', onPointerUp);

    document.querySelectorAll('[data-close]').forEach(function (el) {
        el.addEventListener('click', closeViewer);
    });
    document.querySelectorAll('[data-zoom]').forEach(function (el) {
        el.addEventListener('click', function () {
            var mode = el.getAttribute('data-zoom');
            if (mode === 'in') { setZoom(zoom * 1.25); }
            else if (mode === 'out') { setZoom(zoom / 1.25); }
            else { fit(); }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeViewer(); }
        else if (e.key === '+' || e.key === '=') { setZoom(zoom * 1.25); }
        else if (e.key === '-') { setZoom(zoom / 1.25); }
        else if (e.key === '0') { fit(); }
    });
})();
</script>
</body>
</html>
