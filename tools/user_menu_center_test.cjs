/**
 * 用户下拉菜单头部居中检测（无头 Edge / CDP）
 *
 * 用法：node tools/user_menu_center_test.cjs [url]
 *
 * 行为：登录后打开右上角用户菜单，读取 .user-dropdown-head 里
 * 用户名 strong 与副标题 span 的文字包围盒，跟头部容器的左右边界比对，
 * 判断两行文本是否真的水平居中；顺带截图。
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9451;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-umenu-profile');
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
  const url = process.argv[2] || 'http://127.0.0.1:8102/';
  const width = parseInt(process.argv[3] || '1280', 10);

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

    // 登录
    const base = new URL(url).origin;
    await cdp.send('Page.navigate', { url: base + '/login' }, sessionId);
    await sleep(1600);
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
    await sleep(2000);

    await cdp.send('Page.navigate', { url }, sessionId);
    await sleep(2200);

    // 打开用户菜单
    const opened = await evalJs(`(function () {
      var btn = document.querySelector('[data-user-menu]');
      if (!btn) { return '按钮不存在'; }
      btn.click();
      return '已点击';
    })()`);
    console.log('打开菜单: ' + opened);
    await sleep(700);

    const info = await evalJs(`(function () {
      var head = document.querySelector('.user-dropdown-head');
      if (!head) { return null; }
      var hb = head.getBoundingClientRect();
      var strong = head.querySelector('strong');
      var span = head.querySelector('span');
      var sb = strong ? strong.getBoundingClientRect() : null;
      var pb = span ? span.getBoundingClientRect() : null;
      var cs = getComputedStyle(head);
      return {
        head:      { left: hb.left, right: hb.right, width: hb.width },
        textAlign: cs.textAlign,
        strong: sb ? { text: strong.textContent.trim(), left: sb.left, right: sb.right, width: sb.width } : null,
        span:   pb ? { text: span.textContent.trim(),   left: pb.left, right: pb.right, width: pb.width } : null,
      };
    })()`);

    if (!info) {
      console.error('✗ 没找到 .user-dropdown-head');
      code = 1;
    } else {
      console.log('');
      console.log('头部容器: left=' + info.head.left.toFixed(1) + ' right=' + info.head.right.toFixed(1)
        + ' width=' + info.head.width.toFixed(1) + '  text-align=' + info.textAlign);
      console.log('');

      const checks = [];
      const hc = (info.head.left + info.head.right) / 2;

      for (const key of ['strong', 'span']) {
        const el = info[key];
        if (!el) { checks.push({ key, ok: false }); continue; }
        const c = (el.left + el.right) / 2;
        const off = Math.abs(c - hc);
        const ok = off <= 1.5;
        checks.push({ key, ok });
        console.log((ok ? '  [PASS] ' : '  [FAIL] ') + key + ' 「' + el.text + '」');
        console.log('         文本中心=' + c.toFixed(1) + '  容器中心=' + hc.toFixed(1)
          + '  偏移=' + off.toFixed(2) + 'px  文本宽=' + el.width.toFixed(1));
      }

      console.log('');
      const allOk = checks.every(c => c.ok);
      console.log(allOk ? '全部通过：两行文本均水平居中' : '存在未居中的元素');
      if (!allOk) code = 1;
    }

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
    const out = path.join(OUT_DIR, 'user-menu-center-' + width + '.png');
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
