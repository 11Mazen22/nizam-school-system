-- Nizam School Management System (Postgres/Supabase)
-- Migration 018: exams, exam_scores

CREATE TABLE exams (
  id               INTEGER          GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER          NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  name_en          VARCHAR(100)     NOT NULL,
  name_ar          VARCHAR(100)     NOT NULL,
  term             SMALLINT         NOT NULL DEFAULT 1,
  max_score        DECIMAL(5,2)     NOT NULL DEFAULT 100.00,
  weight           DECIMAL(5,2)     NOT NULL DEFAULT 100.00,
  is_active        SMALLINT         NOT NULL DEFAULT 1,
  created_at       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_exams_year_term ON exams (academic_year_id, term);

CREATE TABLE exam_scores (
  id          INTEGER          GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  exam_id     INTEGER          NOT NULL REFERENCES exams(id) ON DELETE CASCADE ON UPDATE CASCADE,
  student_id  INTEGER          NOT NULL REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
  subject_id  INTEGER          NOT NULL REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  score       DECIMAL(5,2)     NULL,
  notes       VARCHAR(255)     NULL,
  recorded_by INTEGER          NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_score_exam_student_subject ON exam_scores (exam_id, student_id, subject_id);
CREATE INDEX idx_scores_student ON exam_scores (student_id);
CREATE INDEX idx_scores_exam    ON exam_scores (exam_id);

CREATE TRIGGER trg_exam_scores_updated_at
  BEFORE UPDATE ON exam_scores
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
