-- Nizam School Management System -- Seed: current default academic year
--
-- Dynamic twin of seeds-pg/007. From July onward, prepare the year beginning
-- that September; January-June belong to the year that began the previous
-- September. Existing active years are never changed.

SET @nizam_start_year := YEAR(CURDATE()) - IF(MONTH(CURDATE()) < 7, 1, 0);

INSERT INTO academic_years (
  label, start_date, end_date, is_active, is_closed, expected_weekly_capacity
)
SELECT * FROM (
  SELECT
    CONCAT(@nizam_start_year, '/', @nizam_start_year + 1) AS label,
    STR_TO_DATE(CONCAT(@nizam_start_year, '-09-01'), '%Y-%m-%d') AS start_date,
    STR_TO_DATE(CONCAT(@nizam_start_year + 1, '-06-30'), '%Y-%m-%d') AS end_date,
    1 AS is_active,
    0 AS is_closed,
    35 AS expected_weekly_capacity
) AS current_year
WHERE NOT EXISTS (SELECT 1 FROM academic_years WHERE is_active = 1);
