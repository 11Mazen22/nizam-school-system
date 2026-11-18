# Nizam School System

**Version 1.1.0** · Arabic-first, bilingual school operations platform

Nizam is a modular PHP school-management system for students, guardians,
teachers, attendance, examinations, timetables, admissions, finance, HR,
transport, library, welfare, reports, notifications, audit logs, and guarded
backup/restore. It supports two first-class deployment targets:

- **Online:** Railway container + Railway PostgreSQL + persistent application
  volume, with automatic HTTPS and GitHub Actions maintenance.
- **Offline/LAN:** the packaged Windows/XAMPP product with MySQL/MariaDB and
  all frontend assets available without internet access.

## Technology

- PHP 8.2+; plain MVC/service/repository architecture
- PostgreSQL 15+ online, MySQL 8 / MariaDB 10.4+ offline
- Apache, Bootstrap 5, Chart.js, mPDF, and PhpSpreadsheet
- Arabic RTL and English LTR; bundled fonts and assets (no runtime CDN)
- Role/permission authorization, CSRF, output escaping, login throttling,
  audit logs, one-time administrator recovery, and checksummed backups

## Online deployment (Railway)

The production image is defined by `packaging-online/Dockerfile`. It uses a
digest-pinned PHP/Apache base, OPcache, production PHP limits, automatic
Postgres migrations/seeds, a least-privilege `nizam_app` database role, and a
persistent `/data` mount for uploads, sessions, logs, and backups.

See [`packaging-online/README.md`](packaging-online/README.md) for the complete
architecture, variable contract, deployment and recovery runbook. Important
endpoints:

- `/healthz` — process liveness (Railway health check)
- `/readyz` — generated configuration + database readiness
- `/setup` — first-school setup; permanently locked after completion

Never commit `config/config.php` or credentials. The container generates the
runtime file from Railway variables on every start.

## Offline installation

The recommended offline artifact is the signed/packaged installer produced by
`packaging/build.ps1`. For a manual XAMPP installation:

1. Point Apache's `DocumentRoot` at this repository's `public/` directory.
2. Create an empty MySQL/MariaDB database.
3. Open `/setup` and complete environment, database, school, year, and initial
   administrator steps.

Required PHP extensions: `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`,
`fileinfo`, and `curl` when optional cloud storage is enabled.

## Development and verification

```bash
composer install
npm install
npx playwright install chromium
npm test
```

`npm test` runs service/repository contracts, authorization, academic and
operational invariants, scheduled-endpoint authentication, design consistency,
online deployment contracts, and the full disposable-database HTTP suite. The
HTTP suite also drives a real headless Chromium through the shared live-search
behaviour -- typing without Enter, URL refresh restoration, pagination and
no-match states -- so a broken filter fails the suite, not only a static
contract. Install the browser once with `npx playwright install chromium`.
GitHub CI runs the same suite on PHP 8.2 and 8.3 with MySQL 8.4.

Useful focused commands:

```bash
php tests/online-readiness.php
php tests/http/run.php
composer validate --strict
composer audit --locked
```

### PostgreSQL parity (optional)

The pgsql branches have their own disposable test. It needs a throwaway
PostgreSQL server (never a school database) and the `pdo_pgsql` extension:

```bash
# Windows embedded server used locally; any disposable PostgreSQL works
npm install --no-save @embedded-postgres/windows-x64
PG=node_modules/@embedded-postgres/windows-x64/native/bin
$PG/initdb -D /tmp/nizam-pg -U postgres -A trust --locale=C
$PG/pg_ctl -D /tmp/nizam-pg -o "-p 55432" -l /tmp/nizam-pg.log start
NIZAM_PG_PORT=55432 php tests/postgres-parity.php
$PG/pg_ctl -D /tmp/nizam-pg -m immediate stop
```

It applies `database/migrations-pg/`, runs the seeds, and verifies migrations
029/030 and 035 plus the roster-verification, teacher-alias, timetable-gap,
teaching-load, assessment-schedule and lesson-cover upserts, the student
data-quality counters, the missing-field filters and the student lookups
(generated-id code rendering, code/name search) against real PostgreSQL, then
drops its database.

**Upgrading an existing PostgreSQL deployment:** run the PostgreSQL migration
runner before serving the new code:

```bash
php database/migrate-pg.php
```

