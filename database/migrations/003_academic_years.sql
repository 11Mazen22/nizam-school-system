-- Nizam School Management System
-- Migration 003: academic_years
-- Blueprint reference: §D, §O-2 (DB-enforced single active year), §S-11 (per-year
-- expected_weekly_capacity)

CREATE TABLE academic_years (
  id                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  label                      VARCHAR(20)  NOT NULL COMMENT 'e.g. 2025/2026',
  start_date                 DATE         NOT NULL,
  end_date                   DATE         NOT NULL,
  is_active                  TINYINT(1)   NOT NULL DEFAULT 0,
  is_closed                  TINYINT(1)   NOT NULL DEFAULT 0,
  expected_weekly_capacity   SMALLINT UNSIGNED NULL COMMENT 'Per-year override, §S-11 -- copied from academic.expected_weekly_capacity at year creation',
  -- §O-2: exactly one active year, enforced at the database, not just the application.
  -- A virtual generated column that is 1 only when is_active=1, NULL otherwise; a
  -- UNIQUE index on it lets any number of inactive (NULL) years coexist -- MySQL/MariaDB
  -- unique indexes never conflict on NULL -- while a second row trying to set
  -- is_active=1 fails the unique constraint outright.
  active_flag  TINYINT(1) GENERATED ALWAYS AS (IF(is_active = 1, 1, NULL)) VIRTUAL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_academic_years_label (label),
  UNIQUE KEY uq_academic_years_active_flag (active_flag)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- No default academic year is seeded (§R, §N): the first year is created by the
-- Setup Wizard (Phase 4+), never as fake/demo data here.
