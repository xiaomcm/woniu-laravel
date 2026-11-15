/**
 * 灯箱功能自动化验证（jsdom + 真实页面 HTML）
 * 用法：node /tmp/lbtest/test_lightbox.js
 */
const fs = require('fs');

let JSDOM;
try {
  ({ JSDOM } = require('jsdom'));
} catch (e) {
  console.error('缺少 jsdom。先装一次：');
  console.error('  npm install jsdom');
  console.error('（也可以指定已安装的位置：set NODE_PATH=/path/to/node_modules）');
  process.exit(2);
}

const BASE = 'http://127.0.0.1:8102';
const DETAIL = '/i/7735161c0d692a39e918e4081fc8327e56205386';
const path = require('path');
const LIGHTBOX_JS = path.resolve(__dirname, '../public/assets/lightbox.js');

let passed = 0;
let failed = 0;

function check(name, cond, extra) {
  if (cond) {
    passed++;
    console.log('  [通过] ' + name);
  } else {
    failed++;
    console.log('  [失败] ' + name + (extra ? '  →  ' + extra : ''));
  }
}

function fetchHtml(path) {
  const http = require('http');
  return new Promise((resolve, reject) => {
    http.get(BASE + path, (res) => {
      let data = '';
      res.on('data', (c) => (data += c));
      res.on('end', () => resolve(data));
    }).on('error', reject);
  });
}

async function main() {
  console.log('=== 灯箱功能验证 ===\n');
console.log('依赖：npm install jsdom（一次性），站点需在 8102 端口运行\n');

  const html = await fetchHtml(DETAIL);
  console.log('详情页 HTML ' + html.length + ' 字节\n');

  const dom = new JSDOM(html, {
    url: BASE + DETAIL,
    runScripts: 'outside-only',
    pretendToBeVisual: true
  });
  const { window } = dom;
  const { document } = window;

  // 注入灯箱脚本
  const code = fs.readFileSync(LIGHTBOX_JS, 'utf8');
  window.eval(code);

  console.log('--- 一、入口识别 ---');
  const triggers = document.querySelectorAll('[data-lightbox-src]');
  check('识别到 5 个灯箱入口', triggers.length === 5, '实际 ' + triggers.length);

  const main = document.querySelector('.detail-canvas');
  check('详情页主图是灯箱入口', !!main && main.hasAttribute('data-lightbox-src'));
  check(
    '主图入口带标题',
    main && main.getAttribute('data-lightbox-caption') &&
      main.getAttribute('data-lightbox-caption').length > 0,
    main ? main.getAttribute('data-lightbox-caption') : 'null'
  );

  console.log('\n--- 二、打开灯箱 ---');
  const before = window.location.href;

  // 灯箱 DOM 是首次打开时才创建的
  main.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));

  const box = document.getElementById('woniu-lightbox');
  check('灯箱 DOM 已创建', !!box);
  check('点击后灯箱打开', box && !box.hasAttribute('hidden'));
  check('body 锁定滚动', document.body.classList.contains('lb-locked'));
  check('点击主图没有跳转', window.location.href === before, window.location.href);

  const pic = box.querySelector('.lb-img');
  check(
    '灯箱展示原图地址',
    pic.getAttribute('src') === main.getAttribute('data-lightbox-src'),
    pic.getAttribute('src')
  );
  const counter = box.querySelector('.lb-counter');
  check('序号显示 1 / 5', counter.textContent === '1 / 5', counter.textContent);
  const caption = box.querySelector('.lb-caption');
  check(
    '标题取自 data-lightbox-caption',
    caption.textContent === main.getAttribute('data-lightbox-caption'),
    caption.textContent
  );
  check(
    '下载按钮指向原图',
    box.querySelector('.lb-download').getAttribute('href') === main.getAttribute('data-lightbox-src')
  );

  console.log('\n--- 三、键盘切换 ---');
  function key(k) {
    document.dispatchEvent(new window.KeyboardEvent('keydown', { key: k, bubbles: true }));
  }
  key('ArrowRight');
  check('→ 切到第 2 张', counter.textContent === '2 / 5', counter.textContent);
  check('图片地址已切换', pic.getAttribute('src') !== main.getAttribute('data-lightbox-src'));
  key('ArrowRight');
  key('ArrowRight');
  check('→ 连续切换到第 4 张', counter.textContent === '4 / 5', counter.textContent);
  key('ArrowLeft');
  check('← 退回第 3 张', counter.textContent === '3 / 5', counter.textContent);
  key('ArrowRight');
  key('ArrowRight');
  key('ArrowRight');
  check('末张再 → 回到第 1 张（循环）', counter.textContent === '1 / 5', counter.textContent);

  console.log('\n--- 四、缩放 ---');
  key('+');
  check('+ 放大', pic.style.transform.includes('scale(1.2)'), pic.style.transform);
  key('-');
  check('- 缩小', pic.style.transform.includes('scale(1)'), pic.style.transform);
  key('+');
  key('0');
  check('0 重置缩放', pic.style.transform.includes('scale(1)'), pic.style.transform);

  console.log('\n--- 五、关闭 ---');
  key('Escape');
  check('Esc 关闭灯箱', box.hasAttribute('hidden'));
  check('关闭后解除滚动锁定', !document.body.classList.contains('lb-locked'));

  console.log('\n--- 六、重新打开与遮罩关闭 ---');
  main.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
  check('可以再次打开', !box.hasAttribute('hidden'));
  box.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
  check('点遮罩关闭', box.hasAttribute('hidden'));

  console.log('\n--- 七、列表页按钮不触发卡片跳转 ---');
  const listHtml = await fetchHtml('/explore');
  const listDom = new JSDOM(listHtml, { url: BASE + '/explore', runScripts: 'outside-only' });
  listDom.window.eval(code);
  const listDoc = listDom.window.document;
  const btns = listDoc.querySelectorAll('button[data-lightbox-src]');
  check('探索广场有灯箱按钮', btns.length > 0, btns.length + ' 个');

  let cardClicked = false;
  const card = listDoc.querySelector('.gallery-item');
  card.addEventListener('click', () => (cardClicked = true));
  btns[0].dispatchEvent(new listDom.window.MouseEvent('click', { bubbles: true, cancelable: true }));
  check('点灯箱按钮不会冒泡到卡片', cardClicked === false);
  const listBox = listDoc.getElementById('woniu-lightbox');
  check('点灯箱按钮能打开灯箱', listBox && !listBox.hasAttribute('hidden'));

  console.log('\n--- 八、开关关闭后不生效 ---');
  const offHtml = html.replace(/data-lightbox-src="[^"]*"/g, '').replace(/data-lightbox-caption="[^"]*"/g, '');
  const offDom = new JSDOM(offHtml, { url: BASE + DETAIL, runScripts: 'outside-only' });
  offDom.window.eval(code);
  check('无入口时不创建灯箱', !offDom.window.document.getElementById('woniu-lightbox'));

  console.log('\n=== 结果：通过 ' + passed + '，失败 ' + failed + ' ===');
  process.exit(failed > 0 ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
