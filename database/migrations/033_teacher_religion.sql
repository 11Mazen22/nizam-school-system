-- Migration 033: religion field for staff
--
-- Teachers may be recorded by the school as Muslim or Christian. The supplied
-- roster documents do not state staff religion, so this stays NULL until a
-- school enters it; the value is only ever used for optional filtering.

ALTER TABLE teachers
  ADD COLUMN religion VARCHAR(20) NULL DEFAULT NULL AFTER email;
