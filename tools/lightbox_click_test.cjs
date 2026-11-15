/**
 * 灯箱入口点击测试（真实浏览器 / CDP）
 *
 * 用法：
 *   node tools/lightbox_click_test.cjs http://127.0.0.1:8102/albums/2
 *
 * 检查项：
 *   1. 图片本体上叠着什么元素（elementFromPoint）——遮罩层吃掉点击就是这里暴露的
 *   2. 点图片本体能不能弹出灯箱
 *   3. 点放大按钮能不能弹出灯箱
 *   4. 弹出后按 Esc 能不能关闭
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9444;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-lbtest-profile');
const sleep = ms => new Promise(r => setTimeout(r, ms));

class Cdp {
  constructor(ws) {
    this.ws = ws; this.id = 0; this.pending = new Map();
    ws.addEventListener('message', ev => {
      const m = JSON.parse(ev.data);
      if (m.id && this.pending.has(m.id)) {
        const { resolve, reject } = this.pending.get(m.id);
        this.pending.delete(m.id);
        m.error ? reject(new Error(m.error.message)) : resolve(m.result);
      }
    });
  }
  send(method, params = {}, sessionId) {
    const id = ++this.id;
    const payload = { id, method, params };
    if (sessionId) payload.sessionId = sessionId;
    this.ws.send(JSON.stringify(payload));
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      setTimeout(() => {
        if (this.pending.has(id)) { this.pending.delete(id); reject(new Error('CDP 超时: ' + method)); }
      }, 30000);
    });
  }
}

async function waitDebug() {
  for (let i = 0; i < 40; i++) {
    try {
      const r = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (r.ok) return await r.json();
    } catch (e) { /* 等 */ }
    await sleep(250);
  }
  throw new Error('调试端口无响应');
}

