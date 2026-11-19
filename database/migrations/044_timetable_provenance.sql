-- Nizam -- timetable provenance, printed-clash resolution and re-import safety.
--
-- The class timetable is not just a lesson list; the school has to be able to
-- tell which cells came from the printed grid, which ones a human corrected,
-- and which printed double-bookings are still unresolved. Before this
-- migration an import could only be repeated by deleting the whole year,
-- which silently destroyed every manual correction the school had made.
--
-- This migration adds:
--   * origin / import_source / imported_at       -- where the cell came from
--   * manual_override / manual_edited_at/by      -- a human decision wins on re-import
--   * clash_resolved_* / clash_resolution_note   -- the printed clash was settled
--   * timetable_resolutions                      -- permanent resolution history
--   * timetable_import_exclusions                -- a cell a human deliberately removed
--
-- Existing rows are marked as imported: they are the printed 2026/2027 grids
-- loaded from timetables_17.json, and none of them carries a recorded manual
-- edit. The printed name of a paper clash stays in printed_teacher forever,
-- even after the clash is resolved, because it is the evidence the school
-- compared against.

ALTER TABLE timetables
  ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER printed_teacher,
  ADD COLUMN import_source VARCHAR(120) NULL AFTER origin,
  ADD COLUMN imported_at DATETIME NULL AFTER import_source,
  ADD COLUMN manual_override TINYINT(1) NOT NULL DEFAULT 0 AFTER imported_at,
  ADD COLUMN manual_edited_at DATETIME NULL AFTER manual_override,
  ADD COLUMN manual_edited_by INT UNSIGNED NULL AFTER manual_edited_at,
  ADD COLUMN clash_resolved_at DATETIME NULL AFTER manual_edited_by,
  ADD COLUMN clash_resolved_by INT UNSIGNED NULL AFTER clash_resolved_at,
  ADD COLUMN clash_resolution_note VARCHAR(255) NULL AFTER clash_resolved_by;

UPDATE timetables
   SET origin = 'imported',
       import_source = 'timetables_17.json',
       imported_at = created_at;

ALTER TABLE timetables
  ADD KEY idx_timetable_origin (academic_year_id, origin),
  ADD CONSTRAINT fk_timetable_manual_editor
    FOREIGN KEY (manual_edited_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_clash_resolver
    FOREIGN KEY (clash_resolved_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE timetable_resolutions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id    INT UNSIGNED NOT NULL,
  timetable_id        INT UNSIGNED NULL COMMENT 'Soft reference: the lesson the clash belonged to',
  class_id            INT UNSIGNED NOT NULL,
  day_of_week         TINYINT UNSIGNED NOT NULL,
  period_number       TINYINT UNSIGNED NOT NULL,
  printed_teacher     VARCHAR(190) NULL COMMENT 'Printed teacher text, kept verbatim as evidence',
  previous_teacher_id INT UNSIGNED NULL,
  previous_subject_id INT UNSIGNED NULL,
  resolved_teacher_id INT UNSIGNED NOT NULL,
  resolved_subject_id INT UNSIGNED NOT NULL,
  note                VARCHAR(255) NULL,
  resolved_by         INT UNSIGNED NULL,
  resolved_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_timetable_resolution_year (academic_year_id, resolved_at),
  KEY idx_timetable_resolution_lesson (timetable_id),
  CONSTRAINT fk_timetable_resolution_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_resolution_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_resolution_teacher
    FOREIGN KEY (resolved_teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_resolution_subject
    FOREIGN KEY (resolved_subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_resolution_user
    FOREIGN KEY (resolved_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE timetable_import_exclusions (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  day_of_week      TINYINT UNSIGNED NOT NULL,
  period_number    TINYINT UNSIGNED NOT NULL,
  reason           VARCHAR(40) NOT NULL DEFAULT 'manual_delete',
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_timetable_import_exclusion (academic_year_id, class_id, day_of_week, period_number),
  CONSTRAINT fk_timetable_exclusion_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_exclusion_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_exclusion_user
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
