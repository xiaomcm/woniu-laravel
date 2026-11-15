/**
 * 后台设置页「卡片之间的间隙」检测（无头 Edge / CDP）
 *
 * 用法：node tools/settings_gap_test.cjs [url] [width]
 *
 * 背景：设置页的卡片靠 CSS `.card + .card { margin-top: 18px }` 留白。
 * 相邻兄弟选择器要求两个 .card 在 DOM 里**直接相邻** —— 中间插一个元素
 * （比如 <script>）相邻关系就断了，那一段的间距会整个消失，视觉上两块内容黏在一起。
 *
 * 本脚本量出设置页每一对相邻卡片的实际间距，断言全部 ≥ 12px。
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9453;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-settings-gap-profile');
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
  const url = process.argv[2] || 'http://127.0.0.1:8102/admin/settings';
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
      // 只取表单里最外层的卡片（排除嵌套卡片）
      var cards = Array.prototype.slice.call(document.querySelectorAll('.card'));
      var top = cards.filter(function (c) {
        return !c.parentElement || !c.parentElement.closest('.card');
      });
      var list = top.map(function (c) {
        var b = c.getBoundingClientRect();
        var h = c.querySelector('.card-head h2');
        return {
          title: h ? h.textContent.trim() : '(无标题)',
          top: b.top + window.scrollY,
          bottom: b.bottom + window.scrollY,
          height: b.height,
        };
      });
      var gaps = [];
      for (var i = 1; i < list.length; i++) {
        gaps.push({
          from: list[i - 1].title,
          to: list[i].title,
          gap: list[i].top - list[i - 1].bottom,
        });
      }
      return { cards: list, gaps: gaps };
    })()`);

    if (!info || !info.cards.length) {
      console.error('✗ 没找到 .card');
      code = 1;
    } else {
      console.log('');
      console.log('设置页卡片（' + info.cards.length + ' 个）：');
      for (const c of info.cards) {
        console.log('  ' + c.title.padEnd(12) + ' top=' + c.top.toFixed(1) + '  bottom=' + c.bottom.toFixed(1));
      }

      console.log('');
      console.log('相邻卡片间距：');
      for (const g of info.gaps) {
        console.log('  ' + (g.from + ' → ' + g.to).padEnd(30) + g.gap.toFixed(1) + 'px');
      }

      console.log('');
      const checks = [];
      for (const g of info.gaps) {
        checks.push({
          name: '「' + g.from + '」与「' + g.to + '」之间有间隙（≥12px）',
          ok: g.gap >= 12,
          detail: g.gap.toFixed(1) + 'px',
        });
      }
      const target = info.gaps.find(g => g.from === '注册验证码' && g.to === '上传设置');
      checks.push({
        name: '「注册验证码」与「上传设置」这一对量到了',
        ok: !!target,
        detail: target ? target.gap.toFixed(1) + 'px' : '未找到相邻对',
      });

      for (const c of checks) {
        console.log((c.ok ? '  [PASS] ' : '  [FAIL] ') + c.name + '  (' + c.detail + ')');
      }
      const allOk = checks.every(c => c.ok);
      console.log('');
      console.log(allOk ? '全部通过' : '存在不通过项');
      if (!allOk) code = 1;
    }

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
    const out = path.join(OUT_DIR, 'settings-gap-' + width + '.png');
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
