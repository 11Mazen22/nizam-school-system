-- Nizam -- roster CSV provenance.
--
-- The official roster pages carry a column headed الديانة\اللغة الثانية whose
-- meaning varies by class and row. The brief requires keeping the printed
-- value verbatim and letting an administrator classify it later, so it is
-- stored as raw source text next to the student record rather than being
-- silently folded into religion.

ALTER TABLE students
  ADD COLUMN class_attribute_raw VARCHAR(120) NULL DEFAULT NULL
    COMMENT 'Exact roster column value (الديانة\\اللغة الثانية), unparsed'
    AFTER notes,
  ADD COLUMN import_source VARCHAR(40) NULL DEFAULT NULL
    COMMENT 'roster_csv, manual, ... -- where the record came from'
    AFTER class_attribute_raw;
