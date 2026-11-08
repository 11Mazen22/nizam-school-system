# Nizam product redesign inventory

This inventory is the implementation checklist for the Premium Modern Light redesign. It maps the real routes and templates rather than treating the shared shell as the entire product.

## Audit findings

- The application exposes 126 registered GET/POST routes through `public/index.php` and renders 58 PHP templates, including authenticated screens, setup, login, errors, and PDF output.
- The existing `public/assets/css/app.css` had grown to 3,661 lines with repeated component definitions and several generations of “powerful/advanced” page overrides. It mixed the original green theme, decorative gradients, animated blobs, card-top gradients, and duplicated Bootstrap overrides.
- Page headers were inconsistent: some screens used `n-page-head`, while others reused grade-specific `n-grades-*` classes regardless of module.
- Several views relied on inline dimensions and page-local styles, making responsive behavior and RTL maintenance harder.
- The sidebar was a flat 17-item list and did not expose Notifications, Promotion, or the two Welfare areas as clear destinations.
- The app already has a coherent inline SVG icon foundation, but several templates requested missing icon names and therefore rendered empty icons.
- The PWA service worker supports offline asset/page caching. The former client-side POST “sync queue” claimed successful synchronization despite having no durable server conflict model; it could also intercept requests on a LAN with no public internet. The redesign keeps offline reading/caching and removes unsupported synchronization claims.
- Baseline import-contract tests pass. Database-backed tests cannot run in the current environment because the configured local MySQL service is not running.

## Page-level implementation matrix

