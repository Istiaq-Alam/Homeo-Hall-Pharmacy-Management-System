const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'visual-check@example.test');
  await page.fill('input[name="password"]', 'VisualCheck!234');
  await Promise.all([page.waitForURL('**/admin'), page.click('button[type="submit"]')]);
  await page.waitForLoadState('networkidle');

  const main = page.locator('main.page-wrap');
  const box = await main.evaluate((el) => ({ scrollHeight: el.scrollHeight, clientHeight: el.clientHeight }));
  console.log('main scroll', JSON.stringify(box));

  for (const [i, y] of [700, 1500, 2400, 3200].entries()) {
    await main.evaluate((el, top) => el.scrollTo({ top }), y);
    await page.waitForTimeout(200);
    await page.screenshot({ path: `shots/admin-mobile-scroll-${i + 1}.png` });
  }

  const tablet = await browser.newPage({ viewport: { width: 768, height: 1024 } });
  await tablet.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await tablet.fill('input[name="email"]', 'visual-check@example.test');
  await tablet.fill('input[name="password"]', 'VisualCheck!234');
  await Promise.all([tablet.waitForURL('**/admin'), tablet.click('button[type="submit"]')]);
  await tablet.waitForLoadState('networkidle');
  await tablet.screenshot({ path: 'shots/admin-tablet-top.png' });
  await tablet.locator('main.page-wrap').evaluate((el) => el.scrollTo({ top: 900 }));
  await tablet.waitForTimeout(200);
  await tablet.screenshot({ path: 'shots/admin-tablet-mid.png' });

  await browser.close();
})();
