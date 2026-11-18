# NIZAM UNIVERSAL SCHOOL PLATFORM -- RELEASE READINESS

**Document status:** honest audit, not a marketing scorecard.
**Date:** 2026-11-16
**Baseline:** current pre-deployment working tree (branch `master`)

---

## 0. Why this document was rewritten

The previous revision of this file declared eleven operational areas
"Verified Ready" and cited automated suites such as
`tests/universal-configuration-smoke.php`, `tests/module-registry-smoke.php`,
`tests/sis-family-smoke.php`, `tests/admissions-pipeline-smoke.php`,
`tests/finance-smoke.php` and `tests/operations-smoke.php`.

**None of those files exist in this repository.** `tests/` contains seventeen
files, none of them the ones cited. The table below therefore claimed test
evidence that had never been produced. That is the single most damaging kind
of documentation error a school software release can contain, because it
tells an administrator they may trust untested behaviour with children's
records.

This document now separates three things that were previously conflated:

* **Implemented and checked** -- code exists and a named command exercises it.
* **Implemented, not yet verified** -- code exists but no check covers it.
* **Not implemented** -- a table or a document exists, the capability does not.

---

## 1. Verified evidence

Every row below was executed against this working tree.

| Check | Command | Result |
|---|---|---|
| PHP syntax, all application files | `find app views public database -name '*.php' -exec php -l {} \;` | see §4 |
| Translation key parity | `php tests/redesign-checks.php` | PASS -- 1472 keys identical in `ar.php` / `en.php` |
| Icon catalogue audit | `php tests/redesign-checks.php` | PASS -- every referenced icon is defined |
| Import + pre-write validation | `php tests/import-contract.php` | PASS |
| Exam score integrity and rollback | `php tests/exam-integrity.php` | PASS |
| Authorization / session refresh | `php tests/authorization-refresh.php` | PASS |
| Academic configuration | `php tests/academic-configuration.php` | PASS |
| Academic calculation | `php tests/academic-calculation.php` | PASS |
| Operational module integrity | `php tests/module-integrity.php` | PASS |
| Scheduled endpoint authentication | `php tests/scheduled-auth.php` | PASS |
| Online deployment/RLS contract | `php tests/online-readiness.php` | PASS (159 checks) |
| HTTP request pipeline | `php tests/http/run.php` | PASS (899 assertions) |
| Dependency metadata/security | `composer validate --strict && composer audit --locked` | PASS |
| PHP syntax, all first-party PHP | recursive `php -l` over `app bin database tests views` | PASS |

The last suite is new and covers the universal academic-configuration slice
described in §2.

---

## 2. What is now genuinely implemented

### 2.1 Universal academic configuration (new)

| Capability | Status | Where |
|---|---|---|
| Educational stages CRUD | Implemented + tested | `AcademicConfigController`, `/settings/structure` |
| Grades assigned to stages | Implemented + tested | `/settings/structure/grade-stages` |
| Stage deletion guarded by grade linkage | Implemented + tested | `AcademicConfigService::deleteStage` |
| Academic terms, variable count per year | Implemented + tested | `/settings/terms` |
| Overlap / out-of-year / duplicate-number rejection | Implemented + tested | `AcademicConfigService::saveTerm` |
| Exactly one current term per year | Implemented + tested | `AcademicConfigRepository::clearActiveTerms` |
| Grading scales (percentage / letter / GPA / points) | Implemented + tested | `/settings/grading` |
| Grading bands with overlap rejection | Implemented + tested | `AcademicConfigRepository::replaceBands` |
| Exclusive default grading scale | Implemented + tested | `makeDefault` / `setDefaultScale` |
| Bilingual UI (AR RTL / EN LTR) | Implemented | `views/settings/structure.php` |

This is the difference the specification called decisive: the platform no
longer assumes one fixed grade ladder, a two-term year and a 100-point score.

### 2.2 One shared academic calculation path (new)

`app/services/AcademicCalculationService.php` is now the only place a mark
becomes a grade:

