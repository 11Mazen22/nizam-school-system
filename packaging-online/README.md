# Nizam online deployment runbook

## Production topology

```text
Browser --HTTPS--> Railway edge --> nizam-school-system (PHP/Apache)
                                      |-- private network --> Postgres
                                      `-- /data persistent volume

GitHub Actions --Bearer token--> scheduled endpoints
Railway logs <------------------- Apache/PHP stdout + stderr
```

The app remains a single-school monolith. That is deliberate: one service,
one database, and one persistent data root are simpler to recover and operate
than unnecessary microservices at this scale.

## Image contract

`packaging-online/Dockerfile` provides:

- digest-pinned PHP 8.3/Apache on Debian Bookworm;
- `pdo_pgsql`, GD, ZIP, Intl, mbstring, cURL, and OPcache;
- Apache rewrite/compression/cache/security modules;
- `public/` as the only document root;
- a production PHP policy (`php-production.ini`);
- no local credentials, local uploads, logs, test artifacts, or `node_modules`
  in the build context (`.dockerignore`).

`entrypoint.sh` validates variables, prepares `/data`, applies all pending
Postgres migrations and idempotent seeds under a database advisory lock,
rotates the least-privilege runtime role password, atomically replaces the
temporary admin config with a runtime config, verifies `current_user` is
`nizam_app`, and only then starts Apache.

## Railway resources

In the `production` environment:

1. App service connected to `11Mazen22/nizam-school-system`, branch `master`.
2. Railway PostgreSQL service.
3. App volume mounted at `/data` (1 GiB is a practical initial size; it can be
   grown, never shrunk).
4. App service region and database region kept together.
5. Railway-generated HTTPS domain, then an optional custom school domain.

App service settings:

| Setting | Value |
|---|---|
| Dockerfile path | `packaging-online/Dockerfile` |
| Health check | `/healthz` |
| Health timeout | 120 seconds |
| Restart policy | On failure, up to 5 retries |
| Replicas | 1 (file sessions + attached volume) |
| Sleeping | Disabled |

## Variable contract

Values below are Railway reference variables wherever possible. Do not copy
resolved database credentials into documentation or source.

| Variable | Value/purpose |
|---|---|
| `DB_ADMIN_HOST` | `${{Postgres.PGHOST}}` |
| `DB_ADMIN_PORT` | `${{Postgres.PGPORT}}` |
| `DB_ADMIN_DATABASE` | `${{Postgres.PGDATABASE}}` |
| `DB_ADMIN_USERNAME` | `${{Postgres.PGUSER}}` |
| `DB_ADMIN_PASSWORD` | `${{Postgres.PGPASSWORD}}` |
| `DB_HOST` | `${{Postgres.PGHOST}}` |
| `DB_PORT` | `${{Postgres.PGPORT}}` |
| `DB_DATABASE` | `${{Postgres.PGDATABASE}}` |
| `DB_USERNAME` | `nizam_app` |
| `DB_PASSWORD` | generated 32+ byte random secret |
| `NIZAM_DATA_PATH` | `/data` |
| `NIZAM_MIGRATE_ON_START` | `1` |
| `NIZAM_DEBUG` | `0` |
| `APP_TIMEZONE` | school's IANA zone, e.g. `Africa/Cairo` |
| `TRUST_PROXY_HEADERS` | `1` on Railway only |
| `SCHEDULED_BACKUP_TOKEN` | same generated token as GitHub `NIZAM_SCHEDULED_TOKEN` |

Optional SMTP variables:

| Variable | Example form |
|---|---|
| `SMTP_HOST` | provider host |
| `SMTP_PORT` | `587` |
| `SMTP_ENCRYPTION` | `tls` (`ssl` for implicit TLS/465) |
| `SMTP_USER` / `SMTP_PASSWORD` | provider credentials |
| `MAIL_FROM_ADDRESS` | verified sender address |
| `MAIL_FROM_NAME` | school display name |

Optional Supabase Storage variables are documented in
`CLOUD_STORAGE_SETUP.md`; they are not required for Railway-native storage.

## First run

The database schema and reference data are ready before the health check can
pass. Open the generated domain and complete `/setup`:

1. school identity and locale;
2. academic year (if not pre-seeded);
3. initial administrator.

After completion, the setup routes are locked. Test sign-in, one image upload,
PDF/XLSX export, manual backup download, and administrator recovery before
entering real student data.

## Probes and troubleshooting

- `/healthz`: liveness only; independent of DB so an outage does not create a
  destructive restart loop.
- `/readyz`: configuration + DB query; use for monitoring/incident diagnosis.
- Failed build: inspect Railway build logs first.
- Crash/restart: inspect deploy logs; startup deliberately exits on missing
  variables, migration failure, or wrong runtime DB identity.
- HTTP 5xx: search stderr by the friendly error reference shown to the user.
- Upload/backup loss after redeploy: verify the `/data` volume mount and
  `NIZAM_DATA_PATH` variable before writing more data.

## Recovery

1. Preserve the current app volume and database service; do not delete either
   while diagnosing.
2. Download the newest Nizam SQL backup if the app is reachable.
3. Use Railway's database backup/restore for infrastructure-level recovery,
   or Nizam's guarded restore for a portable application snapshot.
4. Redeploy the last known-good commit if a code release is the cause.
5. Verify `/readyz`, sign-in, exports, uploads, and a fresh backup before
   reopening access to staff.
