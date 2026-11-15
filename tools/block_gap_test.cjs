/**
 * 页面「块级间距」审计（无头 Edge / CDP）
 *
 * 用法：node tools/block_gap_test.cjs [url] [width] [label]
 *
 * 背景：页面靠 CSS `.card + .card { margin-top: 18px }` 留白。
 * 相邻兄弟选择器要求两个块在 DOM 里**直接相邻** —— 中间夹一个容器
 * （.two-col / .stat-grid）相邻关系就断了，那一段的间距整个消失，
 * 容器底下的卡片会直接贴上来（实测 0px）。
 *
 * 本脚本不看 CSS，只量真实像素：对页面上每一对「上下相邻且水平重叠」
 * 的块级元素，算出它们的垂直间隙，断言全部 ≥ 12px。
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9455;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-block-gap-profile');
const sleep = ms => new Promise(r => setTimeout(r, ms));

// 视作「独立块」的容器 —— 它们之间也要留白
const BLOCK_SEL = [
  '.card', '.two-col', '.stat-grid', '.toolbar', '.tabs',
  '.mini-grid', '.thread', '.plan-grid', '.checkout-grid',
  '.explore-head', '.notice-stack', '.site-wrap', '.detail-wrap',
].join(', ');

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
  const url = process.argv[2] || 'http://127.0.0.1:8102/settings';
  const width = parseInt(process.argv[3] || '1280', 10);
  const label = process.argv[4] || 'block-gap';

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
    await new Promise((res, rej) => { ws.addEventListener('open', res); ws.addEventListener('error', rej); });
    const cdp = new Cdp(ws);
    const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
    await cdp.send('Page.enable', {}, sessionId);
    await cdp.send('Runtime.enable', {}, sessionId);
    await cdp.send('Emulation.setDeviceMetricsOverride',
      { width, height: 900, deviceScaleFactor: 1, mobile: width < 800 }, sessionId);

    const evalJs = async (expression) => {
      const r = await cdp.send('Runtime.evaluate',
        { expression, returnByValue: true, awaitPromise: true }, sessionId);
      if (r.exceptionDetails) throw new Error(r.exceptionDetails.text);
      return r.result?.value;
    };

    const base = new URL(url).origin;
    await cdp.send('Page.navigate', { url: base + '/login' }, sessionId);
    await sleep(1600);
    const logged = await evalJs(`(function () {
      var acc = document.querySelector('#account');
      var pwd = document.querySelector('#password');
      if (!acc || !pwd) { return '表单不存在'; }
      acc.value = ${JSON.stringify(process.env.LB_ACCOUNT || 'admin')};
      acc.dispatchEvent(new Event('input', { bubbles: true }));
      pwd.value = ${JSON.stringify(process.env.LB_PASSWORD || 'admin')};
      pwd.dispatchEvent(new Event('input', { bubbles: true }));
      var btn = document.querySelector('button[type=submit], form button');
      if (!btn) { return '提交按钮不存在'; }
      btn.click();
      return '已提交';
    })()`);
    console.log('登录: ' + logged);
    await sleep(2000);

    await cdp.send('Page.navigate', { url }, sessionId);
    await sleep(2400);

    const info = await evalJs(`(function () {
      var SEL = ${JSON.stringify(BLOCK_SEL)};
      var all = Array.prototype.slice.call(document.querySelectorAll(SEL));

      function name(el) {
        var h = el.querySelector('.card-head h2, .card-head h3, .card-title, .explore-title');
        var t = h ? h.textContent.trim() : '';
        var cls = (el.className || '').toString().trim().split(/\\s+/)[0] || el.tagName.toLowerCase();
        return t ? (cls + '「' + t + '」') : cls;
      }

      var boxes = all.map(function (el) {
        var b = el.getBoundingClientRect();
        return {
          el: el, name: name(el),
          top: b.top + window.scrollY, bottom: b.bottom + window.scrollY,
          left: b.left, right: b.right,
          cls: (el.className || '').toString(),
        };
      });

      // 对每一对：水平重叠（同列）+ 垂直相邻（A 在 B 上方）
      var pairs = [];
      for (var i = 0; i < boxes.length; i++) {
        for (var j = 0; j < boxes.length; j++) {
          if (i === j) { continue; }
          var a = boxes[i], b = boxes[j];
          if (a.el.contains(b.el) || b.el.contains(a.el)) { continue; }
          var overlap = Math.min(a.right, b.right) - Math.max(a.left, b.left);
          if (overlap <= 1) { continue; }
          if (b.top < a.bottom - 1) { continue; }
          var gap = b.top - a.bottom;
          if (gap > 600) { continue; }
          pairs.push({ from: a.name, to: b.name, gap: gap,
                       fromCls: a.cls, toCls: b.cls,
                       nested: !!(a.el.parentElement === b.el.parentElement) });
        }
      }
      // 同一对只保留最小间隙
      var seen = {};
      pairs.forEach(function (p) {
        var k = p.from + '=>' + p.to;
        if (!seen[k] || p.gap < seen[k].gap) { seen[k] = p; }
      });
      pairs = Object.keys(seen).map(function (k) { return seen[k]; });
      pairs.sort(function (x, y) { return x.gap - y.gap; });
      return { pairs: pairs, count: boxes.length };
    })()`);

    console.log('');
    console.log('识别到的块（' + info.count + ' 个）：');
    console.log('');
    console.log('上下相邻块的垂直间隙（从小到大）：');
    for (const p of info.pairs) {
      const mark = p.gap >= 12 ? ' ' : '!';
      console.log('  ' + mark + ' ' + (p.from + ' → ' + p.to).padEnd(38)
        + p.gap.toFixed(1) + 'px' + (p.nested ? '' : '  [非同级]'));
    }

    console.log('');
    const checks = info.pairs
      .filter(p => p.gap < 12)
      .map(p => ({
        name: '「' + p.from + '」与「' + p.to + '」之间有间隙（≥12px）',
        ok: false,
        detail: p.gap.toFixed(1) + 'px',
      }));
    if (!checks.length) {
      checks.push({ name: '所有相邻块之间都有间隙（≥12px）', ok: true, detail: info.pairs.length + ' 对' });
    }
    for (const c of checks) {
      console.log((c.ok ? '  [PASS] ' : '  [FAIL] ') + c.name + '  (' + c.detail + ')');
    }
    const allOk = checks.every(c => c.ok);
    console.log('');
    console.log(allOk ? '全部通过' : '存在不通过项');
    if (!allOk) code = 1;

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
    const out = path.join(OUT_DIR, label + '-' + width + '.png');
    fs.writeFileSync(out, Buffer.from(shot.data, 'base64'));
    console.log('截图: ' + out);
  } catch (e) {
    code = 1;
    console.error('异常: ' + e.message);
  } finally {
    proc.kill();
  }
  console.log(code === 0 ? '\n检测通过' : '\n检测未通过');
  process.exitCode = code;
}

main();
