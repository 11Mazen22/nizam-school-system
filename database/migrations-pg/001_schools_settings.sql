-- Nizam School Management System (Postgres/Supabase)
-- Migration 001: schools, settings
-- Postgres translation of database/migrations/001_schools_settings.sql --
-- see that file for the original design rationale (§D, §O-1). Differences
-- from the MySQL version, all mechanical: IDENTITY instead of
-- AUTO_INCREMENT, no ENGINE/CHARACTER SET clause (Postgres is UTF-8 native,
-- single storage engine), value_type as VARCHAR+CHECK instead of ENUM
-- (Postgres has no inline enum-in-column-definition; a native CREATE TYPE
-- ENUM would work too, but CHECK is simpler to alter later and this
-- project's ENUMs are all small, stable, closed sets either way).

CREATE TABLE schools (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  name_ar     VARCHAR(150) NOT NULL,
  logo_path   VARCHAR(255) NULL,
  address     VARCHAR(255) NULL,
  phone       VARCHAR(30)  NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Hadaba Al-Ahram Language School: Hardcoded school data
-- No setup wizard - school information pre-populated for single-school deployment
-- Note: PostgreSQL IDENTITY columns don't allow manual ID insertion by default
-- Use OVERRIDING SYSTEM VALUE to force id=1
INSERT INTO schools (id, name, name_ar, logo_path, address, phone) 
OVERRIDING SYSTEM VALUE 
VALUES (1, 'Hadaba Al-Ahram Language School', 'هضبة الأهرام الثانوية', '/assets/img/hadaba-logo.png', NULL, NULL);

CREATE TABLE settings (
  id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  setting_key  VARCHAR(100) NOT NULL UNIQUE,
  value        TEXT         NULL,
  value_type   VARCHAR(10)  NOT NULL DEFAULT 'string' CHECK (value_type IN ('string','int','bool')),
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Postgres has no "ON UPDATE CURRENT_TIMESTAMP" column clause -- one shared
-- trigger function does the same job, attached per-table below and in every
-- later migration that has its own updated_at column.
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_settings_updated_at
  BEFORE UPDATE ON settings
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
