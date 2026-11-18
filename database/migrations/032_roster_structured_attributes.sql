-- Migration 032: source-backed structured roster attributes
--
-- The printed roster combines religion, second language and pathway in one
-- cell. Keep class_attribute_raw verbatim for audit, while storing each value
-- the source actually proves in its own column. Source columns distinguish
-- reviewed roster data from later manual edits; unknown values remain NULL.

ALTER TABLE students
  ADD COLUMN second_language VARCHAR(20) NULL DEFAULT NULL AFTER religion,
  ADD COLUMN gender_source VARCHAR(40) NULL DEFAULT NULL AFTER gender,
  ADD COLUMN religion_source VARCHAR(40) NULL DEFAULT NULL AFTER religion,
  ADD COLUMN second_language_source VARCHAR(40) NULL DEFAULT NULL AFTER second_language;