`GET /readyz` returns `schema_pending:N` (HTTP 503) while any migration is
missing, so a deployment that skipped this step is visible instead of failing
later with a 500. Migration 032 is the one that adds `assessment_schedules`,
`import_batches` and `classes.pathway_ar`; without it `/assessments` and every
class query that reads the pathway return a 500 on PostgreSQL.

### Official 2026/2027 package

The reviewed package ships inside the application in `database/official/` and
is loaded in one verified step:

```bash
php bin/seed-official-data.php
```

It creates/activates the 2026/2027 year on an empty installation, imports the
17 classes, 778 populated roster rows, the subject catalogue, 45 assessment rows and the 17
timetables (507 slots), sets the school-confirmed maximum of 50 students per
class, verifies the resulting counts, and writes
`storage/official-data-seed.json`. Re-running it on a seeded installation is a
no-op, and the individual importers are idempotent as a second guard.

The roster enrichment is source-backed and versioned: all 778 students receive
the gender established by their reviewed roster section, 778 religions follow
the printed page-default/exception notation (including the confirmed Christian
`م-` marker) plus the school's confirmed name-based classification for the 75
students whose printed cell proves no religion, and 424 second-language values
come from printed cells or an unambiguous class heading. Every birth date and
every guardian link stays blank because the supplied pages do not provide them;
nothing is ever guessed. The exact rules, totals and 17 source image checksums
are committed in `database/official/roster-data-audit.json`.

The Railway image runs the same command once when
`NIZAM_SEED_OFFICIAL_DATA=1`, so the live site carries exactly the same school
data as the offline install. `tests/official-seed.php` proves the exact counts
and the second-run no-op on both MySQL and PostgreSQL.

The individual steps remain available for a single page or a repair; each one
can be re-run safely and reports what it changed:

```bash
php bin/import-roster-csv.php <file.csv>     # one printed roster page
php bin/expand-roster-attributes.php --all   # the page's "printed once" rule
php bin/import-timetables.php                # the 17 printed grids
php bin/sync-timetable-gaps.php              # cells that could not load
php bin/sync-teacher-assignments.php         # teaching load from the grid
```

The load sync never overwrites periods the school edited on the assignment
screen: those rows are marked manual, while rows derived from the grid are
corrected when the grid changes.

### Substitute cover

`/timetable/covers` answers "who takes this lesson on this date?" without
touching the weekly grid. Pick a date and the board lists every lesson of that
weekday with its regular teacher, an approved-leave marker when HR has one,
and only the teachers who are genuinely free in that period (the same
free/busy evidence used on `/timetable/gaps`). Assigning writes one
`lesson_covers` row for that date; removing it restores the regular teacher.
The weekly timetable is never edited by a cover, and a teacher cannot cover
their own lesson, a lesson while on approved leave, or two classes in one
period. Double-booked gaps link to the same board so a collision can be
covered for a specific day while the permanent grid decision stays with the
school.

Roster pages print a repeated value once and leave the rows below blank; the
expansion writes that value to every student while keeping the printed text
verbatim and recording whether it was printed on the row or inherited.
`/students/verification` then checks a class against its printed page and signs
it off. `/timetable/gaps` lists printed cells that are not lessons yet (no
teacher printed, or a teacher already booked elsewhere in the same period), and
`/timetable/staff` attaches a printed first name to the real staff record.

## Operations

- **Backups:** pure-PHP, checksummed, dependency-ordered SQL snapshots;
  PostgreSQL restore is transactional. Online files live on the persistent
  volume unless optional Supabase Storage credentials are configured.
- **Automation:** daily backups and notices plus weekly retention/cleanup are
  triggered by `.github/workflows/`; see `AUTOMATION_GUIDE.md`.
- **Logs:** local files under the mutable data root and stderr for Railway's
  log capture. User-facing pages never expose exception/SQL details.
- **Two-step verification:** each staff member can enable TOTP from `/mfa`;
  recovery codes are single-use and hashed, and disabling requires the
  password plus a current code.
- **Readiness:** `/healthz` is the liveness probe; `/readyz` also checks the
database and reports `schema_pending:N` when migrations are missing. Run
`php database/migrate.php` (MySQL) or `php database/migrate-pg.php`
(PostgreSQL) before serving new code.
- **Admin recovery:** run `php bin/recover-admin.php` on a trusted host; the
  one-time code is hashed in the database and expires after 20 minutes.

Additional product and engineering documentation lives under `docs/`.
