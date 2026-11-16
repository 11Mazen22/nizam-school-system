-- Nizam School Management System -- Seed: current default academic year
--
-- Never freeze a calendar year in a reusable installer. From July onward a
-- school is normally preparing the year that starts that September; January
-- through June belongs to the year that began the previous September. This
-- seed creates that current/planning year only when no active year exists,
-- and therefore never overwrites an administrator's real calendar.

WITH year_bounds AS (
  SELECT (
    EXTRACT(YEAR FROM CURRENT_DATE)::INTEGER
    - CASE WHEN EXTRACT(MONTH FROM CURRENT_DATE) < 7 THEN 1 ELSE 0 END
  ) AS start_year
)
INSERT INTO academic_years (
  label, start_date, end_date, is_active, is_closed, expected_weekly_capacity
)
SELECT
  start_year::TEXT || '/' || (start_year + 1)::TEXT,
  make_date(start_year, 9, 1),
  make_date(start_year + 1, 6, 30),
  true,
  false,
  35
FROM year_bounds
WHERE NOT EXISTS (SELECT 1 FROM academic_years WHERE is_active = true);
