<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Flash;
use App\Repositories\SchoolRepository;
use App\Request;
use App\Services\ActivityLogger;
use App\Services\AcademicRulesService;
use App\Services\LocaleService;
use App\Services\SchoolSettingsService;
use App\Services\SettingsAuditService;
use App\Services\SettingsService;
use App\Services\SignatoryService;
use App\Services\UploadService;
use InvalidArgumentException;
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
        $email = $request->post('email', '') ?: null;
        $website = $request->post('website', '') ?: null;
        $country = (string) ($request->post('country', 'EG') ?: 'EG');
        $timezone = (string) ($request->post('timezone', 'Africa/Cairo') ?: 'Africa/Cairo');
        $currency = (string) ($request->post('currency', 'EGP') ?: 'EGP');
        $footerText = $request->post('footer_text', '') ?: null;

        $checks = [
            [$name !== '' && mb_strlen($name) <= 150, 'settings.error.profile_name'],
            [$nameAr !== '' && mb_strlen($nameAr) <= 150, 'settings.error.profile_name'],
            [$email === null || filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'settings.error.email'],
            [$website === null || filter_var($website, FILTER_VALIDATE_URL) !== false, 'settings.error.website'],
            [mb_strlen($country) <= 2 && preg_match('/^[A-Za-z]{2}$/', $country) === 1, 'settings.error.country'],
            [mb_strlen($address ?? '') <= 255, 'settings.error.too_long'],
            [mb_strlen($phone ?? '') <= 30, 'settings.error.too_long'],
        ];
        foreach ($checks as [$valid, $errorKey]) {
            if (!$valid) {
                $this->view('settings/profile', ['error' => __($errorKey), 'school' => $school]);
                return;
            }
        }

        try {
            $timezone = SchoolSettingsService::validateTimezone($timezone);
            $currency = SchoolSettingsService::validateCurrency($currency);
            $weekInput = $request->postArray('school_week_days');
            $schoolWeek = $weekInput !== []
                ? SchoolSettingsService::normalizeWeekdays($weekInput)
                : SchoolSettingsService::normalizeWeekdays((string) ($request->post('school_week', '0,1,2,3,4') ?: '0,1,2,3,4'));
        } catch (InvalidArgumentException) {
            $this->view('settings/profile', ['error' => __('settings.error.profile_calendar'), 'school' => $school]);
            return;
        }

        $before = [
            'name' => $school['name'] ?? '', 'name_ar' => $school['name_ar'] ?? '',
            'address' => $school['address'] ?? '', 'phone' => $school['phone'] ?? '',
            'email' => $school['email'] ?? '', 'website' => $school['website'] ?? '',
            'school_type' => $school['school_type'] ?? '', 'school_structure' => $school['school_structure'] ?? '',
            'country' => $school['country'] ?? '', 'region' => $school['region'] ?? '',
            'timezone' => $school['timezone'] ?? '', 'currency' => $school['currency'] ?? '',
            'school_week' => $school['school_week'] ?? '', 'tax_number' => $school['tax_number'] ?? '',
            'affiliation_code' => $school['affiliation_code'] ?? '',
            'report_footer' => (string) (SettingsService::get('reports.school_footer_text', '') ?? ''),
        ];

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
            email: $email,
            website: $website,
            country: $country,
            region: $request->post('region', '') ?: null,
            timezone: $timezone,
            // Not present on this form: keep whatever is configured rather
            // than resetting it to a hardcoded default on every save.
            defaultLocale: (string) ($school['default_locale'] ?? 'ar'),
            currency: $currency,
            dateFormat: (string) ($school['date_format'] ?? 'YYYY-MM-DD'),
            numberFormat: (string) ($school['number_format'] ?? 'standard'),
            schoolWeek: $schoolWeek,
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

        SchoolSettingsService::reload();
        $afterRow = $this->schools->full() ?? [];
        $after = [
            'name' => $afterRow['name'] ?? '', 'name_ar' => $afterRow['name_ar'] ?? '',
            'address' => $afterRow['address'] ?? '', 'phone' => $afterRow['phone'] ?? '',
            'email' => $afterRow['email'] ?? '', 'website' => $afterRow['website'] ?? '',
            'school_type' => $afterRow['school_type'] ?? '', 'school_structure' => $afterRow['school_structure'] ?? '',
            'country' => $afterRow['country'] ?? '', 'region' => $afterRow['region'] ?? '',
            'timezone' => $afterRow['timezone'] ?? '', 'currency' => $afterRow['currency'] ?? '',
            'school_week' => $afterRow['school_week'] ?? '', 'tax_number' => $afterRow['tax_number'] ?? '',
            'affiliation_code' => $afterRow['affiliation_code'] ?? '',
            'report_footer' => (string) (SettingsService::get('reports.school_footer_text', '') ?? ''),
            'logo' => $hasNewLogo ? 'uploaded' : ($request->post('remove_logo') === '1' ? 'removed' : ($afterRow['logo_path'] ?? '')),
        ];
        $changes = [];
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? '';
            if ((string) $oldValue !== (string) $newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }
        SettingsAuditService::record('profile', $changes, 'School profile updated');
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

        $previous = (string) (SettingsService::get('app.default_language', 'ar') ?? 'ar');
        LocaleService::setDefault($language, (int) ($_SESSION['user_id'] ?? 0));
        SettingsAuditService::record('localization', [
            'app.default_language' => ['old' => $previous, 'new' => $language],
        ], 'Default language changed');
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
        $defaults = self::safeAttendanceDefaults();
        $this->view('settings/academic', [
            'error' => null,
            'expectedWeeklyCapacity' => SettingsService::get('academic.expected_weekly_capacity', 20),
            'studentIdPattern' => SettingsService::get('academic.student_id_pattern', 'STU-{year}-{seq:6}'),
            'teacherIdPattern' => SettingsService::get('academic.teacher_id_pattern', 'TCH-{seq:6}'),
            'rules' => [
                'academic.attendance_min_percent' => SettingsService::get('academic.attendance_min_percent', $defaults['min_percent']),
                'academic.attendance_warning_consecutive' => SettingsService::get('academic.attendance_warning_consecutive', $defaults['warning_consecutive']),
                'academic.attendance_warning_separate' => SettingsService::get('academic.attendance_warning_separate', $defaults['warning_separate']),
                'academic.attendance_critical_consecutive' => SettingsService::get('academic.attendance_critical_consecutive', $defaults['critical_consecutive']),
                'academic.attendance_critical_separate' => SettingsService::get('academic.attendance_critical_separate', $defaults['critical_separate']),
                'academic.year_work_completion_percent' => SettingsService::get('academic.year_work_completion_percent', 60),
            ],
        ]);
    }

    public function saveAcademic(Request $request): void
    {
        $capacity = (int) ($request->post('expected_weekly_capacity', '') ?: 0);
        $studentPattern = $request->post('student_id_pattern', '') ?: '';
        $teacherPattern = $request->post('teacher_id_pattern', '') ?: '';
        $defaults = self::safeAttendanceDefaults();
        $yearDefaults = self::safeYearWorkDefaults();
        // HTML form names use underscores (PHP normalizes dots in POST keys).
        $ruleFields = [
            'academic.attendance_min_percent' => ['academic_attendance_min_percent', $defaults['min_percent']],
            'academic.attendance_warning_consecutive' => ['academic_attendance_warning_consecutive', $defaults['warning_consecutive']],
            'academic.attendance_warning_separate' => ['academic_attendance_warning_separate', $defaults['warning_separate']],
            'academic.attendance_critical_consecutive' => ['academic_attendance_critical_consecutive', $defaults['critical_consecutive']],
            'academic.attendance_critical_separate' => ['academic_attendance_critical_separate', $defaults['critical_separate']],
            'academic.year_work_completion_percent' => ['academic_year_work_completion_percent', $yearDefaults['completion_percent']],
        ];
        $rules = [];
        foreach ($ruleFields as $settingKey => [$formKey, $fallback]) {
            $rules[$settingKey] = (int) ($request->post($formKey, (string) $fallback) ?: $fallback);
        }

        $error = null;
        if ($capacity < 1 || $capacity > 60) {
            $error = __('settings.error.capacity_range');
        } elseif (!preg_match('/\{seq:\d+\}/', $studentPattern) || !preg_match('/\{seq:\d+\}/', $teacherPattern)) {
            $error = __('settings.error.pattern_missing_seq');
        } elseif ($rules['academic.attendance_min_percent'] < 1 || $rules['academic.attendance_min_percent'] > 100) {
            $error = __('settings.error.percent_range');
        } elseif ($rules['academic.year_work_completion_percent'] < 1 || $rules['academic.year_work_completion_percent'] > 100) {
            $error = __('settings.error.percent_range');
        } else {
            foreach ($rules as $key => $value) {
                if ($value < 1 || $value > 365) {
                    $error = __('settings.error.absence_range');
                    break;
                }
            }
        }

        if ($error !== null) {
            $this->view('settings/academic', [
                'error' => $error, 'expectedWeeklyCapacity' => $capacity,
                'studentIdPattern' => $studentPattern, 'teacherIdPattern' => $teacherPattern,
                'rules' => $rules,
            ]);
            return;
        }

        $changes = [];
        foreach ([
            'academic.expected_weekly_capacity' => $capacity,
            'academic.student_id_pattern' => $studentPattern,
            'academic.teacher_id_pattern' => $teacherPattern,
        ] + $rules as $key => $value) {
            $old = SettingsService::get($key);
            if ((string) $old !== (string) $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
                SettingsService::set($key, $value, is_int($value) ? 'int' : 'string');
            }
        }
        SettingsAuditService::record('academic_rules', $changes, 'Academic and attendance rules updated');
        AcademicRulesService::reload();
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/academic');
    }

    // ------------------------------------------------------ Signatures
    /**
     * The two authorized signatures the school prints on its documents.
     *
     * This is a Settings screen, not a per-document prompt: configure the
     * principal and student-affairs signatory once, choose the Arabic
     * typeface that distinguishes them, and every document that declares
     * those roles uses the same resolved block on every applicable page.
     */
    public function signatures(Request $request): void
    {
        $this->view('settings/signatures', [
            'error' => null,
            'errorId' => 0,
            'rows' => (new SignatoryService())->settingsRows(),
            'fonts' => SignatoryService::FONTS,
            'placements' => SignatoryService::PLACEMENTS,
        ]);
    }

    public function saveSignature(Request $request): void
    {
        $id = (int) ($request->paramString('id') ?? 0);
        $service = new SignatoryService();

        try {
            $service->save(
                $id,
                [
                    'name_ar' => (string) ($request->post('name_ar', '') ?? ''),
                    'name_en' => (string) ($request->post('name_en', '') ?? ''),
                    'title_ar' => (string) ($request->post('title_ar', '') ?? ''),
                    'title_en' => (string) ($request->post('title_en', '') ?? ''),
                    'font_key' => (string) ($request->post('font_key', '') ?? ''),
                    'font_size' => (string) ($request->post('font_size', '10.5') ?? '10.5'),
                    'font_weight' => (string) ($request->post('font_weight', '600') ?? '600'),
                    'text_color' => (string) ($request->post('text_color', '#1F2937') ?? '#1F2937'),
                    'letter_spacing' => (string) ($request->post('letter_spacing', '0') ?? '0'),
                    'alignment' => (string) ($request->post('alignment', 'center') ?? 'center'),
                    'placement' => (string) ($request->post('placement', 'every_page') ?? 'every_page'),
                    'is_active' => $request->post('is_active') === '1',
                ],
                $request->file('signature'),
                $request->file('stamp'),
                $request->post('remove_signature') === '1',
                $request->post('remove_stamp') === '1',
            );
        } catch (RuntimeException $exception) {
            $key = match ($exception->getMessage()) {
                'not_found' => 'settings.signatures.error.not_found',
                'image_invalid' => 'settings.signatures.error.image',
                default => 'settings.signatures.error.validation',
            };
            http_response_code(422);
            $this->view('settings/signatures', [
                'error' => __($key),
                'errorId' => $id,
                'rows' => $service->settingsRows(),
                'fonts' => SignatoryService::FONTS,
                'placements' => SignatoryService::PLACEMENTS,
            ]);
            return;
        }

        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/signatures');
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

        $changes = [];
        foreach ([
            'security.login_max_attempts' => $maxAttempts,
            'security.lockout_minutes' => $lockoutMinutes,
            'security.session_timeout_minutes' => $sessionTimeout,
        ] as $key => $value) {
            $old = SettingsService::get($key);
            if ((string) $old !== (string) $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
                SettingsService::set($key, $value, 'int');
            }
        }
        SettingsAuditService::record('security', $changes, 'Security settings updated');
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

        $previous = (string) (SettingsService::get('backup.default_folder', 'database/backups') ?? 'database/backups');
        if ($previous !== $folder) {
            SettingsService::set('backup.default_folder', $folder, 'string');
            SettingsAuditService::record('backup', [
                'backup.default_folder' => ['old' => $previous, 'new' => $folder],
            ], 'Backup destination updated');
        }
        Flash::set('success', __('settings.saved'));
        $this->redirect('/settings/backup');
    }

    private static function isPgsql(): bool
    {
        return Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    /** @return array{min_percent:int,warning_consecutive:int,warning_separate:int,critical_consecutive:int,critical_separate:int} */
    private static function safeAttendanceDefaults(): array
    {
        $fallback = [
            'min_percent' => 85, 'warning_consecutive' => 10, 'warning_separate' => 20,
            'critical_consecutive' => 15, 'critical_separate' => 30,
        ];
        try {
            $rules = AcademicRulesService::attendanceRules();
            return array_intersect_key($rules, $fallback) + $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /** @return array{completion_percent:int} */
    private static function safeYearWorkDefaults(): array
    {
        try {
            return AcademicRulesService::yearWorkRules('GENERAL_SECONDARY', 3);
        } catch (\Throwable) {
            return ['completion_percent' => 60];
        }
    }
}
