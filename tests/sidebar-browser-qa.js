/**
 * Sidebar + new workflow screens, exercised in a real browser.
 *
 * Usage:
 *   node tests/sidebar-browser-qa.js --base=http://127.0.0.1:8123 \
 *        --user=qa_sidebar --password=... [--class=3]
 *
 * This is a QA pass over behaviour that unit tests cannot see: what the menu
 * does while a person types in the search box, whether the accordion animates
 * open and closed, and whether the nav keeps its scroll position when a link
 * loads a new page. It also opens the roster-verification and timetable-gap
 * screens and fails on any PHP error text or uncaught page error.
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const match = a.match(/^--([^=]+)=(.*)$/);
  return match ? [match[1], match[2]] : [];
}));
const BASE = args.base || 'http://127.0.0.1:8123';
const USER = args.user || 'qa_sidebar';
const PASSWORD = args.password || '';
const CLASS_ID = args.class || '3';
const OUT = path.resolve(args.out || path.join(__dirname, '..', 'screenshots', 'sidebar-qa'));

if (!PASSWORD) {
  throw new Error('Pass --password=<qa password>');
}
fs.mkdirSync(OUT, { recursive: true });

let failures = 0;
const check = (condition, label, detail = '') => {
  if (condition) {
    console.log('  ok    ' + label);
  } else {
    failures++;
    console.log('  FAIL  ' + label + (detail ? ' -- ' + detail : ''));
  }
};

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error)));
  page.on('console', (message) => {
    if (message.type() === 'error') pageErrors.push('console: ' + message.text());
  });

  console.log('Signing in...');
  await page.goto(`${BASE}/login`, { waitUntil: 'load' });
  await page.fill('#username', USER);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/);
  await page.goto(`${BASE}/lang?to=ar`, { waitUntil: 'load' });
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'load' });
  await page.waitForTimeout(400);

  // ---------------------------------------------------------------- filter
  console.log('Sidebar search while typing');
  const input = page.locator('#sidebarNavFilter');
  const visibleCount = () => page.evaluate(() => Array.from(document.querySelectorAll('#sidebarNavScroll .nav-item'))
    .filter((item) => item.style.display !== 'none' && item.offsetParent !== null).length);

  const totalItems = await visibleCount();
  check(totalItems > 10, 'menu renders its items', String(totalItems));

  await input.click();
  await input.pressSequentially('حضور', { delay: 40 });
  await page.waitForTimeout(250);

  const filtered = await visibleCount();
  const attendanceVisible = await page.evaluate(() => {
    const link = document.querySelector('.nav-link[href="/attendance"]');
    return !!link && link.offsetParent !== null;
  });
  const studentsVisible = await page.evaluate(() => {
    const link = document.querySelector('.nav-link[href="/students"]');
    return !!link && link.offsetParent !== null;
  });
  const clearVisible = await page.locator('#sidebarFilterClear').isVisible();

  check(filtered < totalItems, 'typing narrows the menu', `${totalItems} -> ${filtered}`);
  check(attendanceVisible, 'the matching destination stays visible');
  check(!studentsVisible, 'non-matching destinations are hidden');
  check(clearVisible, 'the clear button appears while filtering');
  await page.screenshot({ path: path.join(OUT, 'sidebar-search-typing-ar.png') });

  await page.click('#sidebarFilterClear');
  await page.waitForTimeout(200);
  check(await visibleCount() === totalItems, 'clearing restores every item');
  check(!(await page.locator('#sidebarFilterClear').isVisible()), 'clear button hides when the box is empty');

  // ------------------------------------------------------------- accordion
  console.log('Accordion open/close');
  const group = page.locator('.n-nav-group').filter({ has: page.locator('.n-nav-group-title') }).nth(1);
  const header = group.locator('.n-nav-group-header');
  const wasCollapsed = await group.evaluate((el) => el.classList.contains('is-collapsed'));
  if (wasCollapsed) {
    await header.click();
    await page.waitForTimeout(260);
  }
  await header.click();
  await page.waitForTimeout(280);
  const collapsedNow = await group.evaluate((el) => el.classList.contains('is-collapsed'));
  const listHeight = await group.locator('.n-nav-group-list').evaluate((el) => getComputedStyle(el).maxHeight || getComputedStyle(el).height);
  const ariaCollapsed = await header.getAttribute('aria-expanded');
  check(collapsedNow, 'clicking a group header collapses it');
  check(ariaCollapsed === 'false', 'aria-expanded follows the collapsed state');
  check(listHeight !== 'none', 'the group list has an animated height', listHeight);
  await page.screenshot({ path: path.join(OUT, 'sidebar-accordion-collapsed-ar.png') });

  await header.click();
  await page.waitForTimeout(280);
  check(!(await group.evaluate((el) => el.classList.contains('is-collapsed'))), 'clicking again expands it');

  // ------------------------------------------------- scroll across pages
  console.log('Sidebar scroll position across navigation');
  const scrolledTo = await page.evaluate(() => {
    const nav = document.getElementById('sidebarNavScroll');
    nav.scrollTop = 260;
    return nav.scrollTop;
  });
  check(scrolledTo > 100, 'the menu is scrollable at this viewport', String(scrolledTo));

  // Let the scroll handler persist before the page unloads.
  await page.waitForTimeout(150);
  const storedBefore = await page.evaluate(() => sessionStorage.getItem('nizam_sidebar_scroll'));
  check(Number(storedBefore) > 100, 'scroll offset is persisted to sessionStorage', String(storedBefore));

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load' }),
    page.click('.nav-link[href="/teachers"]'),
  ]);
  await page.waitForTimeout(500);
  const restored = await page.evaluate(() => document.getElementById('sidebarNavScroll').scrollTop);
  check(Math.abs(restored - Number(storedBefore)) <= 2, 'the menu returns to the same offset on the next page', `${storedBefore} -> ${restored}`);
  check(page.url().includes('/teachers'), 'the navigation itself completed');
  await page.screenshot({ path: path.join(OUT, 'sidebar-scroll-restored-ar.png') });

  // ------------------------------------------------------ workflow screens
  console.log('New workflow screens');
  const screens = [
    ['/students/verification', 'roster verification list'],
    [`/students/verification/${CLASS_ID}`, 'class verification screen'],
    ['/timetable/gaps', 'timetable gaps'],
    ['/timetable/staff', 'printed-name mapping'],
    ['/timetable/covers', 'substitute cover'],
    ['/timetable', 'timetable grid'],
    ['/assignments', 'teaching assignments'],
    ['/assignments/workload', 'teaching workload'],
  ];
  for (const [url, label] of screens) {
    const response = await page.goto(`${BASE}${url}`, { waitUntil: 'load' });
    const status = response ? response.status() : 0;
    const body = await page.content();
    check(status === 200, label + ' answers 200', String(status));
    check(!/\b(Fatal error|Warning:|Notice:|Undefined variable|Undefined array key)\b/.test(body), label + ' renders without PHP diagnostics');
    check(await page.locator('h1').count() === 1, label + ' has exactly one page title');
  }

  await page.goto(`${BASE}/students/verification/${CLASS_ID}`, { waitUntil: 'load' });
  const rows = await page.locator('input[name^="name["]').count();
  check(rows > 40, 'class roster lists its students', String(rows));
  const pageImage = await page.locator('img[src$="/page"]').count();
  check(pageImage === 1, 'the printed roster page is displayed next to the list');
  await page.screenshot({ path: path.join(OUT, 'roster-verification-ar.png'), fullPage: true });

  await page.goto(`${BASE}/timetable/gaps`, { waitUntil: 'load' });
  check(await page.locator('form[action$="/resolve"]').count() >= 10, 'open gaps offer a resolve form');
  const gapsText = await page.locator('main').innerText();
  check(gapsText.includes('يحملها الآن'), 'a collision names the class that already holds the teacher');
  const teacherGroups = await page.locator('select[name="teacher_id"] optgroup').evaluateAll(
    (groups) => groups.map((group) => group.label)
  );
  check(teacherGroups.includes('متاحون في هذا الوقت'), 'the resolve list marks teachers free at that period');
  check(teacherGroups.includes('مشغولون في هذا الوقت'), 'the resolve list marks teachers busy at that period');
  await page.screenshot({ path: path.join(OUT, 'timetable-gaps-ar.png'), fullPage: true });

  check(pageErrors.length === 0, 'no page or console errors', pageErrors.slice(0, 3).join(' | '));

  await browser.close();
  console.log(`\nSidebar QA complete: ${failures} failure(s). Screenshots: ${OUT}`);
  process.exitCode = failures === 0 ? 0 : 1;
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