| Capability | Status | Where |
|---|---|---|
| Score to percentage, one rounding rule | Implemented + tested | `percentage()` |
| Weighted average across assessments | Implemented + tested | `weightedPercentage()` |
| Percentage to configured band, GPA and pass flag | Implemented + tested | `bandFor()` / `describePercentage()` |
| Active scale loaded from school configuration | Implemented + tested | `scale()` |
| No configured scale returns no grade, never an invented one | Implemented + tested | `scale()` returns null |
| Class ranking uses the shared formula | Implemented + tested | `ExamService::getClassRanking` |
| Score-entry sheet shows the resulting band | Implemented + tested | `ExamService::getScoreSheet` |
| Report card / transcript uses the shared report | Implemented + tested | `ExamService::getStudentReport` |
| Family and student portals use the same result | Implemented | `PortalController` |

The ranking SQL formula was deleted rather than left beside the PHP one, so
there is no second definition of an average to drift.

### 2.4 Operational modules hardened and verified (new)

`tests/module-integrity.php` exercises the invariants beneath five modules
against isolated temporary tables:

| Invariant | Module | Result |
|---|---|---|
| Partial payment sets `partial`; settlement sets `paid` | Finance | PASS |
| Overpayment refused; balance and receipts unchanged | Finance | PASS |
| Cancelled invoice refuses payment | Finance | PASS |
| Loan reduces availability; exhausted stock refuses a loan | Library | PASS |
| Returning a loan twice does not inflate stock | Library | PASS |
| Route capacity enforced; re-assignment does not double-count | Transport | PASS |
| Only a pending leave can be decided; first decision preserved | HR | PASS |
| Only an offered application converts; wrong-grade class refused | Admissions | PASS |
| Failed conversions create no student (atomicity) | Admissions | PASS |

### 2.5 Runtime blocker fixed

Forty-two call sites across eight controllers used `Flash::success()` and
`Flash::error()`, while `App\Flash` defined only `set()` and `consume()`.
Every form submission in admissions, finance, HR, library, transport,
guardians and module settings raised "Call to undefined method" and returned
a 500 instead of a confirmation. The helpers now exist and every screen
reports its result.

### 2.3 School identity

* Profile save no longer corrupts identity fields. The previous call passed
  16 positional arguments into an 18-parameter method, shifting `currency`
  into `default_locale`, `date_format` into `currency`, and so on. It now uses
  named arguments.
* School logo is uploadable and removable (`UploadService`, MIME-sniffed,
  re-encoded, stored outside `public/`), replacing a hardcoded crest that
  made the screen untrue for any school but the original one.

### 2.3 Role templates

Migration `019` / `023` created seventeen standard roles but granted
permissions to `admin` only, so every other role could sign in and reach
nothing. Migrations `020` (MySQL) and `024` (PostgreSQL) add the baseline
grant set each template implies, idempotently.

---

### 2.6 HTTP request pipeline (new)

`tests/http/run.php` is the first suite that exercises the application the
way a browser does. It builds a throwaway MySQL database from migrations and
seeds only, writes a temporary `config.php` under a temporary
`NIZAM_DATA_PATH`, boots the real front controller under PHP's built-in
server, and drives it with a cookie jar. The real school database is never
touched, and the database is dropped on exit.

It asserts 899 behaviours across the request pipeline. Besides routing, 404s,
session regeneration at login, CSRF rejection, permission denial, module-gate
redirection, PRG redirects, flash handling, the `/lang` open-redirect guard
and security headers, it drives a create -> validate -> edit chain for every
module: grades, subjects, teachers, classes, academic years, students,
admissions (create, status, convert), guardians (create, edit, link), finance
(invoice, partial payment, refused overpayment), transport (route,
assignment), library (book, borrow, exhausted stock, return, double return),
HR (leave, decision, department), exams, attendance, timetable (including the
assignment prerequisite and duplicate-slot refusal), teaching assignments,
welfare (discipline, health), users, the four settings forms and module
toggling (including the core-module refusal). Each chain asserts the success
path, that a rejected payload persists nothing, and that a zero-permission
role is denied with 403 and writes nothing. The admissions conversion is the
spine of the chain: the student that finance, transport, library, attendance,
welfare and timetable operate on is produced by the application's own HTTP
flow, not inserted behind it.

