'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const match = a.match(/^--([^=]+)=(.*)$/);
  return match ? [match[1], match[2]] : [];
}));
const BASE = args.base || 'http://127.0.0.1:8000';
const USER = args.user || 'admin';
const PASSWORD = args.password || '';
const OUTPUT = path.resolve(args.out || path.join(__dirname, 'ui-audit-shots', 'final'));
(async () => {
  if (!PASSWORD) throw new Error('Pass --password=<dev admin password>');
  fs.mkdirSync(OUTPUT, { recursive: true });
  const b = await chromium.launch();
  for (const lang of ['ar','en']) for (const w of [1440, 390]) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 } });
    const p = await ctx.newPage();
    const errs = [];
    p.on('console', m => { if (m.type()==='error') errs.push(m.text().slice(0,160)); });
    p.on('pageerror', e => errs.push('pageerror '+e.message));
    await p.goto(BASE + '/login');
    await p.fill('#username',USER); await p.fill('#password',PASSWORD);
    await Promise.all([p.waitForURL(/dashboard/, {timeout:15000}), p.click('button[type="submit"]')]);
    await p.goto(BASE + '/lang?to='+lang); await p.goto(BASE + '/dashboard');
    await p.waitForTimeout(900);
    const o = await p.evaluate(() => ({ over: document.documentElement.scrollWidth > document.documentElement.clientWidth, h1: document.querySelector('h1')?.textContent, att: !!document.querySelector('#attendance-today-title'), cap: document.querySelectorAll('.n-capacity-item').length, attn: document.querySelectorAll('.n-attention-list li').length, raw: /\b(dashboard|attendance)\.[a-z_]+/.test(document.body.innerText) }));
    console.log(lang, w, JSON.stringify(o), errs.length ? errs : 'no console errors');
    await p.screenshot({ path: path.join(OUTPUT, `dashboard-${lang}-${w}.png`), fullPage: true });
    await ctx.close();
  }
  await b.close();
})();
