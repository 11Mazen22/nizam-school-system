# Nizam Online -- Deployment Guide

Nizam's online deployment runs this repository's `packaging-online/Dockerfile`
as a container, backed by a Supabase Postgres database. The Dockerfile and
`entrypoint.sh` are platform-agnostic -- they were built and tested against
**Railway** (the current live deployment) and also work unmodified on Render
or any other host that can run an arbitrary Dockerfile and inject environment
variables. This document is the one-time setup a new deployment (or a
disaster-recovery rebuild) needs; day-to-day deploys are just "push to the
connected branch."

## 1. One-time Supabase setup (before the app is ever deployed)

The app's own runtime database role (`nizam_app`) deliberately has no
CREATE/DROP/ALTER privilege at all -- schema only ever comes from a migration
run as the `postgres` superuser, never from the running app. Do this once,
from a machine that can reach the Supabase **session-mode** pooler
(port 5432; the transaction-mode pooler on 6543 is the app's own runtime
connection and is not reliable for catalog-level DDL -- see
`database/migrations-pg/010_app_role_and_rls.sql`'s own note):

1. Run every file in `database/migrations-pg/` in order, as the `postgres`
   superuser, via `App\Services\MigrationService` (the same runner
   `database/migrate.php` uses for the offline/MySQL path -- point it at
   `database/migrations-pg` and a session-mode PDO connection instead).
2. Run every file in `database/seeds-pg/` the same way.
3. Set a real password for the `nizam_app` role (migration 010 creates it
   with a placeholder): `ALTER ROLE nizam_app WITH PASSWORD '...';` -- this
   is the `DB_PASSWORD` value the host's environment will use. Generate it
   with something like `openssl rand -hex 24`; store it in the host's
   environment variables, nowhere else.
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

## 2. Railway deployment (current, tested)

Railway's own auto-detection (Railpack) does not know this is a
Docker-based PHP app and will try to build it with a generic PHP buildpack
instead, which fails outright (missing `ext-gd`, wrong PHP config). Point it
at the Dockerfile explicitly:

1. Create a project, add a service from the GitHub repository
   (`https://github.com/11Mazen22/nizam-school-system`), branch `master`.
2. Set a **service variable** named `RAILWAY_DOCKERFILE_PATH` to
   `packaging-online/Dockerfile`. This is the actual switch that makes
   Railway use the Dockerfile instead of Railpack -- the service's own
   "Dockerfile path" setting in its build config is a *different* field and
   setting it alone was found live to NOT change the builder.
3. Set the health check path to `/healthz`, **not** `/login` or any other
   application route. `/healthz` is answered before `bootstrap.php`, config,
   or any DB/session code loads -- it only proves the container is alive.
   Found live: pointing the check at `/login` left the deployment stuck in
   "deploying" forever, because `/login` correctly 302s to `/dashboard` or
   `/setup` depending on session/setup state, and Railway's checker requires
   a literal 200 and does not follow redirects.
4. Set these environment variables on the service (Railway's dashboard, not
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
   | `SCHEDULED_BACKUP_TOKEN` | a long random string (`openssl rand -hex 32`) -- gates `POST /backups/scheduled` (§6 below). Not required for the app itself to start; omitting it just means the scheduled-backup workflow has nothing valid to authenticate with and every call gets 401. |

   `entrypoint.sh` writes `config/config.php` from these at every container
   start, so the Setup Wizard's own database-connection screen is never
   reached in production -- the same "provisioning writes config.php"
   pattern the Windows installer uses for the offline target.

5. Generate a domain (Railway dashboard -> service -> Networking -> Generate
   Domain, or the `generate-domain` MCP tool). Railway assigns a
   `*.up.railway.app` HTTPS subdomain automatically, or attach a custom
   domain in the same panel.
6. Deploy. Watch the build logs for `[2/6] RUN apt-get update ...` completing
   without an oniguruma/gd error, then the deploy logs for `AH00163: ...
   resuming normal operations` (Apache started) before assuming success --
   both were real failure points found live on this exact pipeline (see
   `Dockerfile`'s own comments for the two bugs that caused them).

### Known Railway/Docker pitfalls found live (already fixed in this repo, kept here so a rebuild doesn't reintroduce them)

- **`RAILWAY_DOCKERFILE_PATH` must be a service *variable*.** The
  `update-service` API's `dockerfilePath` field looked like it should do
  this and did not -- Railway kept using Railpack regardless. The
  documented, actually-effective mechanism is the environment variable.
- **`apt-get purge --auto-remove` on the `-dev` packages after
  `docker-php-ext-install` also removes the runtime shared libraries**
  (`libpq.so.5`, `libpng16.so.16`, `libicuio.so`, `libzip.so.5`) the
  compiled extensions link against, since nothing else on the image
  depended on them once the `-dev` metapackages were purged. Every
  extension then fails to load at PHP startup with "cannot open shared
  object file." Fix: don't purge them. Costs some image size.
- **`mod_php` requires `mpm_prefork`, and a fresh build of this pipeline
  came up with more than one MPM enabled** ("AH00534: More than one MPM
  loaded", Apache refusing to start). Fixing this in the Dockerfile at
  build time did not stick -- the base image's own `mpm_event.load`/`.conf`
  reappeared by container start regardless (most likely a BuildKit
  layer-caching quirk on this pipeline). The fix lives in `entrypoint.sh`
  instead, applied fresh on every container start.
- **mpdf/mpdf needs a writable temp directory under its own package path**
  (`vendor/mpdf/mpdf/tmp/mpdf`), which `COPY .` leaves owned by root while
  Apache's worker runs as `www-data`. PDF export 500s with "Temporary files
  directory ... is not writable" until that path is also `chown`ed, not
  just `storage/`.
- **PHP sessions are file-based on local disk.** A redeploy or restart
  wipes them, forcing every logged-in user to sign in again -- expected and
  harmless (no data loss, sessions hold no persistent state), just worth
  knowing so an unexpected mass "please log in again" after a deploy isn't
  mistaken for a bug.
- **`Logger`'s file output lands on the ephemeral container disk**, invisible
  to Railway's own log capture (which only sees stdout/stderr) and lost on
  the next restart. `Logger::write()` now also calls `error_log()`, which
  Apache routes to its own error stream -- the one Railway actually
  captures -- so a real production error leaves a trace somewhere reachable.

## 3. Render deployment (alternative, not currently used)

The same Dockerfile and environment variable table apply. Two differences
from Railway:

- Render auto-detects a root-level `Dockerfile`; since this one lives at
  `packaging-online/Dockerfile`, set the service's Dockerfile Path field to
  that path directly in Render's dashboard (Render's own field for this
  works as documented, unlike Railway's).
- Render assigns the container a dynamic `$PORT` and expects the app to
  listen on it; `entrypoint.sh` already substitutes this into Apache's
  config regardless of host, so no Render-specific change is needed there.
- Health check path: still `/healthz`, for the same reason as Railway.
- Render's free tier spins the container down after a period with no
  traffic (30-60 second cold start on the next request) but never expires
  or requires a time-limited trial; Railway's free allowance is a one-time
  credit that runs out. Which tradeoff is preferable is a hosting decision,
  not an application one -- nothing else in this repo needs to change to
  move between them.

## 4. First run

With the database already migrated+seeded (step 1) but no school/year/admin
row yet, the Setup Wizard opens straight at the "school details" step (its
own schema step is skipped entirely for `pgsql` --
`App\Services\SetupStatusService::currentStep()`) and walks through the
remaining steps exactly as the offline installer does.

## 5. Backups and restore

Both are Supabase-Storage-backed, not local disk (`BackupService`'s own
docblock: the container disk is ephemeral, wiped on every redeploy or
restart). A restore replays inside one Postgres transaction and rolls back
completely on any failure -- genuinely safer than the offline/MySQL path,
which can't do that (DDL auto-commits there). The Backup & Restore screen
in the app (Administrator only) is the same UI either way; only the storage
destination differs.

### Automated daily backup

Neither Railway's nor Render's free tier has a cron/scheduled-job feature.
The substitute: `.github/workflows/scheduled-backup.yml`, a free GitHub
Actions scheduled workflow (daily at 02:00 UTC, plus a manual "Run workflow"
button for an on-demand one) that calls `POST /backups/scheduled` with a
bearer token -- `App\Controllers\BackupController::scheduled()`, the one
route in the app with no browser-session auth at all, gated purely on that
token (`SCHEDULED_BACKUP_TOKEN`, timing-safe compared). Wire it up once:

1. Set `SCHEDULED_BACKUP_TOKEN` on the deployed service (table above).
2. In the GitHub repo -> Settings -> Secrets and variables -> Actions, add
   `NIZAM_URL` (the deployed origin, no trailing slash -- e.g.
   `https://nizam-school-system-production.up.railway.app`) and
   `NIZAM_SCHEDULED_BACKUP_TOKEN` (must be the *same value* as step 1 --
   rotate both together, never just one).

Each run shows up in the Backups screen with type "Auto" (the `backups.type`
column already reserves this value) and `created_by` NULL, same as any other
backup -- downloadable and restorable the same way.
