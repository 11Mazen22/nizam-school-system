-- Nizam -- official 2026/2027 school package import support.
--
-- The source package (Hadaba Al-Ahram, November 2026) supplies:
--   * 17 classes A1-A9 / B1-B8, each with an official Arabic pathway label
--   * nine weekly assessment distributions A1-A9 (45 weekday rows)
-- Every imported value must keep its source document and page, so the tables
-- below carry provenance columns and the import itself is recorded in
-- import_batches.

ALTER TABLE classes
  ADD COLUMN pathway_ar VARCHAR(80) NULL DEFAULT NULL
  COMMENT 'Official Arabic pathway label from the roster heading; never normalized'
  AFTER name;

CREATE TABLE IF NOT EXISTS assessment_schedules (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  day_of_week      TINYINT UNSIGNED NOT NULL COMMENT '1=Saturday .. 7=Friday (school week); Sunday=2',
  sequence         TINYINT UNSIGNED NOT NULL COMMENT 'Row number inside the source table (1-5)',
  subjects_raw     VARCHAR(255) NOT NULL COMMENT 'Exact cell text, bullets preserved',
  no_assessment    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 only for an explicit no-assessment source row',
  source_document  VARCHAR(190) NOT NULL,
  source_class     VARCHAR(10)  NOT NULL,
  source_version   VARCHAR(40)  NOT NULL DEFAULT '2026/2027',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assessment_class_day (academic_year_id, class_id, day_of_week),
  KEY idx_assessment_class (class_id),
  CONSTRAINT fk_assessment_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assessment_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batches (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_package VARCHAR(190) NOT NULL,
  source_version VARCHAR(40)  NOT NULL,
  summary_json   JSON         NOT NULL,
  imported_by    VARCHAR(60)  NOT NULL DEFAULT 'cli',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
