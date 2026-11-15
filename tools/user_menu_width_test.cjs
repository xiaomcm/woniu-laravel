/**
 * 用户下拉菜单「留白宽度」检测（无头 Edge / CDP）
 *
 * 用法：node tools/user_menu_width_test.cjs [url] [width]
 *
 * 背景：面板以前写死 min-width: 208px，菜单项文字只占 ~96px，
 * 右侧空出一大片，视觉不协调。现在改成 width: max-content 贴合内容，
 * 本脚本量出「面板右边界 - 菜单项文字右边界」的空白，判定是否还有多余预留。
 *
 * 同时顺带校验：
 *   - 头部用户名/副标题仍然水平居中
 *   - 面板宽度 <= 220px（贴合内容后应该明显变窄）
 *   - 菜单项文字没有被裁切
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9452;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-umenu-w-profile');
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
    await sleep(2200);

    const opened = await evalJs(`(function () {
      var btn = document.querySelector('[data-user-menu]');
      if (!btn) { return '按钮不存在'; }
      btn.click();
      return '已点击';
    })()`);
    console.log('打开菜单: ' + opened);
    await sleep(700);

    const info = await evalJs(`(function () {
      var panel = document.querySelector('.user-dropdown');
      if (!panel) { return null; }
      var pb = panel.getBoundingClientRect();
      var pcs = getComputedStyle(panel);

      function textRect(el) {
        var range = document.createRange();
        var best = null;
        for (var i = 0; i < el.childNodes.length; i++) {
          var n = el.childNodes[i];
          if (n.nodeType === 3 && n.textContent.trim() !== '') {
            range.selectNodeContents(n);
            var r = range.getBoundingClientRect();
            if (r.width > 0 && (!best || r.right > best.right)) { best = { left: r.left, right: r.right, width: r.width }; }
          }
        }
        return best;
      }

      var items = [];
      var nodes = panel.querySelectorAll('a, button');
      for (var i = 0; i < nodes.length; i++) {
        var el = nodes[i];
        var eb = el.getBoundingClientRect();
        var tr = textRect(el);
        var svg = el.querySelector('svg');
        var sb = svg ? svg.getBoundingClientRect() : null;
        items.push({
          text: (el.textContent || '').trim(),
          itemLeft: eb.left, itemRight: eb.right, itemWidth: eb.width,
          textLeft: tr ? tr.left : null, textRight: tr ? tr.right : null, textWidth: tr ? tr.width : null,
          iconRight: sb ? sb.right : null,
          clipped: el.scrollWidth > el.clientWidth + 1,
        });
      }

      var head = panel.querySelector('.user-dropdown-head');
      var hb = head ? head.getBoundingClientRect() : null;
      var hcs = head ? getComputedStyle(head) : null;
      var strong = head ? head.querySelector('strong') : null;
      var span = head ? head.querySelector('span') : null;
      var sb2 = strong ? strong.getBoundingClientRect() : null;
      var pb2 = span ? span.getBoundingClientRect() : null;

      return {
        panel: { left: pb.left, right: pb.right, width: pb.width, padLeft: pcs.paddingLeft, padRight: pcs.paddingRight, minWidth: pcs.minWidth, widthProp: pcs.width },
        items: items,
        head: hb ? {
          left: hb.left, right: hb.right, width: hb.width,
          textAlign: hcs.textAlign,
          strong: sb2 ? { text: strong.textContent.trim(), left: sb2.left, right: sb2.right, width: sb2.width } : null,
          span: pb2 ? { text: span.textContent.trim(), left: pb2.left, right: pb2.right, width: pb2.width } : null,
        } : null,
      };
    })()`);

    if (!info) {
      console.error('✗ 没找到 .user-dropdown');
      code = 1;
    } else {
      const p = info.panel;
      console.log('');
      console.log('面板: left=' + p.left.toFixed(1) + ' right=' + p.right.toFixed(1)
        + ' width=' + p.width.toFixed(1) + '  padding=' + p.padLeft + '/' + p.padRight
        + '  min-width=' + p.minWidth);
      console.log('');
      console.log('菜单项：');
      console.log('  ' + '文本'.padEnd(14) + '文字宽'.padStart(8) + '右侧空白'.padStart(10) + '左侧占用'.padStart(10) + '  裁切');
      for (const it of info.items) {
        const rightGap = it.textRight === null ? -1 : (p.right - parseFloat(p.padRight) - it.textRight);
        const leftUse = it.textLeft === null ? -1 : (it.textLeft - (p.left + parseFloat(p.padLeft)));
        console.log('  ' + it.text.padEnd(14) + it.textWidth.toFixed(1).padStart(8)
          + rightGap.toFixed(1).padStart(10) + leftUse.toFixed(1).padStart(10)
          + (it.clipped ? '   是' : '   否'));
      }

      console.log('');
      const checks = [];
      const maxRightGap = Math.max(...info.items.map(it =>
        it.textRight === null ? -1 : (p.right - parseFloat(p.padRight) - it.textRight)));
      checks.push({
        name: '菜单项右侧空白 ≤ 24px',
        ok: maxRightGap >= 0 && maxRightGap <= 24,
        detail: '最大空白 ' + maxRightGap.toFixed(1) + 'px',
      });
      checks.push({
        name: '面板宽度 ≤ 220px（贴合内容后应明显收窄）',
        ok: p.width <= 220,
        detail: '实际 ' + p.width.toFixed(1) + 'px',
      });
      checks.push({
        name: '菜单项文字未被裁切',
        ok: info.items.every(it => !it.clipped),
        detail: info.items.filter(it => it.clipped).map(it => it.text).join('、') || '无',
      });
      if (info.head) {
        const hc = (info.head.left + info.head.right) / 2;
        for (const key of ['strong', 'span']) {
          const el = info.head[key];
          if (!el) { checks.push({ name: '头部 ' + key + ' 存在', ok: false, detail: '缺失' }); continue; }
          const c = (el.left + el.right) / 2;
          const off = Math.abs(c - hc);
          checks.push({
            name: '头部 ' + key + ' 水平居中',
            ok: off <= 1.5,
            detail: '偏移 ' + off.toFixed(2) + 'px',
          });
        }
      }

      for (const c of checks) {
        console.log((c.ok ? '  [PASS] ' : '  [FAIL] ') + c.name + '  (' + c.detail + ')');
      }
      const allOk = checks.every(c => c.ok);
      console.log('');
      console.log(allOk ? '全部通过' : '存在不通过项');
      if (!allOk) code = 1;

      // 压力测试：把头部用户名/邮箱换成超长文本，面板不能被撑爆到超出视口
      const stress = await evalJs(`(function () {
        var head = document.querySelector('.user-dropdown-head');
        if (!head) { return null; }
        var strong = head.querySelector('strong');
        var span = head.querySelector('span');
        var oldS = strong ? strong.textContent : '';
        var oldP = span ? span.textContent : '';
        if (strong) { strong.textContent = 'administrator_super_long_account_name_2026'; }
        if (span) { span.textContent = '管理员 · a-very-long-user-email-address@example-domain.com'; }
        var panel = document.querySelector('.user-dropdown');
        var pb = panel.getBoundingClientRect();
        var s2 = strong ? strong.getBoundingClientRect() : null;
        var p2 = span ? span.getBoundingClientRect() : null;
        var res = {
          panelWidth: pb.width, panelLeft: pb.left, panelRight: pb.right, viewport: window.innerWidth,
          strongW: s2 ? s2.width : null, spanW: p2 ? p2.width : null,
          strongClipped: strong ? strong.scrollWidth > strong.clientWidth + 1 : null,
          spanClipped: span ? span.scrollWidth > span.clientWidth + 1 : null,
        };
        if (strong) { strong.textContent = oldS; }
        if (span) { span.textContent = oldP; }
        return res;
      })()`);

      if (stress) {
        // 面板是 right:0 贴右对齐，右边界等于视口宽度是正常的；
        // 真正的失败是面板整体超出视口（左侧跑到屏幕外）。
        const overflow = stress.panelRight > stress.viewport + 1
          || stress.panelLeft < 0
          || stress.panelWidth > stress.viewport;
        const clipped = stress.strongClipped && stress.spanClipped;
        console.log('');
        console.log('长文本压力测试: 面板宽=' + stress.panelWidth.toFixed(1)
          + 'px  左=' + stress.panelLeft.toFixed(1)
          + '  右=' + stress.panelRight.toFixed(1) + '  视口=' + stress.viewport + 'px');
        console.log((!overflow ? '  [PASS] ' : '  [FAIL] ') + '面板未溢出视口');
        console.log((clipped ? '  [PASS] ' : '  [FAIL] ') + '超长用户名/邮箱已截断为省略号'
          + (clipped ? '' : ' (strong=' + stress.strongClipped + ' span=' + stress.spanClipped + ')'));
        if (overflow || !clipped) code = 1;
      }
    }

    const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
    const out = path.join(OUT_DIR, 'user-menu-width-' + width + '.png');
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
