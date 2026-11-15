/**
 * 本地模拟环境冒烟检查（真实浏览器 / CDP）
 *
 * 用法：
 *   node tools/local_smoke.cjs [url1] [url2] ...
 *   node tools/local_smoke.cjs http://127.0.0.1:8102/ http://127.0.0.1:8102/login
 *
 * 输出：每个 URL 的控制台错误、未捕获异常、失败/4xx/5xx 请求，以及一张截图。
 * 截图落在 storage/tmp/smoke-<序号>.png
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE_CANDIDATES = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
];

const DEBUG_PORT = 9333;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE_DIR = path.join(os.tmpdir(), 'woniu-smoke-profile');

function findEdge() {
  for (const p of EDGE_CANDIDATES) if (fs.existsSync(p)) return p;
  throw new Error('未找到 Edge 可执行文件');
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function httpJson(url, tries = 40) {
  for (let i = 0; i < tries; i++) {
    try {
      const res = await fetch(url);
      if (res.ok) return await res.json();
    } catch (e) { /* 还没起来，继续等 */ }
    await sleep(250);
  }
  throw new Error('CDP 调试端口无响应: ' + url);
}

class Cdp {
  constructor(ws) {
    this.ws = ws;
    this.id = 0;
    this.pending = new Map();
    this.handlers = [];
    ws.addEventListener('message', (ev) => {
      const msg = JSON.parse(ev.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
      } else if (msg.method) {
        this.handlers.forEach(h => h(msg));
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
        if (this.pending.has(id)) {
          this.pending.delete(id);
          reject(new Error('CDP 超时: ' + method));
        }
      }, 30000);
    });
  }
  on(fn) { this.handlers.push(fn); }
}

async function main() {
  const urls = process.argv.slice(2);
  if (!urls.length) urls.push('http://127.0.0.1:8102/');

  const edge = findEdge();
  const proc = spawn(edge, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-extensions',
    '--hide-scrollbars',
    '--remote-debugging-port=' + DEBUG_PORT,
    '--user-data-dir=' + PROFILE_DIR,
    'about:blank',
  ], { stdio: 'ignore', detached: false });

  fs.mkdirSync(OUT_DIR, { recursive: true });

  let exitCode = 0;
  try {
    const version = await httpJson(`http://127.0.0.1:${DEBUG_PORT}/json/version`);
    const ws = new WebSocket(version.webSocketDebuggerUrl);
    await new Promise((res, rej) => {
      ws.addEventListener('open', res, { once: true });
      ws.addEventListener('error', rej, { once: true });
    });
    const cdp = new Cdp(ws);

    const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });

    let idx = 0;
    for (const url of urls) {
      idx++;
      const consoleErrors = [];
      const exceptions = [];
      const badResponses = [];
      const failedRequests = [];

      const listener = (msg) => {
        if (msg.sessionId !== sessionId) return;
        const { method, params } = msg;
        if (method === 'Runtime.consoleAPICalled' && (params.type === 'error' || params.type === 'warning')) {
          const text = (params.args || []).map(a => a.value ?? a.description ?? a.type).join(' ');
          consoleErrors.push(`[${params.type}] ${text}`);
        } else if (method === 'Runtime.exceptionThrown') {
          const d = params.exceptionDetails || {};
          exceptions.push(d.exception?.description || d.text || 'unknown exception');
        } else if (method === 'Network.responseReceived') {
          const st = params.response?.status || 0;
          if (st >= 400) badResponses.push(`${st} ${params.response?.url}`);
        } else if (method === 'Network.loadingFailed') {
          failedRequests.push(`${params.errorText} ${params.type} ${params.requestId}`);
        }
      };
      cdp.on(listener);

      await cdp.send('Runtime.enable', {}, sessionId);
      await cdp.send('Network.enable', {}, sessionId);
      await cdp.send('Page.enable', {}, sessionId);
      await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 1440, height: 900, deviceScaleFactor: 1, mobile: false,
      }, sessionId);

      await cdp.send('Page.navigate', { url }, sessionId);
      await sleep(2200);

      const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
      const shotPath = path.join(OUT_DIR, `smoke-${idx}.png`);
      fs.writeFileSync(shotPath, Buffer.from(shot.data, 'base64'));

      const title = await cdp.send('Runtime.evaluate', {
        expression: 'document.title', returnByValue: true,
      }, sessionId);

      console.log(`\n=== ${url} ===`);
      console.log('标题: ' + (title.result?.value ?? '(无)'));
      console.log('截图: ' + shotPath);
      console.log('控制台错误/警告: ' + consoleErrors.length);
      consoleErrors.forEach(e => console.log('   ' + e));
      console.log('未捕获异常: ' + exceptions.length);
      exceptions.forEach(e => console.log('   ' + String(e).split('\n')[0]));
      console.log('失败请求: ' + failedRequests.length);
      failedRequests.forEach(e => console.log('   ' + e));
      console.log('HTTP >= 400 响应: ' + badResponses.length);
      badResponses.forEach(e => console.log('   ' + e));

      const bad = exceptions.length + failedRequests.length;
      if (bad > 0) exitCode = 1;
    }

    ws.close();
  } catch (e) {
    console.error('冒烟检查失败: ' + e.message);
    exitCode = 2;
  } finally {
    try { proc.kill(); } catch (e) { /* ignore */ }
  }
  process.exit(exitCode);
}

main();