async function main() {
  const url = process.argv[2] || 'http://127.0.0.1:8102/albums/2';
  const proc = spawn(EDGE, [
    '--headless=new', '--disable-gpu', '--no-first-run', '--disable-extensions',
    '--hide-scrollbars', `--remote-debugging-port=${PORT}`, `--user-data-dir=${PROFILE}`,
    'about:blank',
  ], { stdio: 'ignore' });

  fs.mkdirSync(OUT_DIR, { recursive: true });
  let code = 0;
  try {
    const version = await waitDebug();
    const ws = new WebSocket(version.webSocketDebuggerUrl);
    await new Promise((res, rej) => {
      ws.addEventListener('open', res, { once: true });
      ws.addEventListener('error', rej, { once: true });
    });
    const cdp = new Cdp(ws);
    const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });

    await cdp.send('Page.enable', {}, sessionId);
    await cdp.send('Runtime.enable', {}, sessionId);
    await cdp.send('Emulation.setDeviceMetricsOverride',
      { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);

    const evalJs = async (expression) => {
      const r = await cdp.send('Runtime.evaluate',
        { expression, returnByValue: true, awaitPromise: true }, sessionId);
      if (r.exceptionDetails) throw new Error(r.exceptionDetails.text + ' ' + (r.exceptionDetails.exception?.description || ''));
      return r.result?.value;
    };

    // 登录：相册页需要登录态（LB_SKIP_LOGIN=1 时不登录，当游客测落地页）
    const base = new URL(url).origin;
    if (process.env.LB_SKIP_LOGIN === '1') {
      console.log('登录: 跳过（游客模式）');
    } else {
      await cdp.send('Page.navigate', { url: base + '/login' }, sessionId);
      await sleep(1800);
      const logged = await evalJs(`(function () {
        var acc = document.querySelector('#account');
        var pwd = document.querySelector('#password');
        if (!acc || !pwd) { return '表单不存在'; }
        acc.value = ${JSON.stringify(process.env.LB_ACCOUNT || 'admin')};
        acc.dispatchEvent(new Event('input', { bubbles: true }));
        pwd.value = ${JSON.stringify(process.env.LB_PASSWORD || 'test123456')};
        pwd.dispatchEvent(new Event('input', { bubbles: true }));
        var btn = document.querySelector('button[type=submit], form button');
        if (!btn) { return '提交按钮不存在'; }
        btn.click();
        return '已提交';
      })()`);
      console.log('登录: ' + logged);
      await sleep(2200);
    }

    await cdp.send('Page.navigate', { url }, sessionId);
    await sleep(2500);

    // 先滚到可视区（用 instant，页面开了 smooth 会让矩形算不准），再取坐标
    await evalJs(`(function () {
      var img = document.querySelector('.image-card img[data-lightbox-src], .gallery-item img[data-lightbox-src], .lx-show-card img[data-lightbox-src], .detail-canvas img, .detail-canvas[data-lightbox-src]');
      if (img) { img.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' }); }
      return true;
    })()`);
    await sleep(700);

    const probe = await evalJs(`(function () {
      var img = document.querySelector('.image-card img[data-lightbox-src], .gallery-item img[data-lightbox-src], .lx-show-card img[data-lightbox-src], .detail-canvas img, .detail-canvas[data-lightbox-src]');
      if (!img) { return { found: false }; }
      var r = img.getBoundingClientRect();
      var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
      var top = document.elementFromPoint(cx, cy);
      var topBtn = document.elementFromPoint(cx, r.bottom - 24);
      return {
        found: true,
        rect: { x: r.left, y: r.top, w: r.width, h: r.height },
        centerTop: top ? (top.className || top.tagName) : null,
        centerTag: top ? top.tagName : null,
        bottomTop: topBtn ? (topBtn.className || topBtn.tagName) : null,
        bottomTag: topBtn ? topBtn.tagName : null,
        hasBound: img.getAttribute('data-lb-bound') === '1',
        hasLightbox: typeof window.woniuLightbox === 'object',
        overlayStyle: (function () {
          var ov = img.parentElement.querySelector('.image-overlay');
          if (!ov) { return null; }
          var cs = getComputedStyle(ov);
          return { pointerEvents: cs.pointerEvents, position: cs.position, opacity: cs.opacity };
        })()
      };
    })()`);
    console.log('页面: ' + url);
    console.log('图片入口: ' + (probe.found ? '找到' : '没找到'));
    if (!probe.found) { process.exitCode = 2; }
    else {
      console.log('图片矩形: ' + JSON.stringify(probe.rect));
      console.log('中心点最上层元素: <' + probe.centerTag + '> class="' + probe.centerTop + '"');
      console.log('底部点最上层元素: <' + probe.bottomTag + '> class="' + probe.bottomTop + '"');
      console.log('入口已绑定监听: ' + (probe.hasBound ? '是' : '否') + ' / 灯箱脚本: ' + (probe.hasLightbox ? '已加载' : '未加载'));
      console.log('遮罩层样式: ' + JSON.stringify(probe.overlayStyle));
    }

    // 2. 真实鼠标点击图片本体中心
    const rect = probe.rect;
    const cx = rect.x + rect.w / 2, cy = rect.y + rect.h / 2;
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: cx, y: cy }, sessionId);
    await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: cx, y: cy, button: 'left', clickCount: 1 }, sessionId);
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: cx, y: cy, button: 'left', clickCount: 1 }, sessionId);
    await sleep(900);

    let opened = await evalJs(`(function () {
      var b = document.getElementById('woniu-lightbox');
      return !!(b && !b.hasAttribute('hidden'));
    })()`);
    console.log('\n点图片本体 -> 灯箱弹出: ' + (opened ? '是' : '否'));
    if (!opened) code = 1;

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
    fs.writeFileSync(path.join(OUT_DIR, 'lightbox-click.png'), Buffer.from(shot.data, 'base64'));

    if (opened) {
      // 3. Esc 关闭
      await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 }, sessionId);
      await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 }, sessionId);
      await sleep(500);
      const closed = await evalJs(`(function () {
        var b = document.getElementById('woniu-lightbox');
        return !!(b && b.hasAttribute('hidden'));
      })()`);
      console.log('Esc 关闭: ' + (closed ? '是' : '否'));
      if (!closed) code = 1;
    } else {
      // 4. 退回到点放大按钮，确认按钮本身是好的
      await evalJs(`(function () {
        var b = document.getElementById('woniu-lightbox');
        if (b) { b.setAttribute('hidden', 'hidden'); }
        return true;
      })()`);
      const btn = await evalJs(`(function () {
        var b = document.querySelector('.image-card [data-lightbox-self], .gallery-item [data-lightbox-self]');
        if (!b) { return null; }
        var r = b.getBoundingClientRect();
        return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
      })()`);
      if (btn) {
        await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: btn.x, y: btn.y }, sessionId);
        await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: btn.x, y: btn.y, button: 'left', clickCount: 1 }, sessionId);
        await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: btn.x, y: btn.y, button: 'left', clickCount: 1 }, sessionId);
        await sleep(900);
        opened = await evalJs(`(function () {
          var b = document.getElementById('woniu-lightbox');
          return !!(b && !b.hasAttribute('hidden'));
        })()`);
        console.log('点放大按钮 -> 灯箱弹出: ' + (opened ? '是' : '否'));
      }
    }

    ws.close();
  } catch (e) {
    console.error('测试失败: ' + e.message);
    code = 2;
  } finally {
    try { proc.kill(); } catch (e) { /* ignore */ }
  }
  process.exit(code);
}

main();
