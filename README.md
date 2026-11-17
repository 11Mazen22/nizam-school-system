# Nizam School System

**Version 1.0.1** · Arabic-first, bilingual school operations platform

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
npm test
```

`npm test` runs service/repository contracts, authorization, academic and
operational invariants, scheduled-endpoint authentication, design consistency,
online deployment contracts, and the full disposable-database HTTP suite.
GitHub CI runs the same suite on PHP 8.2 and 8.3 with MySQL 8.4.

Useful focused commands:

```bash
php tests/online-readiness.php
php tests/http/run.php
composer validate --strict
composer audit --locked
```

### Official 2026/2027 package

The school's package is loaded with idempotent commands; each one can be re-run
safely and reports what it changed:

```bash
php bin/import-roster-csv.php <file.csv>     # one printed roster page
php bin/expand-roster-attributes.php --all   # the page's "printed once" rule
php bin/import-timetables.php                # the 17 printed grids
php bin/sync-timetable-gaps.php              # cells that could not load
php bin/sync-teacher-assignments.php         # teaching load from the grid
```

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
- **Admin recovery:** run `php bin/recover-admin.php` on a trusted host; the
  one-time code is hashed in the database and expires after 20 minutes.

Additional product and engineering documentation lives under `docs/`.
