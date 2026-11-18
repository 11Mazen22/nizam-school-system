-- Migration 037: religion field for staff
--
-- Nullable and unvalidated by data: the supplied school documents do not state
-- staff religion. The check constraint only guards the three known values once
-- the school records one.

ALTER TABLE teachers ADD COLUMN IF NOT EXISTS religion VARCHAR(20) NULL;

ALTER TABLE teachers DROP CONSTRAINT IF EXISTS chk_teachers_religion;
ALTER TABLE teachers ADD CONSTRAINT chk_teachers_religion
  CHECK (religion IS NULL OR religion IN ('muslim', 'christian', 'other'));

COMMENT ON COLUMN teachers.religion IS 'Recorded by the school when provided; NULL until recorded';
