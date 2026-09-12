-- Nizam School Management System -- Seed: Default Academic Year
--
-- Hadaba Al-Ahram School deployment: Creates a default academic year
-- so the system is immediately usable without requiring manual setup.
-- This matches the production deployment behavior.

INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed, expected_weekly_capacity)
SELECT * FROM (
    SELECT 
        '2025/2026' AS label,
        '2025-09-01' AS start_date,
        '2026-06-30' AS end_date,
        1 AS is_active,
        0 AS is_closed,
        35 AS expected_weekly_capacity
) AS s
WHERE NOT EXISTS (SELECT 1 FROM academic_years WHERE is_active = 1);
