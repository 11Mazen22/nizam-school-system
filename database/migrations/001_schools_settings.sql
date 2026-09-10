-- Nizam School Management System
-- Migration 001: schools, settings
-- Blueprint reference: A-T frozen blueprint, §D (Database Design), §R (migration order)
--
-- Implementation notes (no widths/enum values were given in §D's compact table for
-- this group of standalone tables -- resolved conservatively per the Phase 3 kickoff's
-- "Case B" rule, reusing widths the blueprint DOES specify elsewhere for the same kind
-- of field: name/address/phone widths match students/teachers; see the Phase 3
-- verification report for the full list of these documented choices):
--   - value_type: the frozen settings catalog (§J) only ever uses 'string' and 'int';
--     'bool' is added as the standard third primitive since every settings system
--     eventually needs one. No 'json' -- nothing in §J's catalog needs it, and
--     activity_logs.metadata (migration 008) already has its own dedicated JSON column.

CREATE TABLE schools (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  name_ar     VARCHAR(150) NOT NULL,
  logo_path   VARCHAR(255) NULL,
  address     VARCHAR(255) NULL,
  phone       VARCHAR(30)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Single active row in v1 (decision #6) -- created once by the Setup Wizard (Phase 4+),
-- never seeded here per §N: no fake school data.

CREATE TABLE settings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key  VARCHAR(100) NOT NULL COMMENT 'Renamed from `key` -- reserved SQL word, §O-1',
  value        TEXT         NULL,
  value_type   ENUM('string','int','bool') NOT NULL DEFAULT 'string',
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_setting_key (setting_key)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
