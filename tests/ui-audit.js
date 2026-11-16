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
 * Use --shots=false for a metrics-only crawl and --out=<directory> to keep
 * artifacts outside the repository. Findings fail the command unless --soft.
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
const TAKE_SHOTS = args.shots !== 'false';
const SOFT = args.soft === true || args.soft === 'true';
const SHOTS_DIR = path.resolve(args.out || path.join(__dirname, 'ui-audit-shots'));

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
  { slug: 'attendance-selected-class', path: '/attendance?class_id=1' },
  { slug: 'attendance-report', path: '/attendance/report' },
  { slug: 'welfare-discipline', path: '/welfare/discipline' },
  { slug: 'welfare-health', path: '/welfare/health' },
  { slug: 'timetable', path: '/timetable' },
  { slug: 'timetable-selected-class', path: '/timetable?class_id=1' },
  { slug: 'reports', path: '/reports' },
  { slug: 'report-religion', path: '/reports/religion' },
  { slug: 'report-class-list', path: '/reports/class-list' },
  { slug: 'report-density', path: '/reports/density' },
  { slug: 'report-teachers-by-subject', path: '/reports/teachers-by-subject' },
  { slug: 'report-workload', path: '/reports/workload' },
  { slug: 'report-shortage', path: '/reports/shortage' },
  { slug: 'users', path: '/users' },
  { slug: 'backups', path: '/backups' },
  { slug: 'settings-profile', path: '/settings/profile' },
  { slug: 'settings-localization', path: '/settings/localization' },
  { slug: 'settings-academic', path: '/settings/academic' },
  { slug: 'settings-security', path: '/settings/security' },
  { slug: 'settings-backup', path: '/settings/backup' },
  { slug: 'settings-modules', path: '/settings/modules' },
  { slug: 'settings-structure', path: '/settings/structure' },
  { slug: 'settings-terms', path: '/settings/terms' },
  { slug: 'settings-grading', path: '/settings/grading' },
  { slug: 'settings-offline', path: '/settings/offline' },
  { slug: 'guardians', path: '/guardians' },
  { slug: 'guardians-create', path: '/guardians/create' },
  { slug: 'admissions', path: '/admissions' },
  { slug: 'admissions-create', path: '/admissions/create' },
  { slug: 'finance', path: '/finance' },
  { slug: 'finance-invoices', path: '/finance/invoices' },
  { slug: 'finance-invoices-create', path: '/finance/invoices/create' },
  { slug: 'finance-structures', path: '/finance/structures' },
  { slug: 'hr', path: '/hr' },
  { slug: 'hr-leaves', path: '/hr/leaves' },
  { slug: 'transport', path: '/transport' },
  { slug: 'transport-create', path: '/transport/create' },
  { slug: 'library', path: '/library' },
  { slug: 'library-create', path: '/library/create' },
  { slug: 'library-borrows', path: '/library/borrows' },
  { slug: 'portal-teacher', path: '/portal/teacher' },
  { slug: 'portal-family', path: '/portal/family' },
  { slug: 'portal-student', path: '/portal/student' },
  { slug: 'notifications', path: '/notifications' },
  { slug: 'activity-log', path: '/activity-log' },
];

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
  const onPageError = (error) => { errors.push(`pageerror: ${String(error.message || error).slice(0, 220)}`); };
  page.on('console', onConsole);
  page.on('response', onResponse);
  page.on('pageerror', onPageError);

  let status = 0;
  try {
    const resp = await page.goto(url, { waitUntil: 'load', timeout: 30000 });
    status = resp ? resp.status() : 0;
  } catch (e) {
    errors.push(`goto: ${String(e).slice(0, 220)}`);
  }
  await page.waitForTimeout(250); // settle JS-rendered bits (charts, counters)

  const metrics = await page.evaluate(() => {
    const doc = document.documentElement;
    const width = doc.clientWidth;
    const overflowElements = Array.from(document.querySelectorAll('body *')).map((el) => {
      const rect = el.getBoundingClientRect();
      return { el, rect };
    }).filter(({ el, rect }) => {
      const style = getComputedStyle(el);
      return style.position !== 'fixed' && style.display !== 'none' && (rect.right > width + 2 || rect.left < -2);
    }).slice(0, 8).map(({ el, rect }) => ({
      tag: el.tagName.toLowerCase(),
      id: el.id || '',
      className: typeof el.className === 'string' ? el.className.slice(0, 100) : '',
      left: Math.round(rect.left),
      right: Math.round(rect.right),
      width: Math.round(rect.width),
    }));
    return {
      scrollW: doc.scrollWidth,
      clientW: doc.clientWidth,
      overflowX: doc.scrollWidth > doc.clientWidth + 1,
      overflowElements,
      keyTokens: document.body ? (document.body.innerText.match(/\b[a-z][a-z0-9_]+(?:\.[a-z][a-z0-9_]+)+\b/g) || []) : [],
    };
  }).catch((error) => ({ keyTokens: [], overflowX: false, overflowElements: [], metricError: String(error) }));

  if (TAKE_SHOTS) {
    const screenshot = path.join(SHOTS_DIR, tag.dir, `${tag.slug}.png`);
    fs.mkdirSync(path.dirname(screenshot), { recursive: true });
    try {
      await page.screenshot({ path: screenshot, fullPage: true });
    } catch (e) {
      errors.push(`shot: ${String(e).slice(0, 150)}`);
    }
  }

  page.off('console', onConsole);
  page.off('response', onResponse);
  page.off('pageerror', onPageError);

  const seen = new Set(metrics.keyTokens || []);
  const leakKeys = Array.from(seen).filter((k) =>
    !/^(?:privacy\.|fixed\.|school\.)/.test(k)
    && !/\.(?:com|org|net|edu|gov|php|png|jpe?g|js|css|xlsx?|csv|pdf|sql)$/i.test(k)
    && k.split('.').length <= 4
  );
  if (metrics.metricError) errors.push(`metrics: ${metrics.metricError}`);

  return {
    dir: tag.dir,
    slug: tag.slug,
    requested: url.replace(BASE, ''),
    finalUrl: page.url().replace(BASE, ''),
    status,
    consoleErrors: errors,
    failedRequests: failed,
    overflowX: !!metrics.overflowX,
    overflowElements: metrics.overflowElements || [],
    scrollWidth: metrics.scrollW || 0,
    clientWidth: metrics.clientW || 0,
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
        await ctx.close();
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
      await page.goto(`${BASE}/lang?to=${lang}`, { waitUntil: 'load' });
      report.push(await audit(`${BASE}/login`, page, { dir: `${lang}/${width}`, slug: 'login' }));
      report.push(await audit(`${BASE}/setup/recover-admin`, page, { dir: `${lang}/${width}`, slug: 'recover-admin' }));
      await ctx.close();
    }
  }

  await browser.close();
  fs.writeFileSync(path.join(SHOTS_DIR, 'report.json'), JSON.stringify(report, null, 2));

  for (const r of report) {
    const bits = [
      r.finalUrl === '/login' && r.slug !== 'login' ? 'KICKED-TO-LOGIN' : '',
      r.finalUrl === '/setup' ? 'REDIRECT-SETUP' : '',
      r.status >= 400 ? `HTTP-${r.status}` : '',
      r.overflowX ? `OVERFLOW-X ${r.scrollWidth}>${r.clientWidth} ${JSON.stringify(r.overflowElements?.slice(0, 3))}` : '',
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
  process.exitCode = problem.size > 0 && !SOFT ? 1 : 0;
})();