| Status | Route / area | Actual view | Existing implementation | Required redesign | Dependencies | Verification |
|---|---|---|---|---|---|---|
| [x] | Application shell | `layout/start.php`, `layout/end.php` | Flat dark sidebar, compact utility bar | Grouped light navigation, compact topbar, breadcrumb, quick actions, command palette, account menu, responsive drawer | Session permissions, academic-year context, notification API | EN/AR, desktop/tablet/mobile, keyboard, permission visibility |
| [x] | Login | `auth/login.php` | Decorative dark hero with animated blobs | Calm split editorial layout, local fonts, focused sign-in surface | CSRF, school repository | EN/AR, mobile, error/notice states |
| [x] | Setup wizard | `layout/setup-start.php`, `setup/*.php` | Centered wizard card | Refined stepper and focused setup workspace | Setup gate, environment/database checks | All six setup states, mobile, EN/AR |
| [x] | Error states | `errors/403.php`, `404.php`, `419.php`, `500.php` | Inline-styled centered messages | Shared branded error surface with clear recovery action | Error handler, history-back behavior | All status templates, no Bootstrap dependency regression |
| [x] | Dashboard | `dashboard.php` | Five cards, religion chart, activity, risk table | Administrative command center, welcome/context, concise metrics, quick actions, balanced operations and responsible review language | Real dashboard aggregates, Chart.js, activity scope | Empty/active year, zero students, role-aware actions |
| [x] | Academic years list/create | `academic-years/index.php`, `create.php`, `edit.php` | Decorative card grid and duplicated inline create flow | Status-led year timeline/directory, deliberate rollover and status actions | Year service, rollover, close safeguards | Activate/close/reopen/create/edit, EN/AR |
| [x] | Grades | `grades/index.php` | Animated card grid and hidden edit tab | Compact bilingual directory with structured add/edit panels | Grade CRUD/archive, import | Add/edit/archive/restore/import |
| [x] | Subjects | `subjects/index.php`, `form.php` | Grade-card classes reused for subjects | Readable bilingual subject directory and clear code/status hierarchy | Subject CRUD/archive, import | Add/edit/archive/restore/import |
| [x] | Classes | `classes/index.php` | Card grid with small capacity bars | Grade-aware directory with clear enrollment/capacity presentation | Active year, grades, class CRUD, import | Empty year, add/edit/archive/restore |
| [x] | Student directory | `students/index.php`, `archived.php` | Basic table and ad-hoc filter widths | High-readability directory, integrated filters/actions, responsive row cards, import entry | Paginated repository, grade/class filters, permissions | Search/filter/page/import/archive restore |
| [x] | Student profile | `students/show.php` | Three generic cards | Profile masthead, structured personal/enrollment sections, related authorized workflow links | Enrollment history, reassign, promotion undo | With/without photo/enrollment, permissions |
| [x] | Student form | `students/form.php` | Long single card | Sectioned identity/contact/enrollment form with persistent actions | Upload safeguards, grade/class script | Create/edit/re-enroll/photo states |
| [x] | Promotion select/preview | `students/promotion-pick.php`, `promotion-preview.php` | Two-select form then dense table | Guided step treatment, operation context, clearer exceptions/actions and protected confirmation | Promotion lock, transactional service, undo | Empty rows, forced graduation, class/action JS |
| [x] | Teacher directory | `teachers/index.php`, `archived.php` | Basic table | Polished staff directory with photo/code/contact hierarchy and integrated search/actions | Teacher CRUD, import | Search/create/view/edit/archive/restore |
| [x] | Teacher profile/form | `teachers/show.php`, `form.php` | Generic cards and checkbox box | Profile masthead, qualifications surface, sectioned form/upload | Subject qualifications, uploads | Photo/no photo, qualifications, validation |
| [x] | Assignments | `assignments/index.php`, `form.php`, `archived.php` | Assignment card grid | Scannable assignment directory, inline period editing, workload navigation, focused form | Active year, teacher/subject/class data | Create/update/archive/restore, qualification warning |
| [x] | Workload | `assignments/workload.php` | Three stats, chart, simple table | Balanced workload overview with target context and readable comparison | Chart.js, workload service | No year/no rows/full data, RTL chart labels |
| [x] | Attendance marking | `attendance/mark.php` | Filter card and wide toggle table | Fast daily workflow, context bar, summary, keyboard-friendly status controls, sticky save area | Existing records and attendance service | Class/date switching, bulk status, save |
| [x] | Attendance report | `attendance/report.php` | Filter row and two tables | Structured filter panel, attendance-rate visualization, calm exception review | Summary/chronic queries | Empty/filter/data states, print |
| [x] | Exams directory | `exams/index.php` | Term cards and add/edit tabs | Structured term sections, clearer grading action hierarchy, refined forms | Exam permissions/CRUD | Add/edit/delete, permission read-only state |
| [x] | Score entry | `exams/scores.php` | Wide score table | Focused class/subject context and efficient score sheet | Assignments-derived subjects, score validation | No subject/no student/full score sheet |
| [x] | Ranking | `exams/ranking.php` | Medal table | Neutral ranked performance table with clear report-card action | Weighted averages | Empty/class/full states |
| [x] | Report card PDF | `exams/report_card_pdf.php` | Green bordered document | School-blue editorial report card while preserving mPDF/RTL behavior | mPDF, embedded seal | Arabic shaping, EN/AR, page breaks |
| [x] | Discipline | `welfare/discipline.php` | Filter table and modal | Calm welfare workspace, neutral severity/type semantics, deliberate record form | Welfare permission and service | Filters/add/delete, read-only role |
| [x] | Health | `welfare/health.php` | Filter table and modal | Protected, calm health-record workspace with readable summaries | Welfare permission and service | Filters/add/delete, long text, read-only role |
| [x] | Timetable | `timetable/index.php` | Dense inline-styled grid | Purpose-built weekly grid, strong empty slots, printable layout, accessible modal alternative | Conflict detection and timetable service | View/add/delete/clear/print, tablet overflow |
| [x] | Reports center | `reports/index.php` | Six generic report cards | Categorized report workspace with concise descriptions and format metadata | Report catalog | All catalog links, EN/AR |
| [x] | Report workspace | `reports/show.php` | Inline filter row and raw table | Filter panel, export toolbar, document-style preview | Report service/export service | Each report key, PDF/Excel/print |
| [x] | Users | `users/index.php` | Directory plus add/edit tabs | Professional account directory and focused account forms; role model remains unchanged | Last-admin invariant, user service | Add/edit/archive/restore, last admin guard |
| [x] | Notifications | `notifications/index.php` | Decorative cards and category pills | Read/unread inbox with categories, contextual actions, useful empty state | Notification service/API | Category/read/read-all/follow link |
| [x] | Activity log | `activity-log/index.php` | Three-column table | Audit timeline/table hybrid with local visible-row search and scope notice | Immutable repository and role scope | Admin/self scope, pagination, keyboard search |
| [x] | Import dialog | `partials/import_modal.php` | File input in generic modal | Guided template → file → validation expectation presentation | Atomic import endpoint and templates | Supported/invalid file, each entity entry point |
| [x] | Backup & restore | `backups/index.php` | Side-by-side forms and history | Trust-focused protection center, visually isolated restore danger zone, verified backend feedback only | Backup/restore services, cloud/local storage | Create/download/restore validation/pagination |
| [x] | Settings profile | `settings/profile.php` | Generic form card | Settings center layout, identity preview, grouped form | School repository/settings | Save/validation, EN/AR |
| [x] | Settings localization | `settings/localization.php` | Single select | Clear language choice with direction preview | Settings service | Save and next-session behavior |
| [x] | Settings academic | `settings/academic.php` | Three fields | Grouped workload and ID-pattern rules | Pattern validation | Valid/invalid patterns/ranges |
| [x] | Settings security | `settings/security.php` | Three numeric inputs | Consequence-aware security groups and help text | Login/session settings | Range errors/save |
| [x] | Settings backup | `settings/backup.php` | Path/cloud notice | Storage-mode aware settings surface and direct protection-center link | Driver/storage mode | Local/cloud states/save |

