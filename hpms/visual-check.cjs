const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const problems = [];

  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('console', (m) => {
    if (m.type() === 'error') problems.push(`console: ${m.text()}`);
  });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('requestfailed', (r) => problems.push(`requestfailed: ${r.url()} ${r.failure()?.errorText}`));

  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.screenshot({ path: 'shots/login-desktop.png', fullPage: true });

  await page.fill('input[name="email"]', 'visual-check@example.test');
  await page.fill('input[name="password"]', 'VisualCheck!234');
  await Promise.all([page.waitForURL('**/admin'), page.click('button[type="submit"]')]);
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'shots/admin-desktop.png', fullPage: true });

  // horizontal overflow at desktop
  const overflow = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));
  console.log('desktop overflow', JSON.stringify(overflow));

  // mobile
  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await mobile.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await mobile.screenshot({ path: 'shots/login-mobile.png', fullPage: true });
  const mLogin = await mobile.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));
  console.log('mobile login overflow', JSON.stringify(mLogin));

  console.log('problems', JSON.stringify(problems, null, 2));
  await browser.close();
})();
