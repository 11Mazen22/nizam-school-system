/**
 * Live-search browser regression.
 *
 * Enforced by npm test: tests/http/run.php starts the real application on a
 * throwaway database, seeds the fixtures this script expects (two named
 * students plus two complete pagination pages,
 * a 25-row backup history whose matching row is on page 2), then invokes:
 *
 *   node tests/live-search-browser.js --base=http://127.0.0.1:PORT \
 *        --user=http_admin --password=...
 *
 * It proves what a static contract cannot: typing filters the real list
 * without pressing Enter, only the results region is replaced (the document
 * is not reloaded), the query is written to the URL, a refresh restores the
 * filtered view from the server, and a no-match term produces an explicit
 * empty state instead of a blank table.
 */
const { chromium } = require('playwright');

const args = Object.fromEntries(process.argv.slice(2).map((argument) => {
  const match = argument.match(/^--([^=]+)=(.*)$/);
  return match ? [match[1], match[2]] : [];
}));

const BASE = args.base || 'http://127.0.0.1:8123';
const USER = args.user || 'http_admin';
const PASSWORD = args.password || '';
const CLASS_ID = args.class || '';
const STUDENT_A = 'Live Search Alpha';
const STUDENT_B = 'Live Search Beta';
const NEEDLE = 'needle-backup-2026-02-01.sql';
const NO_MATCH = 'zzz-no-such-record-zzz';

if (!PASSWORD) {
  console.error('FAIL: pass --password=<test account password>');
  process.exit(1);
}

let failures = 0;
const check = (condition, label, detail = '') => {
  if (condition) {
    console.log('  ok    ' + label);
    return;
  }
  failures++;
  console.log('  FAIL  ' + label + (detail ? ' -- ' + detail : ''));
};

const queryParam = (page, name) => {
  try {
    return new URL(page.url()).searchParams.get(name);
  } catch {
    return null;
  }
};

const regionText = async (page, selector) => {
  try {
    return await page.locator(selector).innerText({ timeout: 5000 });
  } catch {
    return '';
  }
};