A static route-contract audit also reflects over every handler named in
`public/index.php`: the method must exist and must accept exactly `(Request)`.

This suite found six defects that no repository-level test could see:

1. `Flash::warning()` did not exist, so **every disabled module returned
   500** instead of redirecting. `success()`/`error()` were added earlier;
   `warning()` was missed. The full set of helpers now exists.
2. The `admin` role is created by seed 001, which runs *after* migrations.
   Migration 021 therefore granted nothing on a fresh install, and seeds
   re-granted only a frozen 36-permission list. **A newly installed
   administrator was denied `/admissions`, `/guardians`, `/finance`, `/hr`,
   `/transport` and `/library`.** Fixed in both halves: the seed now grants
   admin the whole catalogue, and migration 021 covers databases that already
   have an admin role.
3. `AcademicYearContext::id()` does not exist (the method is
   `activeYearId()`). Twelve call sites in finance, admissions and all three
   portals used it, so **those routes returned 500 on load**. Call sites fixed.
4. Twenty-two route handlers in `public/index.php` pointed at controller
   methods that did not exist (`storeBook`, `borrowBook`, `actOnLeave`, `link`,
   `showRoute`, `assignStudent`, `convertToStudent`, ...), or at methods that
   took positional `$id` arguments the router never passes. **The whole
   library, transport, admissions-workflow, guardian-link and HR-decision
   surface was unreachable.** Handlers and signatures aligned to the
   documented convention: controllers read route parameters from `Request`.
5. `Request::post()` declared `?string $default`, but call sites legitimately
   pass `false`, `0.0`, `30` and `1`. The resulting `TypeError` meant
   **invoice creation, payment recording, route creation, book creation and
   the guardian link all returned 500 on their primary write path.** The
   default is now `mixed` and stringified; a request field is always a string.
6. `FinanceController::recordPayment` called the guarded repository with no
   `catch`, so an overpayment showed the generic 500 page instead of the
   reason. It now maps the repository's stable codes to a flash message.

### 2.7 Admin password recovery (new)

The login screen's "Recover Admin Access" button used to lead nowhere: the
recovery route only rendered while the users table was empty and redirected to
`/login` otherwise, so on every real installation it did nothing at all.

Recovery now has two states on the same screen:

- **No users yet** — the create-first-admin form, unchanged.
- **Users exist** — a reset form that takes a one-time code plus a new password.

Codes can only be minted from the trusted server utility. A username is no
longer required: running the command without arguments opens a numbered account
chooser (or prints the recoverable accounts when invoked non-interactively):

```
php bin/recover-admin.php
```

A code is 20 characters in five groups and valid for 20 minutes. Migration 023
(PostgreSQL twin: 027) stores only a SHA-256 lookup digest plus a
`password_hash()` in `admin_recovery_tokens`; the plain code is never persisted.
This replaces the old file handoff, which could split between Program Files and
ProgramData when the CLI and packaged Apache resolved different mutable-data
paths. Password replacement, account-lock clearing, and code deletion now happen
inside one row-locked transaction. A failed update leaves the code available;
successful use makes it immediately unreplayable.

The CLI lists only active Administrator accounts and supports selection by
number/account ID, solving recovery when both password and username were
forgotten without exposing account names on a public web route. On success, the
browser carries the recovered username through the session (never a URL), shows
it in the success notice, pre-fills the sign-in field, and offers a copy button.
The packaged Windows build ships the CLI plus a **Recover Administrator Access**
Start-menu shortcut that sets the correct ProgramData path automatically.

The recovery POST also needed a setup-gate fix: `SetupGateMiddleware` exempted the
exact path `/setup/recover-admin` only, so `POST /setup/recover-admin/reset` was
redirected to `/login` before the controller ran. The exemption is now a prefix
match.

Verified end to end against a live database: issue code -> reset -> sign in with
the new password -> reuse the same code (rejected) -> restore the original
password. `tests/http/run.php` now covers the screen, the reset, single-use
rejection and the recovered sign-in.

