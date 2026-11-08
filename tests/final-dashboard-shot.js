'use strict';
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  for (const lang of ['ar','en']) for (const w of [1440, 390]) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 } });
    const p = await ctx.newPage();
    const errs = [];
    p.on('console', m => { if (m.type()==='error') errs.push(m.text().slice(0,160)); });
    p.on('pageerror', e => errs.push('pageerror '+e.message));
    await p.goto('http://127.0.0.1:8080/login');
    await p.fill('#username','admin'); await p.fill('#password','Dev-Test-2026');
    await Promise.all([p.waitForURL(/dashboard/, {timeout:15000}), p.click('button[type="submit"]')]);
    await p.goto('http://127.0.0.1:8080/lang?to='+lang); await p.goto('http://127.0.0.1:8080/dashboard');
    await p.waitForTimeout(900);
    const o = await p.evaluate(() => ({ over: document.documentElement.scrollWidth > document.documentElement.clientWidth, h1: document.querySelector('h1')?.textContent, att: !!document.querySelector('#attendance-today-title'), cap: document.querySelectorAll('.n-capacity-item').length, attn: document.querySelectorAll('.n-attention-list li').length, raw: /\b(dashboard|attendance)\.[a-z_]+/.test(document.body.innerText) }));
    console.log(lang, w, JSON.stringify(o), errs.length ? errs : 'no console errors');
    await p.screenshot({ path: `tests/ui-audit-shots/final/dashboard-${lang}-${w}.png`, fullPage: true });
    await ctx.close();
  }
  await b.close();
})();
