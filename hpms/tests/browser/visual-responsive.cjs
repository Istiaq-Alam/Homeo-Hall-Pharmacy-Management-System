const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'visual-check@example.test');
  await page.fill('input[name="password"]', 'VisualCheck!234');
  await Promise.all([page.waitForURL('**/admin'), page.click('button[type="submit"]')]);
  await page.waitForLoadState('networkidle');

  for (const width of [1280, 1024, 768]) {
    await page.setViewportSize({ width, height: 900 });
    await page.waitForTimeout(150);
    const ov = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }));
    console.log(width, JSON.stringify(ov));
    await page.screenshot({ path: `shots/admin-${width}.png`, fullPage: true });
  }

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await mobile.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await mobile.fill('input[name="email"]', 'visual-check@example.test');
  await mobile.fill('input[name="password"]', 'VisualCheck!234');
  await Promise.all([mobile.waitForURL('**/admin'), mobile.click('button[type="submit"]')]);
  await mobile.waitForLoadState('networkidle');
  console.log('mobile admin overflow', JSON.stringify(await mobile.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }))));
  await mobile.screenshot({ path: 'shots/admin-mobile.png', fullPage: true });

  await browser.close();
})();