### 2.8 Official 2026/2027 school data (new)

The reviewed official package ships inside the application in
`database/official/`, so the Windows installer and the Railway image carry
exactly the same school data:

- **17 class pathway labels** A1-A9 / B1-B8 (`pathways.json`), mapped to the
  first/second secondary grades.
- **17 reviewed student rosters** (`roster_*.csv`): 778 populated source rows and their
  enrollments in those classes.
- **17 class timetables** (`timetables_17.json`): 507 teaching slots and the
  teaching load derived from them.
- **45 assessment weekday rows** for A1-A9
  (`weekly_assessments_A1_A9.json`), including A8 Thursday as an explicit
  no-assessment row, stored with source class and source document.
- **17-subject catalogue**: Arabic, English, Mathematics, Science, History,
  Computer, Religion, Philosophy, Second Language, Financial Literacy, Sports,
  Activity, Physics, Chemistry / Business, Psychology / Second Language,
  German and Spanish.

The roster data is separated into structured, auditable fields rather than
guessing from names. All 778 gender values come from the visually reviewed
roster sections; 703 religions come from the confirmed page-default/exception
notation (including the `م-` Christian marker); and 424 second-language values
come from printed cells or an unambiguous class heading. B6/B8 and one
unlegended shorthand leave 75 religions null. Birth dates and guardian links
remain null because the source does not provide them. Version, totals, rules,
the restored B5 L17 row and all 17 source-image hashes are pinned in
`database/official/roster-data-audit.json`.

`bin/seed-official-data.php` loads all of it through the same importers the
browser flows use, verifies the resulting counts, and writes a completion
marker. It is the one command that turns a freshly migrated installation into
the real school:

- **Offline:** run it after the installer provisions the database.
- **Online:** `packaging-online/entrypoint.sh` runs it once when
  `NIZAM_SEED_OFFICIAL_DATA=1`, after migrations and the runtime-role check,
  before `bin/production-smoke.php`. The marker lives on the persistent volume,
  so later boots never re-import.

Every import step is idempotent: classes are matched by year/grade/name,
subjects by Arabic name or stable code, assessments upsert on
(year, class, weekday), already-enrolled students are enriched rather than
duplicated, and a year that already has timetable slots is left untouched.
`tests/official-seed.php` proves the exact counts (17 classes, 778 students,
778 enrollments, 17 subjects, 507 slots, 45 assessment rows) and the
second-run no-op on both MySQL and PostgreSQL.

The printed grid lists teachers by first name only, so a handful of cells book
the same name in two classes during one period. A teacher cannot be in two
rooms at once (`uq_timetable_teacher_slot`), so the later cell is reported and
skipped rather than invented -- 519 printed cells yield the same 507 stored
slots the offline database has always had.

Nothing from the reviewed package is guessed: names and grid cells come from
the reviewed CSV/JSON files, never from the broken PDF font map.

A database dump was written to `database/backups/` before the first import ran.

## 3. Not verified by an automated check

These have code and screens. The repository-level invariants for finance,
library, transport, HR and admissions are covered (§2.4), but the layers
above them are not. None of the following may be described as verified:

* Full cross-browser/device responsive and RTL rendering. Targeted Playwright
  checks and screenshots exist, but they are not a substitute for physical
  iOS/Android and the school's actual displays/printers.
* Edit paths for finance, transport, library and HR (their primary create and
  decision flows are covered). PDF/XLSX export and MySQL backup/restore are
  covered by the HTTP suite; a live PostgreSQL restore drill remains pending.
* CSV import into admissions, finance or HR. Only the generic import contract
  is tested.
* Concurrent load, performance and backup/restore against real data.

Ten suites now run in `npm test`, covering import contracts, exam integrity,
authorization refresh, academic configuration/calculation, module invariants,
scheduled authentication, redesign consistency, online deployment contracts,
and the HTTP pipeline. The gaps listed above are the remaining honest ones.

---

## 4. Known defects and limitations

