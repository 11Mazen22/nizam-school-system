-- Nizam -- roster verification, printed-value expansion and timetable gaps.
--
-- 1. The school's own roster pages print a repeated value once and leave the
--    cells below blank: ten Muslim students are written "مسلمة" on the first
--    row only, and a Christian student is the only row that says "مسيحية".
--    Blank therefore means "same as the row above", not "unknown". The
--    import expands that rule into an explicit value on every student, and
--    keeps whether the value was printed on that row or carried forward, so
--    the school can always see which of the two it is looking at.
--
-- 2. The expanded rosters still have to be checked against the printed page.
--    A class is signed off as verified with a fingerprint of the exact names
--    and attribute values at that moment; editing a name afterwards changes
--    the fingerprint and the class returns to "needs re-verification"
--    instead of keeping a stale tick.
--
-- 3. Timetables: a cell the source printed without a teacher (or with a
--    first name that two different teachers share) could not be loaded.
--    Those cells are stored as gaps with their printed text, so the school
--    can see them on the timetable screen and fill them in; resolving a gap
--    creates the lesson and marks the gap resolved.

ALTER TABLE students
  ADD COLUMN class_attribute_source ENUM('printed','carried_forward') NULL DEFAULT NULL
    COMMENT 'printed = value appears on this student''s row; carried_forward = the page prints it once and the rows below inherit it'
    AFTER import_source;

CREATE TABLE IF NOT EXISTS roster_verifications (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  status           ENUM('verified') NOT NULL DEFAULT 'verified',
  student_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  fingerprint      CHAR(64) NULL COMMENT 'SHA-256 of the class roster at sign-off; a later edit invalidates the tick',
  note             VARCHAR(500) NULL,
  verified_by      INT UNSIGNED NULL,
  verified_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roster_verification_class (academic_year_id, class_id),
  KEY idx_roster_verification_status (status),
  CONSTRAINT fk_roster_verification_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_roster_verification_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_roster_verification_user
    FOREIGN KEY (verified_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The printed roster writes a first name only ("مروة", "إيمان"). A staff
-- record may be known to the school by that printed name; the alias keeps
-- the timetable's own text attached to the real person without renaming the
-- teacher record or guessing which of two same-named teachers a slot meant.
CREATE TABLE IF NOT EXISTS teacher_aliases (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_id INT UNSIGNED NOT NULL,
  alias      VARCHAR(150) NOT NULL COMMENT 'Exact text printed by the timetable/roster',
  source     VARCHAR(40) NOT NULL DEFAULT 'timetable',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teacher_alias (teacher_id, alias),
  KEY idx_teacher_alias_text (alias),
  CONSTRAINT fk_teacher_alias_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timetable_gaps (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id     INT UNSIGNED NOT NULL,
  class_id             INT UNSIGNED NOT NULL,
  day_of_week          TINYINT UNSIGNED NOT NULL,
  period_number        TINYINT UNSIGNED NOT NULL,
  subject_id           INT UNSIGNED NULL,
  subject_raw          VARCHAR(120) NOT NULL COMMENT 'Printed subject text, verbatim',
  printed_teacher      VARCHAR(190) NULL COMMENT 'Printed teacher text, verbatim (may be empty or a first name)',
  reason               ENUM('no_teacher','teacher_conflict','unmapped_teacher','absence','other') NOT NULL,
  status               ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
  resolved_teacher_id  INT UNSIGNED NULL,
  resolved_subject_id  INT UNSIGNED NULL,
  resolved_timetable_id INT UNSIGNED NULL COMMENT 'Soft reference: the lesson created when the gap was resolved',
  note                 VARCHAR(255) NULL,
  source_document      VARCHAR(190) NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_timetable_gap_slot (academic_year_id, class_id, day_of_week, period_number),
  KEY idx_timetable_gap_status (status, reason),
  KEY idx_timetable_gap_teacher (printed_teacher),
  CONSTRAINT fk_timetable_gap_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_gap_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_gap_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_gap_resolved_teacher
    FOREIGN KEY (resolved_teacher_id) REFERENCES teachers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_gap_resolved_subject
    FOREIGN KEY (resolved_subject_id) REFERENCES subjects(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
