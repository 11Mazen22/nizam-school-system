-- Nizam School Management System (Postgres/Supabase)
-- Migration 004: grades, subjects, subject_staffing_requirements
-- Postgres translation of database/migrations/004_grades_subjects_staffing.sql

CREATE TABLE grades (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name_en     VARCHAR(60)  NOT NULL,
  name_ar     VARCHAR(60)  NOT NULL,
  sort_order  SMALLINT     NOT NULL,
  is_active   SMALLINT     NOT NULL DEFAULT 1
);

CREATE TABLE subjects (
  id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code      VARCHAR(20)  NOT NULL UNIQUE,
  name_en   VARCHAR(100) NOT NULL,
  name_ar   VARCHAR(100) NOT NULL,
  is_active SMALLINT     NOT NULL DEFAULT 1
);

CREATE TABLE subject_staffing_requirements (
  id                 INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  subject_id         INTEGER  NOT NULL,
  academic_year_id   INTEGER  NOT NULL,
  required_teachers  SMALLINT NOT NULL,
  CONSTRAINT uq_ssr_subject_year UNIQUE (subject_id, academic_year_id),
  CONSTRAINT fk_ssr_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ssr_academic_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);
