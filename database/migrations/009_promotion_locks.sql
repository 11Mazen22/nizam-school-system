-- Nizam School Management System
-- Migration 009: promotion_locks
-- Blueprint reference: §O-17 (Phase 6) -- "before the batch starts, the
-- service takes an app-level mutex (a single row/flag, not a DB table lock)
-- so a second admin can't start a second promotion batch against the same
-- source year concurrently."
--
-- One row per academic_year_id currently mid-promotion; acquiring the lock
-- is a plain INSERT (the PRIMARY KEY collision IS the "already locked"
-- signal -- no SELECT-then-INSERT race), releasing it is a DELETE. Not a
-- generic table-lock/advisory-lock mechanism, and not reused for anything
-- else -- this table exists for exactly the one workflow §O-17 names.

CREATE TABLE IF NOT EXISTS promotion_locks (
  academic_year_id  INT UNSIGNED NOT NULL,
  locked_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (academic_year_id),
  CONSTRAINT fk_promotion_locks_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