## Design-system completion checklist

- [x] School-identity color tokens derived from the blue/navy seal.
- [x] Locally bundled Inter and IBM Plex Sans Arabic in 400/500/600/700 weights with licenses.
- [x] Typography, spacing, radius, shadow, focus, and motion tokens.
- [x] Buttons, fields, selects, checks, tables, badges, tabs, pagination, alerts, dialogs, toasts, empty states, filters, uploads, and skeletons.
- [x] Grouped responsive shell, command palette, quick actions, notification menu, account menu, and compact-density preference.
- [x] Logical-property RTL implementation and reduced-motion support.
- [x] Print rules and service-worker asset version update.

## Verification record

- PHP syntax lint: passes — all modified/new templates (`php -l`, 50 files on final pass; zero failures).
- JavaScript parse checks: passes — `node --check` on `app.js`, `dashboard.js`, `workload-chart.js` (page scripts unchanged throughout).
- Translation parity check: passes — `php tests/redesign-checks.php` reports 850 keys matched in both `lang/en.php` and `lang/ar.php`; 62 distinct `icon('...')` names all defined in the `icon()` map.
- CSS class audit: passes with WARN-only notes (documented substring artifacts such as `align-items-center` and intentional JS hooks with no styling).
- Maintenance pass: removed the remaining dead decorative markup (`n-empty-blob*`, `n-auth-blob*` blocks) from attendance, exams, activity log, notifications, and login, then deleted their now-dead CSS rules; replaced the stale `btn-outline-light` buttons (users, notifications, academic-years edit, assignments archived); normalized double-wrapped empty states (students/teachers archived, reports workspace) to the single `n-empty` pattern.
- Page passes completed: settings center (side-rail `.n-settings-layout` on all five tabs via `settings/_nav.php` + `settings/_end.php`), promotion flow (2-step `n-workflow-steps`, protected confirm footer, mobile-safe stacked preview table, `promotion.js` hooks preserved), assignments form/workload guards and palette recolor, academic-year create/edit guards, subjects form guard, and the import modal (guided `n-import-steps` with real translation keys — the previous `app.*` label keys were missing from both locales and leaked raw key text; now present and parity-maintained).
- Contract tests: import contract passes; database suites blocked until local MySQL is available.
- Browser screenshots/interactions: blocked until a configured database service is available; internal component reference can be previewed without a database.
