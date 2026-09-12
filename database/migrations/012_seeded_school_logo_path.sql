-- Repair installations created before the packaged school logo became an
-- UploadService-managed file. User-uploaded logos use a different relative
-- path and are intentionally left untouched.
UPDATE schools
SET logo_path = 'school/hadaba-logo.png'
WHERE logo_path = '/assets/img/hadaba-logo.png';
