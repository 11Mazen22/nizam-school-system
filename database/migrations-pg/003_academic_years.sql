-- Nizam School Management System (Postgres/Supabase)
-- Migration 003: academic_years
-- Postgres translation of database/migrations/003_academic_years.sql
-- (§O-2 single-active-year, §S-11 per-year capacity)

CREATE TABLE academic_years (
  id                         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  label                      VARCHAR(20)  NOT NULL UNIQUE,  -- e.g. 2025/2026
  start_date                 DATE         NOT NULL,
  end_date                   DATE         NOT NULL,
  is_active                  SMALLINT     NOT NULL DEFAULT 0,  -- see 002's note on SMALLINT vs BOOLEAN
  is_closed                  SMALLINT     NOT NULL DEFAULT 0,
  expected_weekly_capacity   SMALLINT     NULL,  -- §S-11, copied from academic.expected_weekly_capacity at year creation
  created_at                 TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- §O-2: exactly one active year, enforced at the database, not just the
-- application. MySQL used a virtual generated column + UNIQUE index;
-- Postgres's native idiom for "at most one row matching a condition" is a
-- PARTIAL unique index instead -- no generated column needed at all. Only
-- rows with is_active=1 are indexed, and among those, is_active (always 1
-- for an indexed row) must be unique -- so at most one such row can exist.
-- Any number of is_active=0 rows are simply never in the index.
CREATE UNIQUE INDEX uq_academic_years_one_active ON academic_years (is_active) WHERE is_active = 1;
-- No default academic year is seeded (§R, §N): the first year is created by
-- the Setup Wizard, never as fake/demo data here.
