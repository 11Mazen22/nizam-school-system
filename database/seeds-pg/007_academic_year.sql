-- Nizam School Management System -- Seed: Default Academic Year (PostgreSQL)
--
-- Hadaba Al-Ahram School deployment: Creates a default academic year
-- so the system is immediately usable without requiring manual setup.
-- This matches the production deployment behavior.

INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed, expected_weekly_capacity)
SELECT 
    '2025/2026',
    '2025-09-01'::date,
    '2026-06-30'::date,
    true,
    false,
    35
WHERE NOT EXISTS (SELECT 1 FROM academic_years WHERE is_active = true);
