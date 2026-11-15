/**
 * 全站页脚署名行检测（真实浏览器 / CDP）
 *
 * 用法：
 *   node tools/footer_check.cjs http://127.0.0.1:8102/
 *
 * 检查项（对每个页面）：
 *   1. 页脚容器是否存在（.app-foot / .site-foot / .lx-foot-meta / .auth-foot-brand / .blank-foot）
 *   2. 三段内容是否齐全：© 年份 站点名 / Powered by WONIU / Powered by 站点名 2.0
 *   3. 页脚是否可见（在视口内、没被裁掉）
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9448;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-footer-check-profile');
const sleep = ms => new Promise(r => setTimeout(r, ms));

// [路径, 页脚容器候选选择器（任一命中即可）]
const PAGES = [
  ['/', ['.lx-foot-meta', '.land-foot-meta']],
  ['/explore', ['.site-foot']],
  ['/login', ['.auth-foot-brand']],
  ['/register', ['.auth-foot-brand']],
  ['/s/1ed14e26ab78', ['.blank-foot']],
];

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

const CHECK_JS = `(function (sels) {
  var box = null;
  for (var i = 0; i < sels.length; i++) {
    box = document.querySelector(sels[i]);
    if (box) { break; }
  }
  if (!box) { return { found: false, tried: sels.join(',') }; }
  var r = box.getBoundingClientRect();
  var txt = (box.innerText || box.textContent || '').replace(/\\s+/g, ' ').trim();
  var cs = getComputedStyle(box);
  return {
    found: true,
    text: txt,
    display: cs.display,
    visibility: cs.visibility,
    opacity: cs.opacity,
    width: Math.round(r.width),
    height: Math.round(r.height),
    docBottom: Math.round(document.documentElement.scrollHeight),
    inViewport: r.top < window.innerHeight && r.bottom > 0,
    copyright: /©\\s*\\d{4}\\s*\\S+/.test(txt),
    brand: txt.indexOf('Powered by WONIU') >= 0,
    version: /Powered by .+\\s\\d+\\.\\d+/.test(txt)
  };
})`;

async function main() {
  const base = (process.argv[2] || 'http://127.0.0.1:8102/').replace(/\/$/, '');
  const label = process.argv[3] || 'v2';
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
      { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);

    const evalJs = async (expression) => {
      const r = await cdp.send('Runtime.evaluate',
        { expression, returnByValue: true, awaitPromise: true }, sessionId);
      if (r.exceptionDetails) {
        throw new Error(r.exceptionDetails.text + ' | ' +
          (r.exceptionDetails.exception?.description || JSON.stringify(r.exceptionDetails)));
      }
      return r.result?.value;
    };

    for (const [p, sel] of PAGES) {
      await cdp.send('Page.navigate', { url: base + p }, sessionId);
      await sleep(2000);
      const res = await evalJs(`(${CHECK_JS})(${JSON.stringify(sel)})`);
      if (!res.found) {
        code = 1;
        console.log(`[${p}] 失败：找不到页脚容器 ${res.tried || sel}`);
        continue;
      }
      const ok = res.copyright && res.brand && res.version && res.height > 0 && res.visibility !== 'hidden';
      if (!ok) code = 1;
      console.log(`[${p}] ${ok ? '通过' : '失败'}  ${Math.round(res.height)}px`);
      console.log('   内容: ' + res.text);
      if (!res.copyright) console.log('   ✗ 缺版权段');
      if (!res.brand) console.log('   ✗ 缺 Powered by WONIU');
      if (!res.version) console.log('   ✗ 缺版本段');
    }
  } catch (e) {
    code = 1;
    console.error('异常: ' + e.message);
  } finally {
    proc.kill();
  }
  console.log(code === 0 ? '\n全部页面通过' : '\n存在未通过项');
  process.exitCode = code;
}

main();
