# Phase 3 Runtime Verification — Runbook

Run this on the actual XAMPP machine. The sandbox that built Phase 3 has no PHP
or MySQL/MariaDB installed, so none of this has been executed yet — this is the
exact sequence to run yourself, with what to expect at each step.

All commands assume PowerShell, run from the project root. If `php`/`mysql`
aren't on your PATH, use XAMPP's full paths instead, e.g.
`C:\xampp\php\php.exe` and `C:\xampp\mysql\bin\mysql.exe`.

## 0. Confirm the environment

```powershell
php --version
mysql --version
```

Note the exact version strings — they go in the Phase 3 Final Status report.

## 1. Create an empty database

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS nizam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

(Omit `-p` if your root user has no password, as is typical for a fresh XAMPP install.)

## 2. Configure the connection

```powershell
Copy-Item config\config.example.php config\config.php
```

Edit `config\config.php` if your MySQL user/password/port differ from the
defaults (`root` / empty password / `3306`).

## 3. Run the migrations

```powershell
php database\migrate.php
```

**Expect:** 8 lines reading `Applying 00N_....sql ... OK`, then "All pending
migrations applied successfully." If anything says `FAILED`, stop and paste
the full output back — do not attempt to fix it by editing the migration
files without discussing it first.

## 4. Run the seeds

```powershell
php database\seed.php
```

**Expect:** 6 lines reading `Seeding 00N_....sql ... OK`, then "All seed files
ran successfully."

## 5. Run the automated verification

```powershell
php database\verify.php
```

This checks every table/column, every foreign key (with special attention to
the two composite FKs), every unique constraint and index, every seed row —
then runs 7 integrity tests that each attempt an invalid write and confirm it
gets rejected, rolling back unconditionally so nothing is left in the
database. **Expect:** a long `[PASS]` list ending in `N / N checks passed.`
If anything says `[FAIL]`, paste the full output back before going further.

## 6. Migration re-run test (Part 4)

```powershell
php database\migrate.php
```

**Expect:** `0 already applied` should now read the total count applied, `0
pending`, and "Nothing to do -- database is already up to date." No table
gets recreated, nothing errors.

## 7. Settings-preservation test (Part 4)

Simulate an administrator changing a setting, then confirm re-seeding doesn't
silently revert it:

```powershell
mysql -u root nizam -e "UPDATE settings SET value='10' WHERE setting_key='security.login_max_attempts';"
php database\seed.php
mysql -u root nizam -e "SELECT value FROM settings WHERE setting_key='security.login_max_attempts';"
```

**Expect:** the final query returns `10`, not the seed default `5` — proving
`seed.php` did not overwrite your customization. If it comes back `5`, that's
a real regression against the frozen blueprint's own stated behavior — stop
and report it rather than letting it pass.

Afterward, put it back so the database reflects the real intended default:

```powershell
mysql -u root nizam -e "UPDATE settings SET value='5' WHERE setting_key='security.login_max_attempts';"
```

## 8. Send back the full output

Copy the complete terminal output of steps 0, 3, 4, 5, 6, and 7 back into the
conversation. That output is what turns this runbook into an actual verified
result instead of a plan for one.
