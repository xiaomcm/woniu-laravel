/**
 * 通用登录后截图（无头 Edge / CDP）
 *
 * 用法：
 *   node tools/shot.cjs <url> <输出文件名> [视口宽]
 *
 * 例：
 *   node tools/shot.cjs http://127.0.0.1:8102/albums/2 album-after.png 1280
 *
 * 行为：先用 admin 账号登录（如需，可用 LB_ACCOUNT / LB_PASSWORD 覆盖），再访问目标页截图，
 * 同时把页面上的 .toolbar 按钮文本打印出来，便于核对按钮增删。
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9447;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-shot-profile');
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
  const name = process.argv[3] || 'shot.png';
  const width = parseInt(process.argv[4] || '1280', 10);

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
    // --guest：跳过登录，用于截游客视角的页面（落地页等）
    const isGuest = process.argv.includes('--guest');
    if (isGuest) {
      console.log('登录: 跳过（--guest）');
    } else {
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
    }

    await cdp.send('Page.navigate', { url }, sessionId);
    await sleep(2200);

    // 打印最终落地的地址 —— 验证 302 分流时特别有用
    const landed = await evalJs('location.pathname');
    console.log('最终落地路径: ' + landed);

    // 传 --bottom 时滚到页面底部再看（长页面上的页脚在底部）
    if (process.argv.includes('--bottom')) {
      await evalJs('window.scrollTo(0, document.documentElement.scrollHeight); true');
      await sleep(700);
    }

    const buttons = await evalJs(`(function () {
      var bar = document.querySelector('.toolbar');
      if (!bar) { return []; }
      return Array.prototype.slice.call(bar.querySelectorAll('a.btn, button.btn'))
        .map(function (b) { return b.textContent.trim().replace(/\\s+/g, ' '); });
    })()`);
    console.log('toolbar 按钮: ' + (buttons.length ? buttons.join(' | ') : '（无）'));

    // --click=<selector>：截图前先点一下（用于打开弹窗之类）
    const clickArg = process.argv.find(a => a.startsWith('--click='));
    if (clickArg) {
      const selector = clickArg.slice('--click='.length);
      const clicked = await evalJs(`(function () {
        var el = document.querySelector(${JSON.stringify(selector)});
        if (!el) { return '选择器没匹配到元素'; }
        el.click();
        return '已点击 ' + ${JSON.stringify(selector)};
      })()`);
      console.log('点击: ' + clicked);
      await sleep(900);
    }

    // --scroll=<选择器|text:标题文字>：截图前滚动到指定位置
    const scrollArg = process.argv.find(a => a.startsWith('--scroll='));
    if (scrollArg) {
      const target = scrollArg.slice('--scroll='.length);
      const scrolled = await evalJs(`(function () {
        var t = ${JSON.stringify(target)};
        var el = null;
        if (t.indexOf('text:') === 0) {
          var want = t.slice(5);
          var heads = document.querySelectorAll('.card-head h2, h2');
          for (var i = 0; i < heads.length; i++) {
            if (heads[i].textContent.trim() === want) { el = heads[i].closest('.card') || heads[i]; break; }
          }
          if (!el) { return '没找到标题为「' + want + '」的区块'; }
        } else {
          el = document.querySelector(t);
          if (!el) { return '选择器没匹配到元素'; }
        }
        el.scrollIntoView({ block: 'start' });
        return '已滚动到 ' + t;
      })()`);
      console.log('滚动: ' + scrolled);
      await sleep(700);
    }

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
    const out = path.join(OUT_DIR, name);
    fs.writeFileSync(out, Buffer.from(shot.data, 'base64'));
    console.log('截图: ' + out);
  } catch (e) {
    code = 1;
    console.error('异常: ' + e.message);
  } finally {
    proc.kill();
  }
  process.exitCode = code;
}

main();
