const { chromium } = require('playwright');
const path = require('path');

// Visual QA for the theme gallery and every theme's chrome.
// Needs a running `php artisan serve` and a local admin account; put that
// account's credentials here. Screenshots land in /shots.
const base = 'http://127.0.0.1:8000';
const email = 'visual-theme-check@example.test';
const password = 'VisualTheme!234';
const shots = path.join(__dirname, '..', '..', 'shots');
const shot = (name) => path.join(shots, name);

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(String(error)));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    await page.goto(base + '/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await Promise.all([page.waitForURL('**/admin'), page.click('button[type=submit]')]);

    await page.goto(base + '/themes');
    await page.waitForTimeout(600);
    await page.screenshot({ path: shot('theme-gallery-apple-1440.png'), fullPage: true });

    const card = page.locator('.theme-card', { hasText: 'Starbucks' }).first();
    await card.hover();
    await page.waitForTimeout(500);
    await page.screenshot({ path: shot('theme-gallery-preview-1440.png'), fullPage: true });

    const starbucks = page.locator('button[name=theme][value=starbucks]');
    await starbucks.click();
    await page.waitForURL('**/themes');
    await page.waitForTimeout(500);
    const applied = await page.evaluate(() => document.documentElement.dataset.theme);
    await page.screenshot({ path: shot('theme-gallery-starbucks-1440.png'), fullPage: true });

    await page.goto(base + '/dashboard');
    await page.waitForTimeout(800);
    await page.screenshot({ path: shot('theme-dashboard-starbucks-1440.png'), fullPage: true });

    for (const theme of ['linear', 'resend', 'cal', 'notion', 'intercom', 'mintlify', 'sap', 'zapier']) {
        await page.evaluate(async (theme) => {
            const token = document.querySelector('meta[name=csrf-token]').content;
            const body = new URLSearchParams({ theme });
            await fetch('/themes', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        }, theme);
        await page.goto(base + '/dashboard');
        await page.waitForTimeout(500);
        await page.screenshot({ path: shot(`theme-dashboard-${theme}-1440.png`), fullPage: true });
    }

    await page.goto(base + '/medicines');
    await page.waitForTimeout(400);
    await page.screenshot({ path: shot('theme-medicines-zapier-1440.png'), fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + '/themes');
    await page.waitForTimeout(500);
    await page.screenshot({ path: shot('theme-gallery-zapier-390.png'), fullPage: true });

    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto(base + '/themes');
    await page.waitForTimeout(500);
    await page.screenshot({ path: shot('theme-gallery-zapier-768.png'), fullPage: true });

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    console.log(JSON.stringify({ applied, overflow, errors }, null, 2));
    await browser.close();
})();
