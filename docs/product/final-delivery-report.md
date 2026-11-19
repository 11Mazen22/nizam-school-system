# NIZAM — final delivery report

**Date:** 2026-11-19
**Repository:** `I:\Nizam-School-System` (working tree, not a release tag)
**Method:** every claim below was re-executed in this session. No prior report was trusted without checking it again.

---

## 1. What the live database actually contains

`bin/production-status.php` and direct read-only queries show:

| Fact | Value |
|---|---|
| Academic years | 1 (`2026/2027`) |
| Classes / students / subjects / teachers | 29 / 1318 / 18 / 38 |
| Printed timetable lessons | 557 |
| Lessons with no `teacher_id` | 48 (38 activity cells, 10 preserved printed clashes) |
| Real teacher double-bookings | 0 (`uq_timetable_teacher_slot` holds) |
| Assessment schedules | 45 |
| Exam scores | 0 |
| Authorized signatories configured | 0 (two empty role rows, by design) |
| Lesson covers | 0 |
| `timetable_resolutions` / import exclusions | 0 / 0 |
| Attendance records | 0 |

No school data was invented. Every test row used for signatories, scores,
covers, promotion pairs or invoices was created inside a transaction and rolled
back. The one durable data change is a legitimate missing teaching assignment
that the existing timetable-derived sync created (`teacher_assignments` 126 →
127); it corresponds to a real printed B7 lesson, not a fixture.

---

## 2. Delivered changes

### 2.1 Authorized signatures (P0)
- Migration `043_document_signatories.sql` (MySQL + PostgreSQL), empty
  `principal` / `student_affairs` rows with default titles and placements.
- `DocumentSignatoryRepository`, `SignatoryService`, settings screen
  `/settings/signatures`, live preview JS and audit logging.
- `DocumentAssets` embeds the two distinct Arabic typefaces (Aref Ruqaa for the
  principal, Amiri for student affairs) and signature/stamp images as data
  URIs, declared once per document head, not once per page.
- `footer.php` renders each configured signatory on every applicable page;
  `RankingDocument`, report cards and every other registered type use the same
  path. Verified with transactional fixture names in a real ranking PDF/HTML:
  both names and both fonts appeared, then the rows rolled back to empty.

### 2.2 Full-roster ranking
- `ExamRepository::rosterForClass()` + rebuilt `ExamService::getClassRanking()`:
  the roster is the source of rows; marked scores are merged in; unmarked
  students are a separate section with `—` and `لم تُرصد درجات بعد`, never 0.
- `views/exams/ranking.php` and `RankingDocument` share the scored / unscored
  sections and competition ranks (1, 2, 2, 4).
- Live check: a 49-student class produced 49 rows — 0 scored, 49 unscored.
  Fixture check: 1 real mark produced 1 scored and 45 unscored rows.

### 2.3 Timetable provenance, clash resolution and re-import safety
- Migration `044_timetable_provenance.sql` (MySQL + PostgreSQL): `origin`,
  `import_source`, `imported_at`, `manual_override`, editor fields, clash
  resolution fields, `timetable_resolutions` history and
  `timetable_import_exclusions`. Existing rows were backfilled as imported;
  `printed_teacher` evidence is preserved forever.
- `TimetableRepository` / `TimetableService`: manual adds, edits, deletes and
  clash resolutions are marked as human decisions and audited; printed evidence
  survives resolution.
- `/timetable/clashes` (`حل التعارض`): availability-validated candidates,
  server-side re-check in the same transaction, history table, reactive UI.
  Live board: 10 open printed clashes; a transactional resolve set the real
  teacher, kept the printed name and wrote history; rollback restored the row.
- `bin/import-timetables.php`: `--dry-run` comparison preview, `--sync`
  upsert/idempotent re-run, manual corrections and manual deletions preserved,
  `--replace` removes only imported cells. Dry run on real data: 557 already
  correct, 0 inserts, 0 updates. Fixture checks confirmed a manual correction
  and a manual deletion both survive a re-import, then were restored exactly.
- Clash metadata and legend are shared by `TimetableBlocks` for class, teacher
  and master grids; the grid partial marks clash cells and the XLSX builder
  writes an explicit machine-readable clash table (day, period, subject,
  printed teacher, status). Master export on real data: 29 grids, 10 clashes.

### 2.4 Assessment entry / import
- Exam configuration and manual score entry already existed; added
  `ExamScoreImportService`: a real class roster-filled `.xlsx` template,
  parse → validate → preview → explicit confirm → single transaction, with
  per-row reasons, duplicate detection, range checks and activity-log audit.
  Blank score cells are skipped, never written as NULL over an existing mark.
- Migration `045_exam_score_provenance.sql` records `source` / `import_batch`
  on `exam_scores` so imported marks are distinguishable from manual ones.
- Routes `/exams/{id}/scores/import{,/template,/preview,/commit}`. HTTP suite:
  template download, real roster row preview, staged token, zero writes before
  confirm, exactly one imported mark with `source=import` and one audit row.

### 2.5 Bulk ZIP scopes and archive verification
- Real scopes added for `ranking` (classes with students), `timetable-gaps`
  (classes with gaps), `timetable-covers` (dates with covers) and `promotion`
  (source→target year pairs). Empty groups are filtered at the source, so the
  ZIP action is hidden when there is no meaningful scope.
- Fixed a latent fatal: `TeacherTimetableDocument` called
  `workloadReportForYear()` with one argument instead of two.
