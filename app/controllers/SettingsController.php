<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Flash;
use App\Repositories\SchoolRepository;
use App\Request;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Services\UploadService;
use PDO;
use RuntimeException;

/**
 * Nizam -- §Q/§O-21: Settings grouped into five screens (Profile,
 * Localization, Academic Rules, Security, Backup) specifically so a
 * non-technical admin changing the report footer is never sitting next to a
 * security-relevant toggle on the same form. Entirely gated on
 * settings.manage (§J: Administrator only, no Staff visibility at all --
 * the same single-permission shape as Users).
 */
final class SettingsController extends Controller
{
    private const LANGUAGES = ['ar', 'en'];

    public function __construct(
        private readonly SchoolRepository $schools = new SchoolRepository(),
        private readonly UploadService $uploads = new UploadService(),
    ) {
    }

    // -------------------------------------------- Pending offline sends
    /**
     * The offline queue lives in this browser's IndexedDB, not in the school
     * database, so the server renders only the shell and offline-queue.js
     * fills the list from the device. That keeps the promise the queue makes:
     * unsent work is visible on the machine holding it, and nowhere else.
     */
    public function offlineQueue(Request $request): void
    {
        $this->view('settings/offline-queue');
    }

    // ---------------------------------------------------------- Profile
    public function profile(Request $request): void
    {
        $this->view('settings/profile', ['error' => null, 'school' => $this->schools->full()]);
    }

    public function saveProfile(Request $request): void
    {
        $school = $this->schools->full();
        if ($school === null) {
            $this->redirect('/settings/profile');
            return;
        }

        $name = $request->post('name', '') ?: '';
        $nameAr = $request->post('name_ar', '') ?: '';
        $address = $request->post('address', '') ?: null;
        $phone = $request->post('phone', '') ?: null;
        $footerText = $request->post('footer_text', '') ?: null;

        if ($name === '' || $nameAr === '') {
            $this->view('settings/profile', ['error' => __('validation.required'), 'school' => $school]);
            return;
        }

        // Named arguments, deliberately: this call used to pass 16 positional
        // values into an 18-parameter method, which silently wrote currency
        // into default_locale, date_format into currency, school_week into
        // date_format and so on. Saving the profile therefore corrupted the
        // very identity fields the screen exists to configure. Named
        // arguments make that class of drift impossible to reintroduce.
        $this->schools->update(
            id: (int) $school['id'],
            name: $name,
            nameAr: $nameAr,
            address: $address,
            phone: $phone,
            email: $request->post('email', '') ?: null,
            website: $request->post('website', '') ?: null,
            country: (string) ($request->post('country', 'EG') ?: 'EG'),
            region: $request->post('region', '') ?: null,
            timezone: (string) ($request->post('timezone', 'Africa/Cairo') ?: 'Africa/Cairo'),
            // Not present on this form: keep whatever is configured rather
            // than resetting it to a hardcoded default on every save.
            defaultLocale: (string) ($school['default_locale'] ?? 'ar'),
            currency: (string) ($request->post('currency', 'EGP') ?: 'EGP'),
            dateFormat: (string) ($school['date_format'] ?? 'YYYY-MM-DD'),
            numberFormat: (string) ($school['number_format'] ?? 'standard'),
            schoolWeek: (string) ($request->post('school_week', '0,1,2,3,4') ?: '0,1,2,3,4'),
            schoolType: (string) ($request->post('school_type', 'language_school') ?: 'language_school'),
            schoolStructure: (string) ($request->post('school_structure', 'k12_combined') ?: 'k12_combined'),
            taxNumber: $request->post('tax_number', '') ?: null,
            affiliationCode: $request->post('affiliation_code', '') ?: null
        );
        SettingsService::set('reports.school_footer_text', (string) ($footerText ?? ''), 'string');

        // School logo: previously the shell and this screen both hardcoded the
        // original institution's crest, which made "configure your school's
        // identity" untrue for every other school. UploadService already
        // validates, re-encodes and stores the file outside public/.
        $logo = $request->file('logo');
        $hasNewLogo = $logo !== null && ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasNewLogo) {
            try {
                $logoPath = $this->uploads->store($logo, 'school');
            } catch (RuntimeException) {
                $this->view('settings/profile', [
                    'error' => __('settings.error.logo_invalid'),
                    'school' => $this->schools->full(),
                ]);
                return;
            }
            $this->uploads->delete($school['logo_path'] ?? null);
            $this->schools->updateLogoPath((int) $school['id'], $logoPath);
        } elseif ($request->post('remove_logo') === '1') {
            $this->uploads->delete($school['logo_path'] ?? null);
            $this->schools->updateLogoPath((int) $school['id'], null);
        }