1. **Assessment categories are not configurable yet.** Grading *bands* now
   drive results (see §2.2), but weighting is still one `weight` per exam
   rather than named categories (coursework, midterm, final) with their own
   weights. A school that weights by category must express it as per-exam
   weights.
2. **Terms are not yet an active context.** The year context middleware
   supplies the active academic year; nothing yet narrows attendance or
   assessment to the current term.
3. **Campuses exist as schema only.** `campuses` was created in migration 019
   with no controller, route, view or query scoping. Multi-campus must not be
   claimed.
4. **Multi-tenancy is not implemented.** There is no tenant column, no query
   scoping and no isolation test. Do not describe this build as SaaS-ready.
5. **No complete physical-device responsive verification was performed.**
   Targeted Playwright/screenshots pass, but the school's actual phones,
   displays and printers remain a go-live check.
6. **Payroll, procurement, boarding, cafeteria, visitor management and
   alumni do not exist** beyond schema or documentation.

---

## 5. Release position

Active production deployment for this single-school installation. The live
Railway/PostgreSQL promotion gate is green; restore-drill, physical-device,
SMTP (if enabled), and off-platform backup gates remain in
`DEPLOYMENT_SUMMARY.md`. It is not a multi-tenant SaaS release and must not be
represented as one.
---

## Offline and production hardening (2026-11-12)

### Verified offline behaviour

| Check | Method | Result |
|---|---|---|
| Worker registers and controls the page | `navigator.serviceWorker` + `getRegistrations()` | PASS -- 1 registration, controller active |
| New design cached offline | Inspect `nizam-v1.2.1-studio-static` | PASS -- `theme.css`, `components.css`, `app.css`, Bootstrap RTL+LTR, fonts, `offline.html`, `manifest.json` (22 entries) |
| Versioned URLs hit the precache | `fetch('/assets/css/theme.css?v=999999')` | PASS -- 200, 8133 bytes served from cache for a URL never fetched |
| Offline document styling | Computed styles on `/offline.html` | PASS -- `#4f46e5` primary, 22px card, 470px wide, no overflow, AR/EN switch |

The service worker previously precached only `app.css` and matched caches by full
URL. Because pages request `?v=<mtime>` stylesheet URLs, the precache was never
hit and a fresh offline install could open with no stylesheet. Static caching now
uses one canonical entry per path (`theme.css`, `components.css`, dashboard JS,
Chart.js and the manifest were added as well), and the cache version was bumped.

### Production hardening

| Change | Reason |
|---|---|
| `display_errors=0` unless `NIZAM_DEBUG=1`, `log_errors=1` | A school must never see PHP paths/SQL text in a page; errors still reach the log and the friendly reference page |
| `storage/.htaccess` deny-all | Defense in depth if DocumentRoot is misconfigured to the project root |
| Placeholder scheduled tokens rejected (`ScheduledTokenGuard`) | `dev-local-token-change-in-production` / `CHANGE_THIS_TO_A_RANDOM_SECRET` are published in this repo; with the default config every scheduled endpoint answered 401 after this change |
| Error pages rebuilt on a shared document | They linked only `app.css` and used Bootstrap utilities without Bootstrap; all four now load the full design system with zero inline styles |
| Manifest aligned | `theme_color #4f46e5`, `background #f5f6fb`, `lang ar`, `dir rtl` |
| `/scheduled/*` endpoints revived | `App\Request::header()` did not exist, so birthday reminders, rollover and cleanup fataled with 500 on their first line. The method now reads `HTTP_*`, `Content-Type`/`Content-Length` and Apache's `REDIRECT_HTTP_AUTHORIZATION`/`apache_request_headers()` fallbacks. All three endpoints now answer 401 with the published placeholder token. Covered by `tests/scheduled-auth.php`. |

### Offline write queue

Form submissions made while the browser reports itself offline are captured in
IndexedDB and replayed automatically when the connection returns.

