# Nizam Online -- Render Deployment

Nizam's production target is Render's free tier, running this repository's
`packaging-online/Dockerfile` as a Web Service, backed by a Supabase Postgres
database. This document is the one-time setup a new deployment (or a
disaster-recovery rebuild) needs; day-to-day deploys are just "push to the
connected branch."

## 1. One-time Supabase setup (before the app is ever deployed)

The app's own runtime database role (`nizam_app`) deliberately has no
CREATE/DROP/ALTER privilege at all -- schema only ever comes from a migration
run as the `postgres` superuser, never from the running app. Do this once,
from a machine that can reach the Supabase **session-mode** pooler
(port 5432; the transaction-mode pooler on 6543 is Render's own runtime
connection and is not reliable for catalog-level DDL -- see
`database/migrations-pg/010_app_role_and_rls.sql`'s own note):

1. Run every file in `database/migrations-pg/` in order, as the `postgres`
   superuser, via `App\Services\MigrationService` (the same runner
   `database/migrate.php` uses for the offline/MySQL path -- point it at
   `database/migrations-pg` and a session-mode PDO connection instead).
2. Run every file in `database/seeds-pg/` the same way.
3. Set a real password for the `nizam_app` role (migration 010 creates it
   with a placeholder): `ALTER ROLE nizam_app WITH PASSWORD '...';` -- this
   is the `DB_PASSWORD` value Render's environment will use. Generate it
   with something like `openssl rand -hex 24`; store it in Render's
   environment, nowhere else.
4. Create two **private** Storage buckets: `uploads` (file size limit
   2097152 bytes / 2 MB, matching `UploadService::MAX_BYTES`) and `backups`
   (file size limit up to the project's own cap -- 50 MB / 52428800 on the
   free tier). Public access must stay off for both; `storage.objects` ships
   with RLS enabled and no anon/authenticated policy by default, which is
   what actually keeps them private -- confirm that's still true
   (`SELECT relrowsecurity FROM pg_class WHERE relname='objects' AND
   relnamespace='storage'::regnamespace;` should read `true`, and `SELECT
   COUNT(*) FROM pg_policy WHERE polrelid='storage.objects'::regclass;`
   should read `0`) rather than assuming it.

## 2. Render Web Service

1. Connect this GitHub repository (`https://github.com/11Mazen22/nizam-school-system`)
   to a new Render **Web Service**, environment **Docker**, Dockerfile path
   `packaging-online/Dockerfile`, no build/start command overrides needed
   (the Dockerfile's own `ENTRYPOINT`/`CMD` handle both).
2. Health check path: `/login` (reachable with no auth, returns 200 once the
   app and database connection are both up).
3. Set these environment variables on the service (Render's dashboard, not
   committed anywhere):

   | Variable | Value |
   |---|---|
   | `DB_HOST` | the pooler hostname from the Supabase dashboard, e.g. `aws-1-eu-west-1.pooler.supabase.com` |
   | `DB_PORT` | `6543` (transaction-mode pooler -- the app's own runtime traffic, high-concurrency, no DDL) |
   | `DB_DATABASE` | `postgres` |
   | `DB_USERNAME` | `nizam_app.<project-ref>` -- Supavisor requires the project ref suffixed onto every role's username through the pooler, not just `postgres`'s (found live: a plain `nizam_app` fails with "no tenant identifier provided") |
   | `DB_PASSWORD` | the password set for `nizam_app` in step 1.3 |
   | `SUPABASE_URL` | `https://<project-ref>.supabase.co` |
   | `SUPABASE_SERVICE_ROLE_KEY` | the project's `service_role` key (Supabase dashboard -> Project Settings -> API) -- server-side only, this is as sensitive as the database password |
   | `SCHEDULED_BACKUP_TOKEN` | a long random string (`openssl rand -hex 32`) -- gates `POST /backups/scheduled` (§5 below). Not required for the app itself to start; omitting it just means the scheduled-backup workflow has nothing valid to authenticate with and every call gets 401. |

   `entrypoint.sh` writes `config/config.php` from these at every container
   start, so the Setup Wizard's own database-connection screen is never
   reached in production -- the same "provisioning writes config.php"
   pattern the Windows installer uses for the offline target.

4. Deploy. Render assigns a `*.onrender.com` HTTPS subdomain automatically
   (or attach a custom domain in the service's Settings -> Custom Domains --
   Render provisions the certificate).

## 3. First run

With the database already migrated+seeded (step 1) but no school/year/admin
row yet, the Setup Wizard opens straight at the "school details" step (its
own schema step is skipped entirely for `pgsql` --
`App\Services\SetupStatusService::currentStep()`) and walks through the
remaining steps exactly as the offline installer does.

## 4. Known tradeoff: cold starts

Render's free tier spins the container down after a period with no traffic
and takes on the order of 30-60 seconds to serve the first request after
that -- accepted explicitly in favor of staying genuinely free (no monthly
credit, no 30-day trial clock). If this becomes a real problem later, an
external uptime-ping service (a free one, hitting `/login` every few
minutes) or a paid Render instance type are the two straightforward ways
out, without any application-level change.

## 5. Backups and restore

Both are Supabase-Storage-backed, not local disk (`BackupService`'s own
docblock: Render's container disk is ephemeral, wiped on every redeploy or
restart). A restore replays inside one Postgres transaction and rolls back
completely on any failure -- genuinely safer than the offline/MySQL path,
which can't do that (DDL auto-commits there). The Backup & Restore screen
in the app (Administrator only) is the same UI either way; only the storage
destination differs.

### Automated daily backup

Render's free tier has no cron/scheduled-job feature. The substitute:
`.github/workflows/scheduled-backup.yml`, a free GitHub Actions scheduled
workflow (daily at 02:00 UTC, plus a manual "Run workflow" button for an
on-demand one) that calls `POST /backups/scheduled` with a bearer token --
`App\Controllers\BackupController::scheduled()`, the one route in the app
with no browser-session auth at all, gated purely on that token
(`SCHEDULED_BACKUP_TOKEN`, timing-safe compared). Wire it up once:

1. Set `SCHEDULED_BACKUP_TOKEN` on the Render service (table above).
2. In the GitHub repo -> Settings -> Secrets and variables -> Actions, add
   `NIZAM_URL` (the deployed `https://...onrender.com` origin, or the custom
   domain, no trailing slash) and `NIZAM_SCHEDULED_BACKUP_TOKEN` (must be the
   *same value* as step 1 -- rotate both together, never just one).

Each run shows up in the Backups screen with type "Auto" (the `backups.type`
column already reserves this value) and `created_by` NULL, same as any other
backup -- downloadable and restorable the same way.
