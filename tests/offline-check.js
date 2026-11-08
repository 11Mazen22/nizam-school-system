'use strict';
/*
 * Nizam -- offline-first verification for the service worker.
 *
 * Checks the four behaviors the school depends on when the internet is
 * down or the LAN is gone:
 *   1. The app shell (CSS/JS/fonts) is served from the cache offline.
 *   2. A page visited while online is still readable offline.
 *   3. A page never visited offline falls back to the offline document
 *      instead of a browser error page.
 *   4. Signing out drops the dynamic page cache, so the next person on the
 *      machine cannot open the previous user's pages.
 *
 * Usage: node tests/offline-check.js --base=http://127.0.0.1:8080 --user=admin --password=...
 */

const { chromium } = require('playwright');

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const m = a.match(/^--([^=]+)(?:=(.*))?$/);
  return m ? [m[1], m[2] === undefined ? true : m[2]] : [];
}));

const BASE = args.base || 'http://127.0.0.1:8080';
const USER = args.user || 'admin';
const PASSWORD = args.password || '';

const results = [];
function check(name, pass, detail) {
  results.push({ name, pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'}: ${name}${detail ? ` -- ${detail}` : ''}`);
}

(async () => {
  if (!PASSWORD) {
    console.error('Missing --password (dev login).');
    process.exit(1);
  }

  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();

  // 1. Sign in and let the worker install + cache the shell.
  await page.goto(`${BASE}/login`, { waitUntil: 'load' });
  await page.fill('#username', USER);
  await page.fill('#password', PASSWORD);
  await Promise.all([
    page.waitForURL(/dashboard|login/, { timeout: 20000 }).catch(() => null),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForTimeout(1500);

  const controlled = await page.evaluate(async () => {
    if (!('serviceWorker' in navigator)) return false;
    const reg = await navigator.serviceWorker.getRegistration();
    return !!(reg && (reg.active || reg.installing || reg.waiting));
  });
  check('service worker registered', controlled);

  // Visit a real page so it lands in the dynamic cache, and let the shell
  // assets settle into the static cache.
  await page.goto(`${BASE}/students`, { waitUntil: 'load' });
  await page.waitForTimeout(1200);

  const cacheNames = await page.evaluate(async () => {
    const keys = await caches.keys();
    const out = {};
    for (const key of keys) {
      const cache = await caches.open(key);
      out[key] = (await cache.keys()).length;
    }
    return out;
  });
  const dynamicEntries = Object.entries(cacheNames)
    .filter(([name]) => name.includes('dynamic'))
    .reduce((sum, [, count]) => sum + count, 0);
  check('dynamic cache holds visited pages', dynamicEntries > 0, JSON.stringify(cacheNames));

  // 2. Go offline and read a page that was cached while online.
  await ctx.setOffline(true);
  await page.goto(`${BASE}/students`, { waitUntil: 'load' }).catch(() => null);
  const offlineStudents = await page.evaluate(() => ({
    title: document.title,
    hasShell: !!document.querySelector('link[href*="app.css"]'),
    bodyLength: document.body ? document.body.innerText.length : 0,
  }));
  check(
    'cached page readable offline',
    offlineStudents.bodyLength > 200 && offlineStudents.hasShell,
    `title="${offlineStudents.title}" text=${offlineStudents.bodyLength} chars`
  );

  // 3. A page never visited must fall back to the offline document.
  await page.goto(`${BASE}/settings/security`, { waitUntil: 'load' }).catch(() => null);
  const offlineFallback = await page.evaluate(() => ({
    title: document.title,
    text: document.body ? document.body.innerText.slice(0, 200) : '',
  }));
  const fallbackText = `${offlineFallback.title} ${offlineFallback.text}`;
  check(
    'uncached page falls back to the offline document',
    /offline|غير متصل/i.test(fallbackText),
    fallbackText.replace(/\s+/g, ' ').slice(0, 90)
  );

  // 4. Sign out online again and confirm the personal page cache is gone.
  await ctx.setOffline(false);
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'load' }).catch(() => null);
  await page.waitForTimeout(600);
  const before = await page.evaluate(async () => {
    const keys = await caches.keys();
    const urls = [];
    for (const key of keys) {
      if (!key.includes('dynamic')) continue;
      const cache = await caches.open(key);
      for (const request of await cache.keys()) urls.push(new URL(request.url).pathname);
    }
    return urls;
  });

  const loggedOut = await page.evaluate(async () => {
    const trigger = document.querySelector('[data-bs-target="#logoutConfirm"]');
    if (trigger && window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(document.getElementById('logoutConfirm')).show();
      await new Promise((resolve) => setTimeout(resolve, 400));
    }
    const button = document.querySelector('#logoutConfirm button[type="submit"]');
    if (!button) return false;
    button.click();
    return true;
  });
  await page.waitForTimeout(1800);

  const after = await page.evaluate(async () => {
    const keys = await caches.keys();
    const urls = [];
    for (const key of keys) {
      if (!key.includes('dynamic')) continue;
      const cache = await caches.open(key);
      for (const request of await cache.keys()) urls.push(new URL(request.url).pathname);
    }
    return urls;
  });

  const privateBefore = before.filter((url) => url !== '/login');
  const privateAfter = after.filter((url) => url !== '/login');
  check(
    'sign-out purges the previous user pages from the cache',
    loggedOut && privateBefore.length > 0 && privateAfter.length === 0,
    `private pages before=${privateBefore.length} after=${privateAfter.length}`
  );

  await ctx.close();
  await browser.close();

  const failed = results.filter((r) => !r.pass);
  console.log(`\n${results.length - failed.length}/${results.length} offline checks passed`);
  process.exit(failed.length === 0 ? 0 : 1);
})();
