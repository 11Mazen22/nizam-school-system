-- Nizam -- records the official rosters do not carry.
--
-- All 17 roster pages were checked: no birth date and no gender column exists
-- on any of them (the PDF text contains zero date-like tokens and only the
-- اسم الطالب / الديانة\اللغة الثانية headers). Rather than invent either
-- value, a roster-imported student may store NULL and be surfaced as
-- "not recorded" until the school supplies it. The manual student form still
-- requires both fields -- this nullability exists for the import path.

ALTER TABLE students
  MODIFY gender        ENUM('m','f') NULL DEFAULT NULL
    COMMENT 'NULL = not recorded (official roster prints no gender)',
  MODIFY date_of_birth DATE          NULL DEFAULT NULL
    COMMENT 'NULL = not recorded (official roster prints no birth date)',
  MODIFY religion      ENUM('muslim','christian','other') NULL DEFAULT NULL
    COMMENT 'NULL = not recorded (the printed attribute column is ambiguous)';

-- Group-language slots (printed "G+F" or "G+A") carry two teachers in one
-- cell. timetables allows one teacher per slot, so the slot stores the first
-- teacher and the printed pair in note.
ALTER TABLE timetables
  ADD COLUMN note VARCHAR(190) NULL DEFAULT NULL
    COMMENT 'Source annotation, e.g. G+F group pair: خالد / إيناس'
    AFTER teacher_id;
