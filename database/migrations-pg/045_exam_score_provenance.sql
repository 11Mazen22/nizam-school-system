-- Nizam -- assessment score provenance (PostgreSQL twin of migration 045).
-- See the MySQL file for the full reasoning.

ALTER TABLE exam_scores
  ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'manual',
  ADD COLUMN import_batch VARCHAR(40);

CREATE INDEX idx_exam_scores_source ON exam_scores (exam_id, source);
