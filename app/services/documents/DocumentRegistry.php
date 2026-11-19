<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Services\Documents\Types\AttendanceDocument;
use App\Services\Documents\Types\ClassTimetableDocument;
use App\Services\Documents\Types\DocumentType;
use App\Services\Documents\Types\FinanceDocument;
use App\Services\Documents\Types\InvoiceDocument;
use App\Services\Documents\Types\MasterTimetableDocument;
use App\Services\Documents\Types\PromotionDocument;
use App\Services\Documents\Types\RankingDocument;
use App\Services\Documents\Types\ReportCardDocument;
use App\Services\Documents\Types\ReportDocument;
use App\Services\Documents\Types\StudentDocument;
use App\Services\Documents\Types\StudentsDocument;
use App\Services\Documents\Types\TeacherDocument;
use App\Services\Documents\Types\TeachersDocument;
use App\Services\Documents\Types\TeacherTimetableDocument;
use App\Services\Documents\Types\TimetableCoversDocument;
use App\Services\Documents\Types\TimetableGapsDocument;
use InvalidArgumentException;

/**
 * The single list of documents NIZAM can produce.
 *
 * A new printable report is added here and immediately inherits the whole
 * pipeline -- pagination, institutional identity, PDF/PNG/Excel/ZIP, preview,
 * progress, authorisation and audit -- with no change to any shared class.
 */
final class DocumentRegistry
{
    /** @var array<string, class-string<DocumentType>> */
    private const TYPES = [
        'students' => StudentsDocument::class,
        'student' => StudentDocument::class,
        'teachers' => TeachersDocument::class,
        'teacher' => TeacherDocument::class,
        'timetable-class' => ClassTimetableDocument::class,
        'timetable-teacher' => TeacherTimetableDocument::class,
        'timetable-master' => MasterTimetableDocument::class,
        'report' => ReportDocument::class,
        'attendance' => AttendanceDocument::class,
        'finance' => FinanceDocument::class,
        'invoice' => InvoiceDocument::class,
        'report-card' => ReportCardDocument::class,
        'ranking' => RankingDocument::class,
        'timetable-gaps' => TimetableGapsDocument::class,
        'timetable-covers' => TimetableCoversDocument::class,
        'promotion' => PromotionDocument::class,
    ];

    public function has(string $key): bool
    {
        return isset(self::TYPES[$key]);
    }

    public function make(string $key): DocumentType
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException('unknown_document');
        }
        $class = self::TYPES[$key];

        return new $class();
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::TYPES);
    }
}