| Check | Method | Result |
|---|---|---|
| Offline submit captured, navigation suppressed | Live browser: `navigator.onLine` forced false, synthetic POST to `/grades` | PASS -- 1 item queued, no CSRF token stored, banner shown |
| Automatic replay on reconnect | Live browser: back online, flush | PASS -- queue emptied, success toast, one grade row created |
| Duplicate replay suppressed | Same queue id posted again to the live server | PASS -- `204` with `X-Nizam-Replay: duplicate`, row count unchanged |
| Two tabs replay at once | Live browser: two tabs, both online, flush fired together | PASS -- one POST, one toast, one row; the second tab found the queue empty |
| Cross-tab queue visibility | Live browser: queue in tab 1, read banner in tab 2 | PASS -- both banners agree via `BroadcastChannel` |
| Pending-sends screen | Live browser: `/settings/offline` with a queued item | PASS -- Arabic RTL row with saved time and field count; Retry and Send all each cleared the row and wrote one grade; Discard removed it with no server write |
| Screen is device-local | No database table or API backs the list | By design -- rows come from this browser's IndexedDB, and automatic replay is paused while the screen is open |
| Endpoint auth | `GET /csrf-token` signed out / signed in | PASS -- 302 to `/login` / 200 JSON, 64-character token |
| Suite coverage | `tests/http/run.php` | PASS -- 277 assertions (6 new) |

Design notes:

- Only same-origin `POST` forms are queued, and only when the browser already
  reports itself offline. A submit that fails mid-flight on a flaky connection
  still uses the browser's own error path; capturing those would mean rewriting
  every form to `fetch`.
- Sign-in, sign-out, setup and delete forms, any form with a password or file
  field, and any form marked `data-offline-queue="off"` or `data-confirm` are
  never queued.
- Replays fetch a fresh CSRF token from `GET /csrf-token` immediately before
  sending. If the session expired while offline the queue keeps its items and
  asks the user to sign in instead of discarding their work.
- `X-Nizam-Queue-Id` is claimed in the front controller before routing, so a
  lost response cannot create a second record. A submission that fails
  validation also consumes its key: that is the deliberate cost of claiming
  idempotency before the handler runs.
- Tabs coordinate through the Web Locks API (with a 30-second localStorage
  lease for browsers without it), so one tab replays while the others wait;
  a `BroadcastChannel` message keeps every tab's banner and count in sync.
  Requests abort after 20 seconds so a stalled one cannot hold the lock.
- The queue holds at most 25 submissions and is pruned after 30 days.

## Ink & Saffron redesign (2026-11-13)

The interface was re-skinned end to end on a new art direction: warm paper canvas,
deep emerald ink for the shell, saffron accents, and a new type pairing —
**Alexandria** for Arabic and **Manrope** for Latin, both self-hosted and
precached so typography works offline.

| Check | Method | Result |
|---|---|---|
| Fonts applied | Computed `font-family` after load | PASS -- body `Manrope, Alexandria`, Arabic falls through to Alexandria |
| Fonts offline-ready | Files served locally, added to `STATIC_ASSETS` | PASS -- `fonts.css`, `alexandria-arabic.woff2`, `manrope-latin.woff2` |
| Arabic + English screens | Browser, `/admissions` | PASS -- both locales, all nine stage labels translated, no raw keys |
| Shell on phones | Browser, navigation drawer | PASS -- dark ink drawer, gold active state, readable labels |
| No horizontal overflow | `scrollWidth` vs viewport at 1440px | PASS -- equal |

Admissions fixes shipped with the redesign:

- The stage tiles now match the database enum exactly. The old list invented
  `review`/`offer`/`waitlisted` while omitting `decision`; three labels rendered
  as raw translation keys and one real stage was unreachable as a filter.
- The duplicated heading is gone (the shell renders its own head, and the screen
  was adding a second one); the screen now suppresses the shell head and uses a
  purpose-built hero with the total, a stat and the primary action.
- Stage tiles, hero and table are new components (`.n-hero`, `.n-stage-grid`,
  `.n-stage-tile`) rather than Bootstrap card grids.

Consistency is now enforced rather than just applied. The shared layout renders one
hero for the whole application: every module index, list, form, detail and settings
screen contributes only a subtitle (`$pageSubtitle`/`$pageSubtitleHtml`) and an
action slot (`$pageActions`), and the layout draws the eyebrow, title and actions.
The 21 bespoke `.n-grades-header` module heads and the 41 remaining `.n-page-head`
heads were migrated, so no view builds a page header of its own.

