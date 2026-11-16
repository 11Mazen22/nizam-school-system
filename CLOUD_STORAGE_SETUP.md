# Nizam persistent storage

This document describes the current online storage contract. It intentionally
contains **no project IDs, passwords, API keys, or token examples**.

## Default Railway architecture

The recommended deployment keeps all production resources in one Railway
project:

| Data | Location | Persistence |
|---|---|---|
| Relational records | Railway PostgreSQL | Database service volume |
| Photos and school logo | `/data/storage/uploads` | App service volume |
| SQL backups | `/data/database/backups` | App service volume |
| PHP sessions | `/data/storage/sessions` | App service volume |
| Application logs | `/data/storage/logs` + stderr | Volume + Railway logs |
| Generated DB config | `/data/config/config.php` | Recreated each start |

Railway mounts the application volume at `/data`, and the service variable
`NIZAM_DATA_PATH=/data` makes every mutable path resolve there. Application
source remains immutable inside the image.

The runtime database connection uses the migration-created `nizam_app` role.
Administrator credentials are available only during startup migration and are
never written to the final runtime config.

## Backup behavior

- PostgreSQL snapshots contain every durable `public` application table,
  discovered from the live catalog and ordered from the actual foreign-key
  graph. Migration bookkeeping and short-lived recovery credentials are
  intentionally excluded.
- A SHA-256 footer protects integrity.
- Restore validates signature/schema compatibility, takes an emergency
  pre-restore snapshot, and replays inside one PostgreSQL transaction.
- Weekly retention removes successful snapshots older than 30 days while
  always preserving at least the newest seven.

The database service's own Railway backup/restore feature and Nizam's portable
application snapshots serve different purposes; production should keep both.

## Optional Supabase Storage

Nizam still supports private Supabase Storage for deployments that already use
it. Set both variables on the app service:

```text
SUPABASE_URL=https://<project-ref>.supabase.co
SUPABASE_SERVICE_ROLE_KEY=<server-side secret>
```

When—and only when—both are present, `UploadService` and `BackupService` use
private buckets named `uploads` and `backups`. Without them, storage uses the
persistent data root regardless of whether the database driver is PostgreSQL
or MySQL. This keeps database choice and object-storage choice independent.

Required bucket policy:

- `uploads`: private, 2 MiB object limit, JPEG/PNG
- `backups`: private, at least 55 MiB object limit
- no browser/anonymous policies; only the server-side service role accesses
  objects through Nizam's authenticated controllers

Never expose the service-role key to JavaScript, HTML, logs, screenshots, or
version control.

## Security incident note

An older tracked revision of this repository contained a credential-like
database value in a documentation example. The current tree is sanitized, but
Git history is not a secret store. Any database that ever used that value must
have its password rotated; deleting the text from the current file alone does
not revoke it or remove it from historical commits.

## Verification

After deployment:

1. `GET /healthz` returns `200 ok`.
2. `GET /readyz` returns `200 ready`.
3. Upload a test logo/photo and confirm it remains after redeploy.
4. Create and download a manual backup.
5. Verify Railway shows the application volume mounted at `/data`.
6. Run the scheduled-backup workflow once and confirm an `auto` backup row.
