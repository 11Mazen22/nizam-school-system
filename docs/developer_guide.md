# Nizam Developer Guide

## 1. Project Structure
- `app/`: Core application logic (Controllers, Models, Services, Repositories, Middleware).
- `config/`: Environment and runtime configuration.
- `database/`: Migrations, Seeders, and the secured `backups/` directory.
- `docs/`: System blueprints, admin guides, and developer guides.
- `public/`: The web root. Contains `index.php` (front controller) and CSS/JS assets only -- nothing user-uploaded ever lives here.
- `storage/logs/`: Application error and debug logs.
- `storage/uploads/`: Student/teacher photos and the school logo (decision #11). Outside the web root entirely, with its own execution-lockout `.htaccess` (§O-18) on top of the root deny-all; every file is served back through a controller route resolved by database id, never a direct URL.
- `views/`: Vanilla PHP view templates.

## 2. Architecture & Responsibilities
Nizam uses a classic MVC architecture tailored for high performance and offline reliability:
- **Front Controller (`public/index.php`)**: Bootstraps the app, initializes error handling, and dispatches via a simple router.
- **Controllers**: Handle HTTP requests, input validation, and view assembly.
- **Services**: Contain complex, multi-entity business logic (e.g., `PromotionService`, `RestoreService`).
- **Repositories**: Encapsulate all database interaction. Controllers should never write raw SQL.
- **Views**: Strictly escaped output using the `e()` helper. No raw variables are ever echoed.

## 3. Security & RBAC
- **Authentication**: Managed via session cookies. Lockout thresholds are enforced per Username+IP and IP-wide to prevent brute forcing.
- **Authorization**: Handled via `RoleGuardMiddleware`. Routes are explicitly protected by role (`admin`, `staff`).
- **Protection**: A root `.htaccess` enforces a strict deny-all policy. Only `public/` is accessible. All file-serving routes (backups/uploads) resolve via database IDs, never by client-supplied paths.
- **CSRF**: All POST operations require a CSRF token validated via `hash_equals()`.

## 4. Database & Migrations
- **Migrations**: Forward-only. There are no `down()` migrations. The rollback strategy for Nizam is to restore a pre-migration database backup. This reduces complexity for local school deployments.
- **Safety**: Applying a pending migration (including `009_promotion_locks`) is a normal, additive, non-destructive operation like any other -- it just hasn't been decided for a given live database yet. Apply pending migrations deliberately, with the same discipline as any schema change to a database already holding real school data: back it up first.

## 5. Development Workflow & Constraints
- **Zero External Dependencies**: You must never link to an external CDN (e.g., Google Fonts, unpkg). All assets must be bundled in `public/assets/vendor`. Nizam must work 100% offline.
- **Pagination**: Any module displaying large lists (e.g., Students, Backups) must implement server-side pagination (O-25 constraint) enforcing a strict `MAX_PER_PAGE` limit on the backend, irrespective of client requests.
- **Testing**: Destructive tests must explicitly guard themselves by checking `SELECT DATABASE()` to ensure they are operating on `nizam_test`, never `nizam`.

## 6. Modifying the Project
- When adding new modules, utilize the existing UI components (tables, modals, pagination) rather than writing custom HTML.
- Always implement the standard `archive` behavior instead of `DELETE` for core entities (Students, Teachers) to preserve historical enrollment and assignment data.

## 7. Closed gaps (post-Phase 9 completion pass)
The following were identified during the Final Blueprint Reconciliation audit as blueprint-specified but never scheduled by any phase in §F/§T, and have since been built:
- **Users** (`UserController`/`UserService`, routes under `/users`, gated on `users.manage`): list, add/edit, archive/restore. A dedicated invariant (`UserService::wouldRemoveLastAdmin()`) blocks archiving or role-changing the last active Administrator, matching the same "at least one active Administrator" rule RestoreService already enforced after a restore.
- **Settings** (`SettingsController`, routes under `/settings/*`, gated on `settings.manage`): the five §O-21 screens (Profile, Localization, Academic Rules, Security, Backup), each a separate page/POST action so saving one group can never touch another's fields.
- **Activity Log** (`ActivityLogController`, `GET /activity-log`, gated on `activity_log.view`): a paginated list, scope (own actions vs. everything) resolved by role since §J defines `activity_log.view` as one code with role-dependent scope, not two codes. The Dashboard's "recent activity" widget now shares the same `ActivityLogRepository::paginate()` query instead of duplicating it.
- **Photo/logo uploads** (`UploadService`, decision #11 / §O-18): student/teacher photos and the school logo. Files live under `storage/uploads/<type>/` with random filenames, are re-encoded through GD on the way in, and are only ever served back through a controller route resolved by database id (`/students/{id}/photo`, `/teachers/{id}/photo`, `/logo`) -- never a direct URL to the stored file.
- **`schools.logo_path` vs. `school.logo_path`**: resolved in favor of the `schools.logo_path` column (migration `010_settings_logo_cleanup.sql` removes the redundant settings-table key), the same precedent `SchoolRepository::current()` already established for `name`/`name_ar` under decision #6. The school logo is now embedded directly in exported PDF report headers (`ExportService::logoDataUri()`) alongside the existing text-only school name.
- **`backup.default_folder`**: was seeded but never read by `BackupService`, which hardcoded `database/backups`. `BackupService::directory()` now reads the setting (validated for writability at save time in Settings -> Backup); moving the folder does not relocate backups already written to the old location.
