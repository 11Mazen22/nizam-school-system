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

        $this->schools->update($school['id'], $name, $nameAr, $address, $phone);
        SettingsService::set('reports.school_footer_text', (string) ($footerText ?? ''), 'string');

        if ($request->post('remove_logo') === '1') {
            $this->uploads->delete($school['logo_path']);
            $this->schools->updateLogoPath($school['id'], null);
        } else {
            $file = $request->file('logo');
            if ($file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newPath = $this->uploads->store($file, 'school');
                    $this->uploads->delete($school['logo_path']);
                    $this->schools->updateLogoPath($school['id'], $newPath);
                } catch (RuntimeException $e) {
                    Flash::set('warning', match ($e->getMessage()) {
                        'too_large' => __('uploads.error.too_large'),
                        'invalid_type', 'not_an_image' => __('uploads.error.invalid_type'),
                        default => __('uploads.error.generic'),
                    });
                }
            }
        }

        ActivityLogger::log('settings.update', 'settings', null, 'Profile settings updated');
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/profile');
    }

    /** GET /logo -- any authenticated user (shown in the app shell for every role), not gated on settings.manage. Resolved by the single schools row, never client input (§S-10). */
    public function logo(Request $request): void
    {
        $school = $this->schools->full();
        $this->uploads->stream($school['logo_path'] ?? null);
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
