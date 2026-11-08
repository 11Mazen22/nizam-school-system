const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8080';
const WIDTHS = [1440, 1280, 1024, 768, 390, 360];
const LANGS = ['ar', 'en'];
const PAGES = [
  '/dashboard',
  '/students',
  '/students/1',
  '/teachers',
  '/teachers/1',
  '/attendance',
  '/exams',
  '/reports',
  '/academic-years',
  '/settings/profile'
];
const LEAK_RE = /\b[a-z][a-z0-9_]+(?:\.[a-z][a-z0-9_]+)+\b/g;

(async () => {
  const browser = await chromium.launch();
  let totalChecks = 0;
  let issues = 0;

  for (const lang of LANGS) {
    for (const w of WIDTHS) {
      const ctx = await browser.newContext({ viewport: { width: w, height: 800 } });
      const page = await ctx.newPage();
      const errs = [];
      page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 160)); });

      // Sign in
      await page.goto(`${BASE}/login`, { waitUntil: 'load' });
      await page.fill('#username', 'admin');
      await page.fill('#password', 'Dev-Test-2026');
      await Promise.all([
        page.waitForURL(/dashboard|login/, { timeout: 15000 }).catch(() => null),
        page.click('button[type="submit"]')
      ]);
      await page.goto(`${BASE}/lang?to=${lang}`, { waitUntil: 'load' });

      for (const p of PAGES) {
        totalChecks++;
        const res = await page.goto(`${BASE}${p}`, { waitUntil: 'load' });
        await page.waitForTimeout(150);
        const metrics = await page.evaluate(() => ({
          overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
          text: document.body.innerText
        }));

        const leaks = (metrics.text.match(LEAK_RE) || []).filter(k => 
          !k.startsWith('http') && !k.endsWith('.php') && !k.endsWith('.png') && !k.endsWith('.js') && !k.endsWith('.css') &&
          !k.includes('.com') && !k.includes('.org') && !k.includes('.net') && !k.includes('.edu') && !k.includes('.gov') &&
          !k.includes('example.')
        );

        if (res.status() >= 400 || metrics.overflow || errs.length > 0 || leaks.length > 0) {
          issues++;
          console.log(`ISSUE: [${lang} ${w}px] ${p} -> status:${res.status()}, overflow:${metrics.overflow}, errs:${errs.length}, leaks:${leaks.join(',')}`);
        }
      }
      await ctx.close();
    }
  }
  await browser.close();
  console.log(`RESPONSIVE VERIFICATION COMPLETE: ${totalChecks} checks executed, ${issues} issues found.`);
})();
