<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Nizam -- the official timetable package's own vocabulary.
 *
 * The 17 PDFs print short subject labels and Arabic day names. Both the
 * original importer and the gap synchroniser must read them the same way, so
 * the mapping lives here once instead of being copied into each script.
 */
final class TimetableSource
{
    /** Arabic day printed on the grid => ISO day used by `timetables`. */
    public const DAYS = [
        'الأحد' => 2,
        'الاثنين' => 3,
        'الثلاثاء' => 4,
        'الأربعاء' => 5,
        'الخميس' => 6,
    ];

    /** Printed subject => an existing subject row, or a subject to create. */
    public const SUBJECTS = [
        'عربي' => ['existing' => 'اللغة العربية'],
        'إنجليزي' => ['existing' => 'اللغة الإنجليزية'],
        'رياضيات' => ['existing' => 'الرياضيات'],
        'علوم' => ['existing' => 'العلوم'],
        'تاريخ' => ['existing' => 'التاريخ'],
        'برمجة' => ['existing' => 'الحاسوب'],
        'دين' => ['existing' => 'التربية الدينية'],
        'فلسفة' => ['existing' => 'الفلسفة'],
        'ألماني/فرنساوي' => ['existing' => 'اللغة الثانية'],
        'G+F' => ['existing' => 'اللغة الثانية'],
        'G+A' => ['existing' => 'اللغة الثانية'],
        'ثقافة مالية' => ['new' => ['FIN_LIT', 'الثقافة المالية', 'Financial Literacy']],
        'ألعاب' => ['new' => ['SPORTS', 'الألعاب الرياضية', 'Sports']],
        'نشاط' => ['new' => ['ACTIVITY', 'النشاط المدرسي', 'School Activity']],
        'فيزياء' => ['new' => ['PHYSICS', 'الفيزياء', 'Physics']],
        'كيمياء/إدارة أعمال' => ['new' => ['CHEM_BUS', 'الكيمياء / إدارة أعمال', 'Chemistry / Business Administration']],
        'علم نفس/لغة ثانية' => ['new' => ['PSY_LANG2', 'علم النفس / لغة ثانية', 'Psychology / Second Language']],
        'ألماني' => ['new' => ['GERMAN', 'اللغة الألمانية', 'German']],
        'إسباني' => ['new' => ['SPANISH', 'اللغة الإسبانية', 'Spanish']],
    ];

    /** Cells that are not lessons at all. */
    public const NOT_LESSONS = ['الغياب'];

    /** The Arabic name a printed label resolves to, when it maps to a row. */
    public static function subjectName(string $label): ?string
    {
        $target = self::SUBJECTS[trim($label)] ?? null;
        if ($target === null) {
            return null;
        }

        return $target['existing'] ?? ($target['new'][1] ?? null);
    }

    /**
     * A printed teacher cell may be a group pair ("خالد/إيناس"). The first
     * name owns the slot; the second is still a real teacher on record.
     */
    public static function primaryTeacher(string $printed): string
    {
        $names = array_values(array_filter(array_map('trim', explode('/', $printed))));

        return $names[0] ?? '';
    }

    /** @return list<string> */
    public static function teacherNames(string $printed): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $printed))));
    }
}
