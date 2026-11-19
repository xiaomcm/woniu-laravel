/* 蜗牛图床 WONIU · Landing 交互脚本（无依赖，纯原生）
   - 极光粒子网络背景（hero canvas）
   - 导航滚动玻璃化
   - 统计数字滚动
   - 元素滚动入场 */
(function () {
    'use strict';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 极光粒子网络 ---------- */
    function initCanvas() {
        var cv = document.getElementById('lx-canvas');
        if (!cv || reduce) return;
        // 手机端不开：70 个点两两连线约 4900 次/帧，滚动时直接跟手机抢 CPU。
        // 判断放宽：触屏点数 > 0 或指针是 coarse 都算手机，只靠 media 查询在部分浏览器上不生效。
        var touch = (navigator.maxTouchPoints || 0) > 0
            || (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches)
            || (window.matchMedia && window.matchMedia('(max-width: 768px)').matches);
        if (touch) { cv.style.display = 'none'; return; }
        var ctx = cv.getContext('2d');
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var w, h, pts, raf = null, hover = false;
        var COLORS = ['rgba(91,140,255,', 'rgba(52,231,228,', 'rgba(168,116,255,'];

        function size() {
            w = window.innerWidth; h = window.innerHeight;
            cv.width = w * dpr; cv.height = h * dpr;
            cv.style.width = w + 'px'; cv.style.height = h + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            var n = Math.max(28, Math.min(70, Math.round(w / 22)));
            pts = [];
            for (var i = 0; i < n; i++) {
                pts.push({
                    x: Math.random() * w, y: Math.random() * h,
                    vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35,
                    c: COLORS[i % COLORS.length], r: Math.random() * 1.6 + .8
                });
            }
        }

        function frame() {
            ctx.clearRect(0, 0, w, h);
            var maxD = 130, i, j;
            for (i = 0; i < pts.length; i++) {
                var p = pts[i];
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > w) p.vx *= -1;
                if (p.y < 0 || p.y > h) p.vy *= -1;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, 6.2832);
                ctx.fillStyle = p.c + '.9)';
                ctx.fill();
            }
            for (i = 0; i < pts.length; i++) {
                for (j = i + 1; j < pts.length; j++) {
                    var a = pts[i], b = pts[j];
                    var dx = a.x - b.x, dy = a.y - b.y;
                    var d = Math.sqrt(dx * dx + dy * dy);
                    if (d < maxD) {
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
                        ctx.strokeStyle = a.c + (0.16 * (1 - d / maxD)) + ')';
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                }
            }
            raf = requestAnimationFrame(frame);
        }

        function start() { if (raf === null) { raf = requestAnimationFrame(frame); } }
        function stop() { if (raf !== null) { cancelAnimationFrame(raf); raf = null; } }

        size();
        start();

        // 页面不可见时停掉，别在后台空跑
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { stop(); } else { start(); }
        });
        // 滚动时也停：滚动是用户最在意跟手的时候，别让粒子抢帧
        var scrollT;
        window.addEventListener('scroll', function () {
            stop();
            clearTimeout(scrollT);
            scrollT = setTimeout(start, 160);
        }, { passive: true });

        var t;
        window.addEventListener('resize', function () {
            clearTimeout(t); t = setTimeout(size, 200);
        });
    }

    /* ---------- 导航滚动玻璃化 ---------- */
    function initNav() {
        var nav = document.querySelector('.lx-nav');
        if (!nav) return;
        var onScroll = function () {
            nav.classList.toggle('scrolled', window.scrollY > 12);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ---------- 数字滚动 ---------- */
    function initCounters() {
        var els = document.querySelectorAll('[data-count]');
        if (!els.length) return;
        var run = function (el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var dur = 1500, start = null;
            el.textContent = '0';
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var e = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * e).toLocaleString('en-US');
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = target.toLocaleString('en-US');
            }
            if (reduce) { el.textContent = target.toLocaleString('en-US'); return; }
            requestAnimationFrame(step);
        };
        if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
        var io = new IntersectionObserver(function (ents) {
            ents.forEach(function (en) {
                if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
            });
        }, { threshold: .4 });
        els.forEach(function (el) { io.observe(el); });
    }

    /* ---------- 滚动入场 ---------- */
    function initReveal() {
        var els = document.querySelectorAll('.lx-reveal');
        if (!els.length || reduce) return;
        if (!('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); return; }
        var io = new IntersectionObserver(function (ents) {
            ents.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
            });
        }, { threshold: .12 });
        els.forEach(function (e) { io.observe(e); });
    }

    function boot() {
        /* 只有脚本可用且允许动效时才开启入场隐藏，避免 JS 失败导致整页空白 */
        if (!reduce) document.documentElement.classList.add('lx-anim');
        initCanvas(); initNav(); initCounters(); initReveal();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
