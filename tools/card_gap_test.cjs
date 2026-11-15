/**
 * 卡片间隙检测（无头 Edge / CDP）
 *
 * 用法：node tools/card_gap_test.cjs [url] [width]
 *
 * 背景：页面靠 CSS `.card + .card { margin-top: 18px }` 留白。
 * 相邻兄弟选择器要求两个 .card 在 DOM 里**直接相邻** —— 中间夹一个
 * 容器元素（.two-col / .stat-grid / <script>）相邻关系就断了，
 * 后一张卡片会直接贴上来，视觉上两块内容黏在一起。
 *
 * 本脚本按「垂直投影重叠」判断同一列内的相邻卡片，量出真实像素间距。
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9454;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-card-gap-profile');
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
  const url = process.argv[2] || 'http://127.0.0.1:8102/settings';
  const width = parseInt(process.argv[3] || '1280', 10);
  const label = process.argv[4] || 'card-gap';

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
      // 顶层卡片：排除嵌套在别的 .card 里的
      var all = Array.prototype.slice.call(document.querySelectorAll('.card'));
      var cards = all.filter(function (c) {
        return !c.parentElement || !c.parentElement.closest('.card');
      }).map(function (c) {
        var b = c.getBoundingClientRect();
        var h = c.querySelector('.card-head h2');
        return {
          title: h ? h.textContent.trim() : '(无标题)',
          top: b.top + window.scrollY,
          bottom: b.bottom + window.scrollY,
          left: b.left,
          right: b.right,
        };
      });

      // 找出「同一列内垂直相邻」的两张卡片：上一个 bottom 到下一个 top 的距离
      var pairs = [];
      for (var i = 0; i < cards.length; i++) {
        for (var j = i + 1; j < cards.length; j++) {
          var a = cards[i], b = cards[j];
          // 水平投影必须重叠，才是同一列
          var overlap = Math.min(a.right, b.right) - Math.max(a.left, b.left);
          if (overlap <= 1) { continue; }
          if (b.top < a.bottom - 1) { continue; }  // 水平重叠（同一行并排）
          pairs.push({ from: a.title, to: b.title, gap: b.top - a.bottom });
        }
      }
      // 同一对只保留最小间距
      var seen = {};
      pairs.forEach(function (p) {
        var k = p.from + '=>' + p.to;
        if (!seen[k] || p.gap < seen[k].gap) { seen[k] = p; }
      });
      pairs = Object.keys(seen).map(function (k) { return seen[k]; });
      pairs.sort(function (x, y) { return x.gap - y.gap; });

      // 同时量一下 two-col 内部两列的高度差（不同高会造成一侧留白）
      var cols = [];
      Array.prototype.forEach.call(document.querySelectorAll('.two-col'), function (t) {
        var kids = Array.prototype.slice.call(t.children).map(function (c) {
          var b = c.getBoundingClientRect();
          var h = c.querySelector('.card-head h2');
          return { title: h ? h.textContent.trim() : '(无标题)', height: b.height };
        });
        if (kids.length === 2) {
          cols.push({ left: kids[0], right: kids[1], diff: Math.abs(kids[0].height - kids[1].height) });
        }
      });

      return { cards: cards, pairs: pairs, cols: cols };
    })()`);

    if (!info || !info.cards.length) {
      console.error('✗ 没找到 .card');
      code = 1;
    } else {
      console.log('');
      console.log('页面卡片（' + info.cards.length + ' 个）：');
      for (const c of info.cards) {
        console.log('  ' + c.title.padEnd(14) + ' top=' + c.top.toFixed(1) + '  bottom=' + c.bottom.toFixed(1)
          + '  h=' + (c.bottom - c.top).toFixed(1));
      }

      console.log('');
      console.log('同列相邻卡片间距（从小到大）：');
      for (const p of info.pairs) {
        console.log('  ' + (p.from + ' → ' + p.to).padEnd(34) + p.gap.toFixed(1) + 'px');
      }

      if (info.cols.length) {
        console.log('');
        console.log('两列高度差：');
        for (const c of info.cols) {
          console.log('  ' + (c.left.title + ' | ' + c.right.title).padEnd(34)
            + c.left.height.toFixed(1) + ' vs ' + c.right.height.toFixed(1)
            + '  差 ' + c.diff.toFixed(1) + 'px');
        }
      }

      console.log('');
      const checks = info.pairs.map(p => ({
        name: '「' + p.from + '」与「' + p.to + '」之间有间隙（≥12px）',
        ok: p.gap >= 12,
        detail: p.gap.toFixed(1) + 'px',
      }));
      if (!checks.length) checks.push({ name: '至少量到一组相邻卡片', ok: false, detail: '未找到' });
      for (const c of checks) {
        console.log((c.ok ? '  [PASS] ' : '  [FAIL] ') + c.name + '  (' + c.detail + ')');
      }
      const allOk = checks.every(c => c.ok);
      console.log('');
      console.log(allOk ? '全部通过' : '存在不通过项');
      if (!allOk) code = 1;
    }

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
