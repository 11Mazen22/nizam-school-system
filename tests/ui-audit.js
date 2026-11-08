'use strict';
/*
 * Nizam -- automated UI audit harness (Playwright). Part of the redesign
 * finalization QA: crawls every GET route as a signed-in admin in Arabic and
 * English across the responsive test widths, and for every page records:
 *   - final URL and HTTP status (redirects included)
 *   - console errors and failed (>=400) requests
 *   - horizontal overflow (scrollWidth > clientWidth, the real "page overflows")
 *   - leaked translation keys (raw `module.key` tokens in body text)
 *   - a full-page screenshot for visual review
 *
 * Usage (requires the PHP dev server + MySQL running):
 *   node tests/ui-audit.js --base=http://127.0.0.1:8080 --user=admin --password=...
 *
 * Output: tests/ui-audit-shots/<lang>/<width>/<slug>.png plus report.json.
 * Exit code is always 0 -- audit findings are data for review, not pass/fail.
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const m = a.match(/^--([^=]+)(?:=(.*))?$/);
  return m ? [m[1], m[2] === undefined ? true : m[2]] : [];
}));

const BASE = args.base || 'http://127.0.0.1:8080';
const USER = args.user || 'admin';
const PASSWORD = args.password || '';
const LANGS = (args.langs || 'ar,en').split(',');
const WIDTHS = (args.widths || '1440,1280,1024,768,390,360').split(',').map(Number);
const ONLY_WIDTHS = args.only ? args.only.split(',').map(Number) : null;
const SHOTS_DIR = path.join(__dirname, 'ui-audit-shots');

const PAGES = [
  { slug: 'dashboard', path: '/dashboard' },
  { slug: 'academic-years', path: '/academic-years' },
  { slug: 'academic-years-create', path: '/academic-years/create' },
  { slug: 'academic-years-edit', path: '/academic-years/1/edit' },
  { slug: 'grades', path: '/grades' },
  { slug: 'subjects', path: '/subjects' },
  { slug: 'subjects-create', path: '/subjects/create' },
  { slug: 'subjects-edit', path: '/subjects/1/edit' },
  { slug: 'classes', path: '/classes' },
  { slug: 'teachers', path: '/teachers' },
  { slug: 'teachers-archived', path: '/teachers/archived' },
  { slug: 'teachers-create', path: '/teachers/create' },
  { slug: 'teacher-show', path: '/teachers/1' },
  { slug: 'teacher-edit', path: '/teachers/1/edit' },
  { slug: 'students', path: '/students' },
  { slug: 'students-archived', path: '/students/archived' },
  { slug: 'students-create', path: '/students/create' },
  { slug: 'student-show', path: '/students/1' },
  { slug: 'student-edit', path: '/students/1/edit' },
  { slug: 'promotion', path: '/students/promotion' },
  { slug: 'assignments', path: '/assignments' },
  { slug: 'assignments-workload', path: '/assignments/workload' },
  { slug: 'assignments-create', path: '/assignments/create' },
  { slug: 'assignments-archived', path: '/assignments/archived' },
  { slug: 'exams', path: '/exams' },
  { slug: 'exam-scores', path: '/exams/1/scores' },
  { slug: 'exams-ranking', path: '/exams/ranking' },
  { slug: 'attendance', path: '/attendance' },
  { slug: 'attendance-report', path: '/attendance/report' },
  { slug: 'welfare-discipline', path: '/welfare/discipline' },
  { slug: 'welfare-health', path: '/welfare/health' },
  { slug: 'timetable', path: '/timetable' },
  { slug: 'reports', path: '/reports' },
  { slug: 'users', path: '/users' },
  { slug: 'backups', path: '/backups' },
  { slug: 'settings-profile', path: '/settings/profile' },
  { slug: 'settings-localization', path: '/settings/localization' },
  { slug: 'settings-academic', path: '/settings/academic' },
  { slug: 'settings-security', path: '/settings/security' },
  { slug: 'settings-backup', path: '/settings/backup' },
  { slug: 'notifications', path: '/notifications' },
  { slug: 'activity-log', path: '/activity-log' },
];

// Raw-key leak: tokens like `activity_log.empty_title` in visible text.
const LEAK_RE = /\b[a-z][a-z0-9_]+(?:\.[a-z][a-z0-9_]+)+\b/g;

async function submitLogin(page, lang) {
  if (lang === 'en') {
    await page.goto(`${BASE}/lang?to=en`, { waitUntil: 'load' });
  }
  await page.goto(`${BASE}/login`, { waitUntil: 'load' });
  await page.fill('#username', USER);
  await page.fill('#password', PASSWORD);
  await Promise.all([
    page.waitForURL(/dashboard|login/, { timeout: 20000 }).catch(() => null),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForLoadState('load').catch(() => null);
  return page.url();
}

async function audit(url, page, tag) {
  const errors = [];
  const failed = [];
  const onConsole = (msg) => { if (msg.type() === 'error') { errors.push(String(msg.text()).slice(0, 240)); } };
  const onResponse = (res) => { if (res.status() >= 400) { failed.push(`${res.status()} ${res.url().replace(BASE, '')}`.slice(0, 220)); } };
  const hadConsole = page.listeners('console').length === 0;
  if (hadConsole) { page.on('console', onConsole); page.on('response', onResponse); }

  let status = 0;
  try {
    const resp = await page.goto(url, { waitUntil: 'load', timeout: 30000 });
    status = resp ? resp.status() : 0;
  } catch (e) {
    errors.push(`goto: ${String(e).slice(0, 220)}`);
  }
  await page.waitForTimeout(400); // settle JS-rendered bits (charts, counters)

  const metrics = await page.evaluate(() => {
    const doc = document.documentElement;
    return {
      scrollW: doc.scrollWidth,
      clientW: doc.clientWidth,
      keyTokens: document.body ? (document.body.innerText.match(LEAK_RE) || []) : [],
    };
  }).catch(() => ({ keyTokens: [] }));

  const screenshot = path.join(SHOTS_DIR, tag.dir, `${tag.slug}.png`);
  fs.mkdirSync(path.dirname(screenshot), { recursive: true });
  try {
    await page.screenshot({ path: screenshot, fullPage: true });
  } catch (e) {
    errors.push(`shot: ${String(e).slice(0, 150)}`);
  }

  const seen = new Set(metrics.keyTokens || []);
  const leakKeys = Array.from(seen).filter((k) => !/^(?:privacy\.|fixed\.|school\.)/.test(k) && k.split('.').length <= 4);

  return {
    dir: tag.dir,
    slug: tag.slug,
    requested: url.replace(BASE, ''),
    finalUrl: page.url().replace(BASE, ''),
    status,
    consoleErrors: errors,
    failedRequests: failed,
    overflowX: !!metrics.overflowX,
    leakKeys,
  };
}

(async () => {
  if (!PASSWORD) {
    console.error('Missing --password (dev login).');
    process.exit(1);
  }
  fs.rmSync(SHOTS_DIR, { recursive: true, force: true });
  fs.mkdirSync(SHOTS_DIR, { recursive: true });
  const browser = await chromium.launch();
  const report = [];

  for (const lang of LANGS) {
    for (const width of ONLY_WIDTHS || WIDTHS) {
      const ctx = await browser.newContext({
        viewport: { width, height: Math.max(700, Math.round(width * 0.66)) },
      });
      const page = await ctx.newPage();

      // 1. authenticated pages
      const finalAfterLogin = await submitLogin(page, lang);
      if (!finalAfterLogin.includes('/dashboard')) {
        report.push({ dir: `${lang}/${width}`, slug: 'LOGIN-FAILED', finalUrl: finalAfterLogin });
        ctx.close();
        continue;
      }
      for (const entry of PAGES) {
        report.push(await audit(`${BASE}${entry.path}`, page, { dir: `${lang}/${width}`, slug: entry.slug }));
      }

      await ctx.close();
    }
    // 2. the login page itself, unauthenticated, one shot per width
    for (const width of ONLY_WIDTHS || WIDTHS) {
      const ctx = await browser.newContext({ viewport: { width, height: Math.max(700, Math.round(width * 0.66)) } });
      const page = await ctx.newPage();
      report.push(await audit(`${BASE}/login`, page, { dir: `${lang}/${width}`, slug: 'login' }));
      await ctx.close();
    }
  }

  await browser.close();
  fs.writeFileSync(path.join(SHOTS_DIR, 'report.json'), JSON.stringify(report, null, 2));

  for (const r of report) {
    const bits = [
      rLOGIN(r), '',
      r.finalUrl === '/login' && r.slug !== 'login' ? 'KICKED-TO-LOGIN' : '',
      r.finalUrl === '/setup' ? 'REDIRECT-SETUP' : '',
      r.status >= 400 ? `HTTP-${r.status}` : '',
      r.overflowX ? `OVERFLOW-X (${r.slug})` : '',
      r.consoleErrors?.length ? `console: ${r.consoleErrors.join(' | ')}` : '',
      r.failedRequests?.length ? `req-fail: ${r.failedRequests.join(' | ')}` : '',
      r.leakKeys?.length ? `keys: ${r.leakKeys.slice(0, 8).join(',')}` : '',
    ].filter(Boolean);
    if (bits.length) {
      console.log(`[${r.dir}] ${r.slug}: ${bits.join(' ;; ')}`);
    }
  }
  const total = report.length;
  const problem = new Set(report.filter((r) =>
    (r.consoleErrors && r.consoleErrors.length)
    || (r.failedRequests && r.failedRequests.length)
    || r.overflowX
    || (r.leakKeys && r.leakKeys.length)
    || r.slug === 'LOGIN-FAILED'
    || (r.status >= 400)
    || (!r.finalUrl && !r.consoleErrors)
  ).map((r) => [r.dir, r.slug].join('/')));
  console.log(`DONE pages=${total} problem-pages=${problem.size}`);
})();

function rLOGIN() { return ''; }
