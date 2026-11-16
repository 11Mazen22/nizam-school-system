# Nizam automation runbook

Nizam's maintenance endpoints are machine-to-machine routes protected by one
high-entropy bearer token. GitHub Actions supplies the schedule; Railway runs
the app continuously.

## Schedules

| Workflow | UTC schedule | Endpoint | Purpose |
|---|---:|---|---|
| `scheduled-backup.yml` | 02:00 daily | `POST /backups/scheduled` | checksummed full application backup |
| `weekly-cleanup.yml` | 03:00 Sunday | `POST /scheduled/cleanup` | retention, logs, sessions, lockouts, old years |
| `daily-birthdays.yml` | 08:00 daily | `POST /scheduled/birthdays` | birthday email notices |
| `daily-year-reminder.yml` | 09:00 daily | `POST /scheduled/year-rollover` | 60/30/14/7-day year-end reminders |

Every workflow also supports `workflow_dispatch` for an operator-triggered
run. Concurrency groups prevent two instances of the same workflow from
overlapping. Requests have bounded connection/operation timeouts and expose a
non-200 response as a failed workflow.

## One-time secret wiring

Generate one random token of at least 32 bytes. Store the same value in:

1. Railway app service variable: `SCHEDULED_BACKUP_TOKEN`
2. GitHub Actions repository secret: `NIZAM_SCHEDULED_TOKEN`

Also set GitHub Actions repository secret `NIZAM_URL` to the deployed HTTPS
origin without a trailing slash.

Never put the token in source, documentation, issue text, screenshots, shell
history, or workflow logs. Rotate the Railway and GitHub values together; a
one-sided rotation intentionally produces `401 Unauthorized`.

Published placeholder values and empty tokens are always rejected by
`ScheduledTokenGuard`, even if accidentally configured.

## Email delivery

Cloud containers have no mail-transfer agent, so PHP `mail()` is not a
production transport. Configure an SMTP provider on Railway:

```text
SMTP_HOST=<provider host>
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=<provider username>
SMTP_PASSWORD=<provider secret>
MAIL_FROM_ADDRESS=<verified sender>
MAIL_FROM_NAME=<school name>
```

Use `SMTP_ENCRYPTION=ssl` for implicit TLS on port 465. Authenticated SMTP over
an unencrypted connection is refused. If `SMTP_HOST` is absent, email is
disabled online while non-email automation continues to work.

Administrator recipients need an active account with a valid email address.

## Retention and cleanup

The weekly job:

- deletes activity logs older than 90 days;
- deletes failed backup metadata older than 30 days;
- deletes successful backup files older than 30 days while preserving at
  least the newest seven;
- removes expired file sessions;
- clears expired account lockouts;
- closes inactive academic years older than two years only when no active
  enrollment still references them.

## Verification after deployment

1. Confirm `GET /healthz` returns `200 ok`.
2. Confirm `GET /readyz` returns `200 ready`.
3. Run **Scheduled database backup** manually in GitHub Actions.
4. Confirm the workflow is green and an `auto` row appears in Backups.
5. Download that backup and retain it outside Railway as a recovery sample.
6. Configure SMTP, add an administrator email, and test:

```bash
curl --fail-with-body -X POST \
  -H "Authorization: Bearer <token>" \
  -d "email=admin@example.org" \
  https://<deployed-origin>/scheduled/test-notification
```

7. Trigger each remaining workflow manually and inspect the JSON response.

## Failure diagnosis

| Symptom | Check |
|---|---|
| `401` | Railway/GitHub token values differ, are empty, or are placeholders |
| `503 /readyz` | database references, migration logs, Postgres service health |
| Backup `500` | `/data` volume mount, free space, DB grants, Railway deploy logs |
| Workflow timeout | app readiness and Railway HTTP/runtime logs |
| No email | SMTP variables, verified sender, admin email, Railway stderr |
| Duplicate old files | weekly cleanup result and backup directory permissions |

Do not solve incidents by disabling authentication, making storage public, or
switching the app to the Postgres administrator account.
