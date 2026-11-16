# Nizam deployment summary

**Target:** Railway production · **Database:** Railway PostgreSQL ·
**Persistent data:** Railway volume mounted at `/data`

**Status:** Online · **URL:**
`https://nizam-school-system-production-ce930327.up.railway.app`

This file is an operational summary, not a marketing claim. The authoritative
details are in `packaging-online/README.md`; current test evidence is in
`docs/product/release-readiness.md`.

## Production components

| Component | Responsibility |
|---|---|
| `nizam-school-system` | PHP/Apache application built from `packaging-online/Dockerfile` |
| `Postgres` | primary relational store, private Railway networking |
| `nizam-data` volume | uploads, sessions, SQL backups, logs, generated config |
| GitHub Actions | CI plus scheduled backup/cleanup/notification triggers |
| SMTP provider (optional) | authenticated outbound email |
| Supabase Storage (optional) | alternative private object storage, not required |

## Implemented deployment controls

- Digest-pinned PHP 8.3 Apache image with required extensions and OPcache.
- Minimal Docker context; credentials and mutable data are excluded.
- Automatic, advisory-lock-serialized Postgres migration and seeding.
- Separate admin migration identity and least-privilege `nizam_app` runtime
  identity; startup aborts if the final identity is wrong.
- Explicit grants and RLS policy coverage for every application table.
- Persistent sessions/uploads/backups; no production data depends on the
  container layer.
- Liveness (`/healthz`) separated from DB readiness (`/readyz`).
- HTTPS-aware secure cookies behind Railway's proxy, HSTS, CSP, and standard
  browser isolation headers.
- Proxy headers trusted only when explicitly enabled, preserving real client
  IPs for audit/throttling without accepting spoofed headers offline.
- SMTP over TLS/SSL with input validation; no expectation that `mail()` works
  inside a cloud container.
- Catalog-driven PostgreSQL backups (not a stale hardcoded table list),
  checksums, transactional restore, credential-table exclusion, and retention.
- CI on PHP 8.2 and 8.3 with dependency validation/audit and disposable MySQL
  HTTP tests.

## Required Railway settings

- Dockerfile: `packaging-online/Dockerfile`
- Health check: `/healthz` (120-second timeout)
- App volume: `/data`
- Always on; one replica; restart on failure
- Database and app in the same region
- Variables exactly as listed in `packaging-online/README.md`

## Required GitHub Actions secrets

- `NIZAM_URL` — deployed HTTPS origin, no trailing slash
- `NIZAM_SCHEDULED_TOKEN` — same value as Railway's
  `SCHEDULED_BACKUP_TOKEN`

## Go-live gates

- [x] Railway staged changes reviewed and committed by the project owner
- [x] App and Postgres deployments both healthy
- [x] `/healthz` returns `200 ok`
- [x] `/readyz` returns `200 ready`
- [x] First-run school/year/admin setup completed
- [ ] Administrator recovery code issued and rejected after one use
- [ ] Student/teacher photo survives a redeploy
- [ ] PDF and XLSX exports open successfully
- [ ] Manual backup downloads and validates
- [x] Scheduled backup workflow succeeds
- [ ] Restore drill completed on non-production data
- [ ] SMTP test arrives (if email is enabled)
- [ ] Custom domain and DNS/certificate verified (if used)
- [ ] At least one backup copied outside the Railway project

## Cost statement

Railway is usage-based. Compute, database, volume, egress, backups, and any
SMTP/Supabase provider can incur charges. No document in this repository should
promise a zero-cost or unlimited production deployment. Review Railway's live
plan/usage page before go-live and configure billing alerts.
