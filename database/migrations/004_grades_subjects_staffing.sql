-- Nizam School Management System
-- Migration 004: grades, subjects, subject_staffing_requirements
-- Blueprint reference: §D, §I.6 (staffing shortage), item 9 of the original brief

CREATE TABLE grades (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name_en     VARCHAR(60)  NOT NULL,
  name_ar     VARCHAR(60)  NOT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- No UNIQUE on name_en/name_ar: the blueprint doesn't ask for one, and two schools'
-- worth of grade naming conventions shouldn't be blocked by a uniqueness rule §D
-- never specified. sort_order drives promotion's "next grade" default (§I.1 step 2).

CREATE TABLE subjects (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code     VARCHAR(20)  NOT NULL,
  name_en  VARCHAR(100) NOT NULL,
  name_ar  VARCHAR(100) NOT NULL,
  is_active TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subjects_code (code)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE subject_staffing_requirements (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id         INT UNSIGNED NOT NULL,
  academic_year_id   INT UNSIGNED NOT NULL,
  required_teachers  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ssr_subject_year (subject_id, academic_year_id),
  CONSTRAINT fk_ssr_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ssr_academic_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- A subject with no row here for a given year is excluded from the staffing-shortage
-- report entirely (§I.6) -- not treated as "requires zero teachers." That is
-- application-layer behavior (Phase 7); nothing to enforce at the schema level.
