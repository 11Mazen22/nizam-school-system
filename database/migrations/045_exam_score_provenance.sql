-- Nizam -- assessment score provenance (migration 045)
--
-- A mark can now arrive two ways: typed by a teacher on the score sheet, or
-- imported from an Excel workbook the school filled in. The database records
-- which one it was, and which import batch it belongs to, so an imported mark
-- can be traced back to the file that produced it and a manual mark is never
-- silently reclassified.
--
-- Existing rows stay 'manual': they were entered by a person.

ALTER TABLE exam_scores
  ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER recorded_by,
  ADD COLUMN import_batch VARCHAR(40) NULL AFTER source;

CREATE INDEX idx_exam_scores_source ON exam_scores (exam_id, source);
