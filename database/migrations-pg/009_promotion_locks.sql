-- Nizam School Management System (Postgres/Supabase)
-- Migration 009: promotion_locks
-- Postgres translation of database/migrations/009_promotion_locks.sql
-- (§O-17: an app-level mutex per academic_year_id -- acquiring is a plain
-- INSERT, the PRIMARY KEY collision IS the "already locked" signal;
-- releasing is a DELETE. Behavior is identical in Postgres -- primary key
-- uniqueness violations work the same way.)

CREATE TABLE IF NOT EXISTS promotion_locks (
  academic_year_id  INTEGER   NOT NULL PRIMARY KEY,
  locked_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_promotion_locks_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);