- Archives keep Arabic record names, use numeric prefixes for uniqueness and
  carry a README. `tests/bulk-scopes.php`: 24 checks pass; real ranking/gap
  archives verified, cover and promotion scopes verified with rolled-back
  fixtures. On real data the cover and promotion ZIP actions are correctly
  hidden (0 covers, 1 academic year).

### 2.6 Dynamic gap-reason filters
- `TimetableGapRepository::reasonCountsByStatus()` + controller filtering:
  a reason with no rows in the selected status is removed from the dropdown,
  but remains selectable under another status so resolved history survives.
  HTTP fixture: `teacher_conflict` hidden under `open`, visible under
  `resolved`; baseline had 12 resolved and 0 open such rows.

### 2.7 Authoritative pagination
- `PdfPageCount` reads the physical PDF page tree (leaf `/Type /Page` objects,
  not the first nested `/Count`, which under-reports long documents).
- `DocumentEngine::pdfBytes()` now refuses to return a PDF whose physical page
  count differs from the composed count that produced the footer numbering;
  mismatch is logged and surfaced as a clear localized error.
- `tests/page-count.php`: 14 real document types pass, 2 skipped (no real
  invoice / second year). The first run exposed and corrected the page-tree
  counting bug before enforcement; actual composed counts were already right.

### 2.8 Artifact-level regression suite
- New `tests/document-artifacts.php`: all 17 registered types, every declared
  format plus ZIP where a bulk scope exists. 144 checks pass.
- It explicitly separates what is verified: PDF bytes/header/page count,
  OOXML structure read with PhpSpreadsheet, PNG dimensions decoded from bytes,
  ZIP entry integrity. It does **not** claim Excel/LibreOffice visual rendering
  or PDF visual inspection.

---

## 3. Test evidence (re-run on 2026-11-19)

| Command | Result |
|---|---|
| `php tests/http/run.php` | **1036 passed, 0 failed, 0 skipped** |
| `php tests/document-artifacts.php` | **144 passed, 0 failed** |
| `php tests/page-count.php` | **14 passed, 0 failed, 2 skipped** |
| `php tests/bulk-scopes.php` | **24 passed, 0 failed** |
| `php tests/online-readiness.php` | **214 passed, 0 failed** |
| `php tests/i18n-parity.php` | **5 passed, 0 failed** |
| `php tests/legacy-web-smoke.php` (temporary PHP server on 127.0.0.1:8000) | **13 passed, 0 failed** |
| `php tests/exam-integrity.php` | PASS (six invalid batches, full roster, rollback) |
| `php tests/official-seed.php` | 45 passed, 0 failed |
| `php tests/institutional-scenarios-test.php` | 41 passed, 0 failed |
| `php tests/teacher-load-sync.php` | 52 passed, 0 failed |
| `php tests/roster-religion-decode.php` | 51 passed |
| `php tests/official-roster-data.php` | 20 passed |
| `php tests/legacy-smoke.php` / `legacy-import-smoke.php` | 31 / 7 passed |
| `php tests/academic-rules-engine.php` | 21 passed |
| Other top-level suites (academic calculation/configuration, assignment &
  archive restore, authorization refresh, document-engine contract, import
  atomicity/contract, live-search contract, module integrity, notifications,
  redesign checks, scheduled auth) | all PASS |
| `php tests/postgres-parity.php` | **BLOCKED** — no disposable PostgreSQL at `127.0.0.1:55432` |

All fixture-based suites were checked again afterwards: invoice fixture 0,
fixture academic year 0, lesson covers 0, exam scores 0, signatories 0,
timetable resolutions 0, import exclusions 0.

---

## 4. Remaining limitations (honest)

1. **No live PostgreSQL verification.** The PostgreSQL migration twins,
   including RLS for the three new tables, were checked statically by
   `online-readiness.php`, but no disposable PostgreSQL server was available.
   `tests/postgres-parity.php` correctly reports BLOCKED, not PASS.
2. **No real scores or signatures to display.** Production still has 0 exam
   scores and 0 configured signatories. Ranking with marks and rendered
   signatures were demonstrated only with transactional fixtures that were
   rolled back. Nothing was seeded to make a demo look complete.
3. **No Excel/LibreOffice visual test.** XLSX files are real OOXML packages and
   their cells are read with PhpSpreadsheet; whether Microsoft Excel renders
   them beautifully has not been observed on this machine.
4. **No PDF visual inspection.** PDF bytes, headers and physical page counts are
   verified. Layout, Arabic shaping on paper and printer output were not
   eyeballed; the browser/PNG path can be used for that review.
5. **Cover and promotion ZIPs have no real scope today.** There are no lesson
   covers and only one academic year, so those bulk actions are hidden. Their
   generation was verified with rolled-back fixtures only.
6. **Open printed clashes deliberately have no teacher id**, so a teacher's own
   timetable cannot list an unresolved clash as their lesson. The shared grid
   block would badge it if present; class, master and Excel exports do show all
   10 open clashes.
7. **`--replace` clears import exclusions by design** (it means "reload the
   printed grid"), while preserving every `manual_override` cell. This is
   intentional and documented in the importer header.
8. The legacy web-smoke suite needs a running HTTP server; it was run against a
   temporary PHP built-in server and the server was stopped afterwards.

---

## 5. Bottom line

All requested P0–P5 work was implemented and exercised against the real school
database or isolated, rolled-back fixtures. The automated evidence is strong
for data integrity, authorisation, provenance, pagination and artifact
generation. The two genuinely unverified areas are live PostgreSQL behaviour
and human visual review of Excel/PDF output; neither is claimed as passing.
