-- Keep existing PostgreSQL installations aligned with the packaged default
-- logo. A custom UploadService path is never changed by this migration.
UPDATE schools
SET logo_path = 'school/hadaba-logo.png'
WHERE logo_path = '/assets/img/hadaba-logo.png';
