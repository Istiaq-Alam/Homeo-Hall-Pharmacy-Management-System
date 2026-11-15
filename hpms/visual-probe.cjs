const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'visual-check@example.test');
  await page.fill('input[name="password"]', 'VisualCheck!234');
  await Promise.all([page.waitForURL('**/admin'), page.click('button[type="submit"]')]);
  await page.waitForLoadState('networkidle');

  const info = await page.evaluate(() => {
    const out = {
      body: { scrollHeight: document.body.scrollHeight, clientHeight: document.body.clientHeight },
      html: { scrollHeight: document.documentElement.scrollHeight },
    };
    out.blocks = [...document.querySelectorAll('main > *')].map((el) => {
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        tag: el.tagName,
        cls: el.className,
        top: Math.round(r.top + window.scrollY),
        left: Math.round(r.left),
        w: Math.round(r.width),
        h: Math.round(r.height),
        display: s.display,
        position: s.position,
        overflow: s.overflow,
        visibility: s.visibility,
        opacity: s.opacity,
      };
    });
    const main = document.querySelector('main');
    const ms = getComputedStyle(main);
    out.main = { display: ms.display, grid: ms.gridTemplateColumns, height: ms.height, overflow: ms.overflow, cls: main.className };
    out.wrappers = [...document.querySelectorAll('main *')].filter((el) => {
      const s = getComputedStyle(el);
      return s.display === 'none' || s.visibility === 'hidden';
    }).slice(0, 20).map((el) => el.tagName + '.' + el.className);
    return out;
  });
  console.log(JSON.stringify(info, null, 2));
  await browser.close();
})();