Three screens keep a purpose-built identity header and suppress the shared hero:
the dashboard greeting, the student profile and the teacher profile. Those headers
carry an avatar, name and status badge rather than a page title.

`tests/redesign-checks.php` (now part of `npm test`) fails when:

- a view renders its own `<h1>` without suppressing the shared hero;
- a view still contains `n-page-head` or `n-grades-header` markup;
- the layout hero disappears from `views/layout/start.php`;
- any of the 29 navigation links has no matching `GET` route.

The HTTP suite loads 48 module, form and settings screens and asserts, for each,
exactly one page title, the shared hero, and no legacy header markup; it also
asserts `/activity-log` renders the shared hero.

Not verified visually: the fixed desktop sidebar at ≥992px. The browser panel used
for these checks renders at ~511 CSS px, so only the drawer could be inspected;
contrast for the desktop rail was confirmed through computed styles.

### Still not verified

- A real network disconnect (DevTools offline / airplane mode) with navigation
  replay; only cache contents and cache-hit behaviour were asserted.
- Service-worker behaviour on iOS Safari and on shared school machines.
- Offline **writes** cover same-origin POST forms captured while the browser
  reports itself offline. File uploads are not queued, and a submit that fails
  mid-flight on a flaky (not offline) connection is not captured.
- Real airplane-mode replay on a physical device, iOS Safari evicting IndexedDB
  under storage pressure, and two tabs replaying the same queue were not
  exercised; the browser check forced `navigator.onLine` rather than cutting
  the network.
- PostgreSQL backup/restore against a real production-shaped archive and any
  load or concurrency testing. MySQL backup/restore and PDF/XLSX export are
  now exercised by the HTTP suite.

## Online deployment hardening (2026-11-16)

| Control | Evidence/status |
|---|---|
| Reproducible image | PHP 8.3 Bookworm base pinned by OCI digest; `.dockerignore` excludes secrets/mutable/test artifacts |
| Runtime performance | OPcache enabled; versioned assets receive immutable cache headers; compression enabled |
| Database lifecycle | `database/migrate-pg.php`, advisory lock, idempotent seeds, fail-fast startup |
| Least privilege | Separate migration/admin and `nizam_app` runtime identities; startup verifies `current_user` |
| Postgres access policy | Migration 028 repairs grants/RLS for every module table; static coverage test checks all tables |
| Durable state | `/data` contract for config, uploads, sessions, backups, logs; Supabase Storage optional |
| Backups | live-catalog FK order, checksum, transactional PG restore, recovery-token exclusion, 30-day/minimum-7 retention |
| Proxy/TLS correctness | forwarded HTTPS secure cookies; opt-in trusted client IP; HSTS and isolation headers |
| Operations | separate `/healthz` liveness and `/readyz` DB readiness; logs to Railway stderr |
| Promotion gate | `bin/production-smoke.php` executes the live PostgreSQL dashboard/module read surface before Apache starts |
| Automation | bounded/concurrency-guarded workflows; one bearer token; SMTP TLS support |
| CI | PHP 8.2 + 8.3, MySQL 8.4, lint, Composer audit, complete `npm test` |

Live evidence captured on 2026-11-16:

- Railway app and PostgreSQL are online in production; `/healthz` and
  `/readyz` return 200.
- All 28 migrations and all 8 seeds apply idempotently; startup verifies the
  `nizam_app` identity.
- The active-year production smoke executes the dashboard and module query
  surface and discovers 49 durable backup tables before Apache starts.
- GitHub CI is green on PHP 8.2/8.3, including the production image build.
- Scheduled backup, year-rollover, and weekly-cleanup workflows returned 200;
  the backup request completed successfully on the persistent volume.

Remaining live evidence: a non-production PostgreSQL restore drill, manual
download/copy of a backup outside the Railway project, physical-device and
printer checks, and SMTP delivery if email is enabled.
