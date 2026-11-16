const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const match = a.match(/^--([^=]+)=(.*)$/);
  return match ? [match[1], match[2]] : [];
}));
const BASE = args.base || 'http://127.0.0.1:8000';
const USER = args.user || 'admin';
const PASSWORD = args.password || '';
const ARTIFACTS_DIR = path.resolve(args.out || path.join(__dirname, 'ui-audit-shots', 'sidebar'));

(async () => {
  if (!PASSWORD) throw new Error('Pass --password=<dev admin password>');
  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  let failures = 0;
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  console.log('Logging in...');
  await page.goto(`${BASE}/login`, { waitUntil: 'load' });
  await page.fill('#username', USER);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/);

  // 1. ARABIC DASHBOARD & SIDEBAR INSPECTION
  console.log('Testing Arabic Dashboard...');
  await page.goto(`${BASE}/lang?to=ar`, { waitUntil: 'load' });
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'load' });
  await page.waitForTimeout(400);

  // Take full dashboard screenshot
  await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'sidebar-redesign-ar.png') });
  console.log('Captured sidebar-redesign-ar.png');

  // Verify sidebar elements are present and visible
  const sidebarCheck = await page.evaluate(() => {
    const sidebar = document.getElementById('nizamSidebar');
    const brand = document.querySelector('.n-sidebar-brand-box');
    const session = document.querySelector('.n-sidebar-session-bar');
    const search = document.getElementById('sidebarNavFilter');
    const userCard = document.querySelector('.n-sidebar-user-card');
    const groups = document.querySelectorAll('.n-nav-group');
    const activeLink = document.querySelector('.nizam-sidebar .nav-link.active');

    return {
      sidebarVisible: !!sidebar && sidebar.offsetWidth > 0,
      brandVisible: !!brand && brand.offsetHeight > 0,
      sessionVisible: !!session,
      searchVisible: !!search,
      userCardVisible: !!userCard && userCard.offsetHeight > 0,
      groupCount: groups.length,
      hasActiveLink: !!activeLink,
      activeLinkText: activeLink ? activeLink.innerText.trim() : null
    };
  });
  console.log('Sidebar component check:', sidebarCheck);

  // 2. SCROLL TEST: VERIFY SIDEBAR DOES NOT SCROLL AWAY
  console.log('Testing Scroll Behavior on /dashboard (2400px+ height)...');
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'load' });
  await page.waitForTimeout(300);

  // Measure initial positions
  const beforeScroll = await page.evaluate(() => {
    const sidebar = document.getElementById('nizamSidebar');
    const main = document.querySelector('.nizam-main');
    const sRect = sidebar.getBoundingClientRect();
    return {
      sidebarTop: sRect.top,
      sidebarBottom: sRect.bottom,
      sidebarHeight: sRect.height,
      mainScrollTop: main.scrollTop,
      mainScrollHeight: main.scrollHeight,
      mainClientHeight: main.clientHeight
    };
  });

  // Scroll main container down by 700px
  await page.evaluate(() => {
    const main = document.querySelector('.nizam-main');
    main.scrollTop = 700;
  });
  await page.waitForTimeout(300);

  // Measure after scroll
  const afterScroll = await page.evaluate(() => {
    const sidebar = document.getElementById('nizamSidebar');
    const main = document.querySelector('.nizam-main');
    const sRect = sidebar.getBoundingClientRect();
    return {
      sidebarTop: sRect.top,
      sidebarBottom: sRect.bottom,
      sidebarHeight: sRect.height,
      mainScrollTop: main.scrollTop,
      windowScrollY: window.scrollY
    };
  });

  console.log('Before scroll:', beforeScroll);
  console.log('After scroll:', afterScroll);

  if (afterScroll.sidebarTop === 0 && afterScroll.mainScrollTop > 0 && afterScroll.windowScrollY === 0) {
    console.log('SUCCESS: Sidebar is perfectly pinned and visible (top=0) while main content scrolled down (' + afterScroll.mainScrollTop + 'px)!');
  } else {
    console.error('FAILURE: Sidebar moved during scroll!', { beforeScroll, afterScroll });
    failures++;
  }

  await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'sidebar-scrolled-ar.png') });
  console.log('Captured sidebar-scrolled-ar.png');

  // 3. TEST REAL-TIME FILTERING
  console.log('Testing Real-Time Navigation Filter...');
  await page.fill('#sidebarNavFilter', 'حضور');
  await page.waitForTimeout(300);

  const filterCheck = await page.evaluate(() => {
    const attendanceLink = document.querySelector('.nav-link[href="/attendance"]');
    const isAttendanceVisible = attendanceLink ? attendanceLink.closest('.nav-item').style.display !== 'none' : false;
    const studentsLink = document.querySelector('.nav-link[href="/students"]');
    const isStudentsVisible = studentsLink ? studentsLink.closest('.nav-item').style.display !== 'none' : false;
    return { isAttendanceVisible, isStudentsVisible };
  });
  console.log('Filter check for "حضور":', filterCheck);

  // Clear filter
  await page.click('#sidebarFilterClear');
  await page.waitForTimeout(200);

  // 4. TEST ACCORDION COLLAPSE/EXPAND
  console.log('Testing Accordions...');
  const firstHeader = await page.$('.n-nav-group-header');
  if (firstHeader) {
    await firstHeader.click();
    await page.waitForTimeout(200);
    const isCollapsed = await page.evaluate(() => {
      const g = document.querySelector('.n-nav-group');
      return g ? g.classList.contains('is-collapsed') : false;
    });
    console.log('Accordion collapsed successfully:', isCollapsed);
    // Expand it back
    await firstHeader.click();
  }

  // 5. TEST SIDEBAR COLLAPSE TO RAIL MODE
  console.log('Testing Rail Mode...');
  await page.click('#sidebarCollapseBtn');
  await page.waitForTimeout(300);
  const isRail = await page.evaluate(() => {
    const shell = document.getElementById('nizamShell');
    const sidebar = document.getElementById('nizamSidebar');
    return {
      shellCollapsed: shell.classList.contains('n-sidebar-collapsed'),
      sidebarWidth: sidebar.offsetWidth
    };
  });
  console.log('Rail mode check:', isRail);
  if (!isRail.shellCollapsed || isRail.sidebarWidth > 90) {
    console.error('FAILURE: Rail mode retained the expanded sidebar width');
    failures++;
  }
  await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'sidebar-rail-ar.png') });
  console.log('Captured sidebar-rail-ar.png');

  // Uncollapse
  await page.click('#sidebarToggle');
  await page.waitForTimeout(300);

  // 6. ENGLISH MODE SCREENSHOT
  console.log('Testing English Mode...');
  await page.goto(`${BASE}/lang?to=en`, { waitUntil: 'load' });
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'load' });
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'sidebar-redesign-en.png') });
  console.log('Captured sidebar-redesign-en.png');

  await browser.close();
  console.log(`ALL SIDEBAR VISUAL AND INTERACTIVE TESTS COMPLETE: ${failures} failure(s)`);
  process.exitCode = failures === 0 ? 0 : 1;
})();