(async () => {
  let browser;
  try {
    browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);

    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(String(error)));
    page.on('console', (message) => {
      if (message.type() === 'error') pageErrors.push('console: ' + message.text());
    });

    console.log('Live search in a real browser');
    await page.goto(`${BASE}/login`, { waitUntil: 'load' });
    await page.fill('#username', USER);
    await page.fill('#password', PASSWORD);
    await Promise.all([
      page.waitForURL(/dashboard/, { timeout: 15000 }),
      page.locator('form button[type="submit"]').first().click(),
    ]);

    // ---------------------------------------------------------- students
    await page.goto(`${BASE}/students`, { waitUntil: 'load' });
    const initialStudents = await regionText(page, '#student-results');
    check(initialStudents.includes(STUDENT_A) && initialStudents.includes(STUDENT_B), 'The student list starts with both fixture rows');

    await page.fill('#student-search', 'Pagination Student');
    await page.waitForFunction(
      ([alpha, beta]) => {
        const text = ((document.querySelector('#student-results') || {}).innerText || '');
        const url = new URL(window.location.href);
        return url.searchParams.get('q') === 'Pagination Student'
          && text.includes('Pagination Student 01')
          && !text.includes(alpha)
          && !text.includes(beta);
      },
      [STUDENT_A, STUDENT_B],
      { timeout: 10000 }
    );
    const studentPageTwo = page.locator('#student-results .pagination a[href*="page=2"]').first();
    check(await studentPageTwo.count() === 1, 'The student fixture renders a second page');
    await studentPageTwo.scrollIntoViewIfNeeded();
    const studentPageY = await page.evaluate(() => window.scrollY);
    await page.evaluate(() => { window.__nizamPaginationMarker = 'kept'; });
    await studentPageTwo.click();
    await page.waitForURL(/page=2/, { timeout: 10000 });
    await page.waitForFunction(
      () => ((document.querySelector('#student-results') || {}).innerText || '').includes('Pagination Student 40'),
      null,
      { timeout: 10000 }
    );
    check(await page.evaluate(() => window.__nizamPaginationMarker === 'kept'), 'Student pagination replaces only the results region');
    const studentPageTwoY = await page.evaluate(() => window.scrollY);
    check(Math.abs(studentPageTwoY - studentPageY) <= 2, 'Student pagination preserves the exact reading position', `${studentPageY} -> ${studentPageTwoY}`);
    check((await regionText(page, '#student-results')).includes('Pagination Student 40'), 'Student page 2 renders the requested records');

    await page.goBack({ waitUntil: 'commit' });
    await page.waitForFunction(
      () => ((document.querySelector('#student-results') || {}).innerText || '').includes('Pagination Student 01'),
      null,
      { timeout: 10000 }
    );
    check(await page.evaluate(() => window.__nizamPaginationMarker === 'kept'), 'Back navigation restores page 1 without reloading the document');

    await page.fill('#student-search', '');
    await page.waitForFunction(
      ([a, b]) => {
        const text = (document.querySelector('#student-results') || {}).innerText || '';
        return text.includes(a) && text.includes(b);
      },
      [STUDENT_A, STUDENT_B],
      { timeout: 10000 }
    );

    // A marker proves the live update replaces only the results region; a
    // full navigation would wipe it, which is exactly what Enter would do.
    await page.evaluate(() => { window.__nizamLiveSearchMarker = 'kept'; });
    await page.fill('#student-search', 'Alpha');
    await page.waitForFunction(
      ([a, b]) => {
        const text = (document.querySelector('#student-results') || {}).innerText || '';
        return text.includes(a) && !text.includes(b);
      },
      [STUDENT_A, STUDENT_B],
      { timeout: 10000 }
    );
    check(await page.evaluate(() => window.__nizamLiveSearchMarker === 'kept'), 'Typing filters students without Enter and without reloading the page');
    check(queryParam(page, 'q') === 'Alpha', 'The student query is written to the URL');
    const filteredStudents = await regionText(page, '#student-results');
    check(filteredStudents.includes(STUDENT_A) && !filteredStudents.includes(STUDENT_B), 'Only the matching student remains visible');

    await page.reload({ waitUntil: 'load' });
    const refreshedInput = await page.inputValue('#student-search');
    const refreshedStudents = await regionText(page, '#student-results');
    check(refreshedInput === 'Alpha', 'A refresh keeps the student search term in the field');
    check(refreshedStudents.includes(STUDENT_A) && !refreshedStudents.includes(STUDENT_B), 'A refresh restores the filtered student view from the server');

    await page.evaluate(() => { window.__nizamLiveSearchMarker = 'kept'; });
    await page.fill('#student-search', NO_MATCH);
    await page.waitForFunction(
      () => !!document.querySelector('#student-results .n-empty-state'),
      null,
      { timeout: 10000 }
    );
    check(await page.evaluate(() => window.__nizamLiveSearchMarker === 'kept'), 'A no-match student term still filters in place');
    check(queryParam(page, 'q') === NO_MATCH, 'The no-match student term is kept in the URL');
    const noMatchStudents = await regionText(page, '#student-results');
    check(!noMatchStudents.includes(STUDENT_A) && !noMatchStudents.includes(STUDENT_B), 'A no-match student term hides every row behind an explicit empty state');

    await page.fill('#student-search', '');
    await page.waitForFunction(
      ([a, b]) => {
        const text = (document.querySelector('#student-results') || {}).innerText || '';
        return text.includes(a) && text.includes(b);
      },
      [STUDENT_A, STUDENT_B],
      { timeout: 10000 }
    );
    check(queryParam(page, 'q') === null, 'Clearing the student term removes q from the URL');

    // ------------------------------------------------- unsaved mark sheet
    if (CLASS_ID) {
      await page.goto(`${BASE}/attendance?class_id=${CLASS_ID}`, { waitUntil: 'load' });
      const attendanceFilter = page.locator('#attendance-search');
      check(await attendanceFilter.isVisible(), 'The attendance sheet reveals its filter only when scripting is available');
      check(await attendanceFilter.getAttribute('name') === null, 'The attendance filter has no query name, so it cannot submit the unsaved sheet');
      await page.evaluate(() => { window.__nizamLiveSearchMarker = 'kept'; });
      await attendanceFilter.fill('Alpha');
      await page.waitForFunction(
        ([a, b]) => {
          const text = (document.querySelector('#attendance-table') || {}).innerText || '';
          return text.includes(a) && !text.includes(b);
        },
        [STUDENT_A, STUDENT_B],
        { timeout: 10000 }
      );
      check(await page.evaluate(() => window.__nizamLiveSearchMarker === 'kept'), 'Typing filters the unsaved attendance sheet without reloading it');
    }

    // ----------------------------------------------------------- backups
    await page.goto(`${BASE}/backups`, { waitUntil: 'load' });
    const pageOneBackups = await regionText(page, '#backup-results');
    check(!pageOneBackups.includes(NEEDLE), 'The matching backup is not on page 1 before searching (it is older history)');
    check(pageOneBackups.includes('nightly-backup-'), 'The backup history renders its fixture rows');

    await page.evaluate(() => { window.__nizamLiveSearchMarker = 'kept'; });
    await page.fill('#backup-search', 'needle');
    await page.waitForFunction(
      (needle) => ((document.querySelector('#backup-results') || {}).innerText || '').includes(needle),
      NEEDLE,
      { timeout: 10000 }
    );
    check(await page.evaluate(() => window.__nizamLiveSearchMarker === 'kept'), 'Typing filters the backup history without Enter or a reload');
    check(queryParam(page, 'q') === 'needle', 'The backup query is written to the URL');
    check((await regionText(page, '#backup-results')).includes(NEEDLE), 'The backup search reaches a row that lives on a later pagination page');

    await page.reload({ waitUntil: 'load' });
    check(await page.inputValue('#backup-search') === 'needle', 'A refresh keeps the backup search term');
    check((await regionText(page, '#backup-results')).includes(NEEDLE), 'A refresh restores the matched backup from the server');

    // The filtered history is still paginated and must carry q on every page.
    await page.fill('#backup-search', 'nightly');
    await page.waitForFunction(
      () => ((document.querySelector('#backup-results') || {}).innerText || '').includes('nightly-backup-'),
      null,
      { timeout: 10000 }
    );
    const pageTwoLink = page.locator('#backup-results .pagination a[href*="page=2"]').first();
    const hasPageTwo = await pageTwoLink.count() > 0;
    check(hasPageTwo, 'The filtered backup history still paginates');
    if (hasPageTwo) {
      const href = await pageTwoLink.getAttribute('href');
      check((href || '').includes('q=nightly'), 'Backup pagination links keep the search term');
      await pageTwoLink.click();
      await page.waitForURL(/page=2/, { timeout: 10000 });
      check(queryParam(page, 'q') === 'nightly', 'Opening page 2 keeps the search term in the URL');
      check((await regionText(page, '#backup-results')).includes('nightly-backup-'), 'Page 2 of the filtered history shows matching rows');
    }

    await page.evaluate(() => { window.__nizamLiveSearchMarker = 'kept'; });
    await page.fill('#backup-search', NO_MATCH);
    await page.waitForFunction(
      () => !!document.querySelector('#backup-results .n-empty'),
      null,
      { timeout: 10000 }
    );
    check(await page.evaluate(() => window.__nizamLiveSearchMarker === 'kept'), 'A no-match backup term filters in place');
    check(!(await regionText(page, '#backup-results')).includes(NEEDLE), 'A no-match backup term hides every row behind an explicit empty state');
    check(queryParam(page, 'q') === NO_MATCH, 'The no-match backup term is kept in the URL');

    check(pageErrors.length === 0, 'No page or console errors were raised during the live-search pass', pageErrors.join(' | '));
  } catch (error) {
    failures++;
    console.log('  FAIL  browser pass crashed -- ' + (error && error.message ? error.message : String(error)));
  } finally {
    if (browser) await browser.close();
  }

  if (failures > 0) {
    console.log(`FAIL: ${failures} live-search browser checks failed`);
    process.exit(1);
  }
  console.log('PASS: live-search browser checks');
})();
