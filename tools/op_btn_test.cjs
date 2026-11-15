/**
 * 图片卡片操作按钮一致性检测（无头 Edge / CDP）
 *
 * 用法：node tools/op_btn_test.cjs [url] [width]
 *
 * 背景：/images 页面上「详情页」「打开原图」两个按钮被压成 15px 宽的窄条，
 * 跟其余 27px 的按钮完全不一样（用户截图圈出反馈「这两个按钮畸形了」）。
 *
 * 根因是三件事叠加：
 *   1. .image-ops 是 flex 容器，卡片只有 ~172px 宽，6 个按钮装不下 → 触发 flex 收缩
 *   2. .op-btn 设了 width:28px 但没设 flex:none，收缩时 width 被无视
 *   3. <button> 有浏览器默认 padding:1px 6px，被 min-width:auto 保护在 27px；
 *      <a> 没有默认 padding，直接塌到 SVG 的 15px —— 所以只有这两个 <a> 变形
 *
 * 本脚本断言：同一张卡片里所有 .op-btn 的宽高完全一致（且为正方形）。
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const EDGE = [
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
].find(p => fs.existsSync(p));

const PORT = 9461;
const OUT_DIR = path.resolve(__dirname, '../storage/tmp');
const PROFILE = path.join(os.tmpdir(), 'woniu-opbtn-test-profile');
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
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params, sessionId }));
    });
  }
}

async function main() {
  const url = process.argv[2] || 'http://127.0.0.1:8102/images';
  const width = parseInt(process.argv[3] || '1280', 10);
  const account = process.env.LB_ACCOUNT || 'admin';
  const password = process.env.LB_PASSWORD || 'admin';

  const proc = spawn(EDGE, [
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${PROFILE}`,
    '--headless=new', '--disable-gpu', '--no-first-run',
    `--window-size=${width},900`,
    'about:blank',
  ], { stdio: 'ignore' });

  let wsUrl = null;
  for (let i = 0; i < 60; i++) {
    try {
      const r = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      wsUrl = (await r.json()).webSocketDebuggerUrl;
      if (wsUrl) break;
    } catch (e) { /* 还没起来 */ }
    await sleep(300);
  }
  if (!wsUrl) { proc.kill(); throw new Error('无法连接无头 Edge'); }

  const ws = new WebSocket(wsUrl);
  await new Promise(r => ws.addEventListener('open', r));
  const cdp = new Cdp(ws);

  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  await cdp.send('Page.enable', {}, sessionId);
  await cdp.send('Runtime.enable', {}, sessionId);
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width, height: 900, deviceScaleFactor: 1, mobile: false,
  }, sessionId);

  // 登录
  await cdp.send('Page.navigate', { url: `${new URL(url).origin}/login` }, sessionId);
  await sleep(1200);
  await cdp.send('Runtime.evaluate', {
    expression: `(() => {
      const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
      set('input[name="name"], input[name="email"], input[name="account"], input[type="text"]', ${JSON.stringify(account)});
      set('input[type="password"]', ${JSON.stringify(password)});
      const f = document.querySelector('form'); if (f) f.submit();
      return 'ok';
    })()`,
  }, sessionId);
  await sleep(1800);

  await cdp.send('Page.navigate', { url }, sessionId);
  await sleep(1600);

  const probe = await cdp.send('Runtime.evaluate', {
    returnByValue: true,
    expression: `(() => {
      // 强制 overlay 显形，量的是最终渲染态
      const style = document.createElement('style');
      style.textContent = '.image-overlay{opacity:1 !important;}';
      document.head.appendChild(style);

      const cards = Array.from(document.querySelectorAll('.image-card'));
      const groups = [];

      for (const card of cards.slice(0, 12)) {
        const ops = card.querySelector('.image-ops');
        if (!ops) continue;
        const btns = Array.from(ops.querySelectorAll('.op-btn'));
        if (btns.length < 2) continue;

        const boxes = btns.map(b => {
          const r = b.getBoundingClientRect();
          return {
            tag: b.tagName.toLowerCase(),
            title: b.getAttribute('title') || '',
            w: Math.round(r.width * 100) / 100,
            h: Math.round(r.height * 100) / 100,
          };
        });

        const widths = [...new Set(boxes.map(b => b.w))];
        const heights = [...new Set(boxes.map(b => b.h))];
        groups.push({
          count: boxes.length,
          widths, heights,
          boxes,
          square: boxes.every(b => Math.abs(b.w - b.h) < 0.5),
          uniform: widths.length === 1 && heights.length === 1,
        });
      }
      return groups;
    })()`,
  }, sessionId);

  const groups = probe.result.value || [];

  if (groups.length === 0) {
    console.log('页面里没有可测的图片卡片（该页可能没有图片），跳过');
    ws.close(); proc.kill();
    return;
  }

  console.log(`共测 ${groups.length} 张卡片的操作按钮：\n`);
  let failed = 0;

  for (const g of groups) {
    const tag = g.boxes.map(b => `${b.title || b.tag}(${b.w}×${b.h})`).join('  ');
    console.log(`  ${g.count} 个按钮：${tag}`);

    if (!g.square) {
      console.log(`    [FAIL] 有按钮不是正方形（宽高不一致）`);
      failed++;
    }
    if (!g.uniform) {
      console.log(`    [FAIL] 按钮尺寸不统一：宽 ${JSON.stringify(g.widths)} / 高 ${JSON.stringify(g.heights)}`);
      failed++;
    }
  }

  const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
  if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });
  const file = path.join(OUT_DIR, `op-btn-${width}.png`);
  fs.writeFileSync(file, Buffer.from(shot.data, 'base64'));

  if (failed) {
    console.log(`\n  ${failed} 处不通过`);
    console.log('截图:', file);
    ws.close(); proc.kill();
    process.exit(1);
  }

  console.log('\n  [PASS] 所有操作按钮都是统一尺寸的正方形');
  console.log('截图:', file);

  ws.close();
  proc.kill();
}

main().catch(e => { console.error('失败:', e.message); process.exit(1); });
