-- Nizam -- timetable provenance and clash-resolution history
-- (PostgreSQL twin of migration 044; see that file for the full reasoning).

ALTER TABLE timetables
  ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT 'manual',
  ADD COLUMN import_source VARCHAR(120),
  ADD COLUMN imported_at TIMESTAMP,
  ADD COLUMN manual_override SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN manual_edited_at TIMESTAMP,
  ADD COLUMN manual_edited_by INTEGER,
  ADD COLUMN clash_resolved_at TIMESTAMP,
  ADD COLUMN clash_resolved_by INTEGER,
  ADD COLUMN clash_resolution_note VARCHAR(255);

UPDATE timetables
   SET origin = 'imported',
       import_source = 'timetables_17.json',
       imported_at = created_at;

CREATE INDEX idx_timetable_origin ON timetables (academic_year_id, origin);

ALTER TABLE timetables
  ADD CONSTRAINT fk_timetable_manual_editor
    FOREIGN KEY (manual_edited_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_clash_resolver
    FOREIGN KEY (clash_resolved_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE timetable_resolutions (
  id                  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id    INTEGER NOT NULL,
  timetable_id        INTEGER,
  class_id            INTEGER NOT NULL,
  day_of_week         SMALLINT NOT NULL,
  period_number       SMALLINT NOT NULL,
  printed_teacher     VARCHAR(190),
  previous_teacher_id INTEGER,
  previous_subject_id INTEGER,
  resolved_teacher_id INTEGER NOT NULL,
  resolved_subject_id INTEGER NOT NULL,
  note                VARCHAR(255),
  resolved_by         INTEGER,
  resolved_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_timetable_resolution_year ON timetable_resolutions (academic_year_id, resolved_at);
CREATE INDEX idx_timetable_resolution_lesson ON timetable_resolutions (timetable_id);

ALTER TABLE timetable_resolutions
  ADD CONSTRAINT fk_timetable_resolution_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_resolution_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_resolution_teacher
    FOREIGN KEY (resolved_teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_resolution_subject
    FOREIGN KEY (resolved_subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_resolution_user
    FOREIGN KEY (resolved_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE timetable_import_exclusions (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER NOT NULL,
  class_id         INTEGER NOT NULL,
  day_of_week      SMALLINT NOT NULL,
  period_number    SMALLINT NOT NULL,
  reason           VARCHAR(40) NOT NULL DEFAULT 'manual_delete',
  created_by       INTEGER,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_timetable_import_exclusion
  ON timetable_import_exclusions (academic_year_id, class_id, day_of_week, period_number);

ALTER TABLE timetable_import_exclusions
  ADD CONSTRAINT fk_timetable_exclusion_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_exclusion_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_timetable_exclusion_user
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- RLS for the two new tables, matching the platform-wide rule in 010.
ALTER TABLE public.timetable_resolutions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.timetable_resolutions;
CREATE POLICY nizam_app_full_access ON public.timetable_resolutions FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.timetable_import_exclusions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.timetable_import_exclusions;
CREATE POLICY nizam_app_full_access ON public.timetable_import_exclusions FOR ALL TO nizam_app USING (true) WITH CHECK (true);
