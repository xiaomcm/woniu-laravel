/**
 * 落地页 hero 面板遮挡检测（真实浏览器 / CDP）
 *
 * 用法：
 *   node tools/landing_overlap_test.cjs http://127.0.0.1:8102/
 *
 * 检查项：
 *   1. 卖点徽章是否与上传面板 / 面板标题栏 / 外链行发生矩形相交（遮挡即失败）
 *   2. 页面是否出现横向溢出
 *   3. 每个视口尺寸截一张图到 storage/tmp/landing-{w}.png
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9446;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-landing-test-profile');
const sleep = ms => new Promise(r => setTimeout(r, ms));
const VIEWPORTS = [360, 414, 768, 1024, 1440];

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

const OVERLAP_JS = `(function () {
  function rect(el) {
    if (!el) { return null; }
    var r = el.getBoundingClientRect();
    return { x: r.left, y: r.top, w: r.width, h: r.height, right: r.right, bottom: r.bottom };
  }
  function hit(a, b) {
    if (!a || !b) { return false; }
    return a.x < b.right && a.right > b.x && a.y < b.bottom && a.bottom > b.y;
  }
  var badges = Array.prototype.slice.call(document.querySelectorAll('.lx-badge'));
  var targets = [
    ['上传面板', document.querySelector('.lx-panel')],
    ['面板标题栏', document.querySelector('.lx-panel-bar')],
    ['拖拽区', document.querySelector('.lx-drop')],
    ['缩略图', document.querySelector('.lx-thumbs')],
    ['进度条', document.querySelector('.lx-progress')],
    ['外链行', document.querySelector('.lx-linkout')],
  ];
  var overlaps = [];
  badges.forEach(function (b, i) {
    var br = rect(b);
    targets.forEach(function (t) {
      var tr = rect(t[1]);
      if (hit(br, tr)) { overlaps.push('徽章#' + (i + 1) + ' 压住 ' + t[0]); }
    });
  });
  return {
    badgeCount: badges.length,
    badgeRects: badges.map(rect),
    overlaps: overlaps,
    overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    badgeText: badges.map(function (b) { return b.textContent.trim(); })
  };
})()`;

async function main() {
  const url = process.argv[2] || 'http://127.0.0.1:8102/';
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

    const evalJs = async (expression) => {
      const r = await cdp.send('Runtime.evaluate',
        { expression, returnByValue: true, awaitPromise: true }, sessionId);
      if (r.exceptionDetails) throw new Error(r.exceptionDetails.text + ' ' + (r.exceptionDetails.exception?.description || ''));
      return r.result?.value;
    };

    for (const w of VIEWPORTS) {
      await cdp.send('Emulation.setDeviceMetricsOverride',
        { width: w, height: 900, deviceScaleFactor: 1, mobile: w < 800 }, sessionId);
      await cdp.send('Page.navigate', { url }, sessionId);
      await sleep(2200);

      const res = await evalJs(OVERLAP_JS);
      // 把面板与徽章一起滚进视口再截图，方便肉眼核对
      await evalJs(`(function () {
        var el = document.querySelector('.lx-badges');
        if (el) { el.scrollIntoView({ block: 'center' }); }
        return true;
      })()`);
      await sleep(600);
      const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
      fs.writeFileSync(path.join(OUT_DIR, `landing-${w}.png`), Buffer.from(shot.data, 'base64'));

      const ok = res.overlaps.length === 0 && res.overflowX <= 0 && res.badgeCount === 2;
      if (!ok) code = 1;
      console.log(`\n[${w}px] ${ok ? '通过' : '失败'}`);
      console.log('  徽章数量: ' + res.badgeCount + ' -> ' + res.badgeText.join(' / '));
      console.log('  重叠: ' + (res.overlaps.length ? res.overlaps.join('; ') : '无'));
      console.log('  横向溢出: ' + res.overflowX + 'px');
    }
  } catch (e) {
    code = 1;
    console.error('测试异常: ' + e.message);
  } finally {
    proc.kill();
  }
  console.log(code === 0 ? '\n全部视口通过' : '\n存在未通过项');
  process.exitCode = code;
}

main();