        ActivityLogger::log('settings.update', 'settings', null, 'Profile settings updated');
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/profile');
    }

    /** GET /logo -- public, including the pre-login screen (a school crest isn't confidential); also shown in the app shell for every authenticated role, not gated on settings.manage. Resolved by the single schools row, never client input (§S-10). */
    public function logo(Request $request): void
    {
        $school = $this->schools->full();
        $path = $school['logo_path'] ?? null;

        // A fresh install's schools row points at the original crest, but no
        // bytes were ever stored for it, so /logo 404'd and every shell showed
        // a broken image. Serve the bundled crest when the stored file is
        // missing -- the same asset the views already fall back to when
        // logo_path is empty. Found by browser review of a freshly seeded
        // database, not by any status-code test.
        if ($path !== null && $path !== '' && \App\Services\UploadService::retrieve($path) !== null) {
            $this->uploads->stream($path);
            return;
        }

        $fallback = dirname(__DIR__, 2) . '/public/assets/img/hadaba-logo.png';
        if (is_file($fallback)) {
            \App\Response::inlineFile((string) file_get_contents($fallback), 'image/png');
            return;
        }

        \App\ErrorHandler::renderNotFound();
    }

    // ------------------------------------------------------ Localization
    public function localization(Request $request): void
    {
        $this->view('settings/localization', [
            'defaultLanguage' => SettingsService::get('app.default_language', 'ar'),
        ]);
    }

    public function saveLocalization(Request $request): void
    {
        $language = $request->post('default_language', '') ?: '';
        if (!in_array($language, self::LANGUAGES, true)) {
            Flash::set('danger', __('validation.required'));
            $this->redirect('/settings/localization');
            return;
        }

        SettingsService::set('app.default_language', $language, 'string');
        ActivityLogger::log('settings.update', 'settings', null, 'Localization settings updated');
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/localization');
    }

    // ------------------------------------------------- Privacy and retention
    /**
     * A factual page, not a legal promise: it lists what this installation
     * actually stores and the retention windows the scheduled maintenance
     * already enforces. School-specific retention choices still belong to the
     * school, but nobody should have to read the source to find the defaults.
     */
    public function privacy(Request $request): void
    {
        $this->view('settings/privacy', [
            'activityLogDays' => 90,
            'failedBackupDays' => 30,
            'successfulBackupDays' => 30,
            'backupsAlwaysKept' => 7,
            'sessionHours' => 24,
            'mfaEnabledUsers' => (int) Database::connection()->query('SELECT COUNT(*) FROM users WHERE mfa_enabled_at IS NOT NULL')->fetchColumn(),
        ]);
    }

    // -------------------------------------------------------- Academic
    public function academic(Request $request): void
    {
        $this->view('settings/academic', [
            'error' => null,
            'expectedWeeklyCapacity' => SettingsService::get('academic.expected_weekly_capacity', 20),
            'studentIdPattern' => SettingsService::get('academic.student_id_pattern', 'STU-{year}-{seq:6}'),
            'teacherIdPattern' => SettingsService::get('academic.teacher_id_pattern', 'TCH-{seq:6}'),
        ]);
    }

    public function saveAcademic(Request $request): void
    {
        $capacity = (int) ($request->post('expected_weekly_capacity', '') ?: 0);
        $studentPattern = $request->post('student_id_pattern', '') ?: '';
        $teacherPattern = $request->post('teacher_id_pattern', '') ?: '';

        $error = null;
        if ($capacity < 1 || $capacity > 60) {
            $error = __('settings.error.capacity_range');
        } elseif (!preg_match('/\{seq:\d+\}/', $studentPattern) || !preg_match('/\{seq:\d+\}/', $teacherPattern)) {
            // IdPattern::render's whole mechanism depends on a {seq:N} token --
            // a pattern without one would give every future student/teacher
            // the exact same generated code.
            $error = __('settings.error.pattern_missing_seq');
        }

        if ($error !== null) {
            $this->view('settings/academic', [
                'error' => $error, 'expectedWeeklyCapacity' => $capacity,
                'studentIdPattern' => $studentPattern, 'teacherIdPattern' => $teacherPattern,
            ]);
            return;
        }

        SettingsService::set('academic.expected_weekly_capacity', $capacity, 'int');
        SettingsService::set('academic.student_id_pattern', $studentPattern, 'string');
        SettingsService::set('academic.teacher_id_pattern', $teacherPattern, 'string');
        ActivityLogger::log('settings.update', 'settings', null, 'Academic rule settings updated');
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/academic');
    }

    // -------------------------------------------------------- Security
    public function security(Request $request): void
    {
        $this->view('settings/security', [
            'error' => null,
            'loginMaxAttempts' => SettingsService::get('security.login_max_attempts', 5),
            'lockoutMinutes' => SettingsService::get('security.lockout_minutes', 15),
            'sessionTimeoutMinutes' => SettingsService::get('security.session_timeout_minutes', 60),
        ]);
    }

    public function saveSecurity(Request $request): void
    {
        $maxAttempts = (int) ($request->post('login_max_attempts', '') ?: 0);
        $lockoutMinutes = (int) ($request->post('lockout_minutes', '') ?: 0);
        $sessionTimeout = (int) ($request->post('session_timeout_minutes', '') ?: 0);

        // Sane bounds, not arbitrary ones: below the floor and the control
        // stops being usable (a 1-attempt lockout locks out typos, a 1-minute
        // session logs someone out mid-sentence); above the ceiling and the
        // control stops doing the job it exists for.
        $error = null;
        if ($maxAttempts < 3 || $maxAttempts > 20) {
            $error = __('settings.error.attempts_range');
        } elseif ($lockoutMinutes < 1 || $lockoutMinutes > 1440) {
            $error = __('settings.error.lockout_range');
        } elseif ($sessionTimeout < 5 || $sessionTimeout > 480) {
            $error = __('settings.error.timeout_range');
        }

        if ($error !== null) {
            $this->view('settings/security', [
                'error' => $error, 'loginMaxAttempts' => $maxAttempts,
                'lockoutMinutes' => $lockoutMinutes, 'sessionTimeoutMinutes' => $sessionTimeout,
            ]);
            return;
        }

        SettingsService::set('security.login_max_attempts', $maxAttempts, 'int');
        SettingsService::set('security.lockout_minutes', $lockoutMinutes, 'int');
        SettingsService::set('security.session_timeout_minutes', $sessionTimeout, 'int');
        ActivityLogger::log('settings.update', 'settings', null, 'Security settings updated');
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/security');
    }

    // ---------------------------------------------------------- Backup
    public function backup(Request $request): void
    {
        // pgsql/online deployment: backups always go to Supabase Storage
        // (BackupService's own docblock) -- a local-folder config form here
        // would be a control with no effect, exactly the placeholder UI the
        // zero-gap pass was built to eliminate. Show an informational
        // notice instead.
        $this->view('settings/backup', [
            'error' => null,
            'cloudStorage' => self::isPgsql(),
            'defaultFolder' => SettingsService::get('backup.default_folder', 'database/backups'),
        ]);
    }

    public function saveBackup(Request $request): void
    {
        if (self::isPgsql()) {
            // Defense in depth: the form is hidden on this deployment, but a
            // direct POST must still be refused rather than silently
            // accepted into a setting nothing ever reads.
            $this->redirect('/settings/backup');
            return;
        }

        $folder = trim($request->post('default_folder', '') ?: '');

        $error = null;
        if ($folder === '' || str_contains($folder, '..')) {
            // Not a client-facing path-serving endpoint (§S-10 doesn't apply
            // the same way here -- this is an admin-only config value, not a
            // request parameter) but ".." still has no legitimate reason to
            // appear in a destination folder setting, and rejecting it
            // outright is simpler than reasoning about where a traversal
            // could land.
            $error = __('settings.error.folder_invalid');
        } else {
            $absolute = \App\Services\BackupService::resolveDirectory($folder);
            if (!is_dir($absolute) && !@mkdir($absolute, 0755, true)) {
                $error = __('settings.error.folder_not_writable');
            } elseif (!is_writable($absolute)) {
                $error = __('settings.error.folder_not_writable');
            }
        }

        if ($error !== null) {
            $this->view('settings/backup', ['error' => $error, 'cloudStorage' => false, 'defaultFolder' => $folder]);
            return;
        }

        SettingsService::set('backup.default_folder', $folder, 'string');
        ActivityLogger::log('settings.update', 'settings', null, 'Backup settings updated');
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/backup');
    }

    private static function isPgsql(): bool
    {
        return Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }
}
