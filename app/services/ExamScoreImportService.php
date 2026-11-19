<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\ExamRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Nizam -- exam score import from a real .xlsx workbook.
 *
 * A school that already keeps marks in Excel must be able to bring them in
 * without retyping hundreds of cells, but a spreadsheet is also the easiest
 * place to quietly mis-key a student code. This service therefore keeps the
 * three phases strictly separate:
 *
 *   parse    -- read the workbook into plain cell strings (no database writes)
 *   validate -- every row gets ok / skipped / error with explicit reasons
 *   commit   -- write only the fully validated rows, in one transaction
 *
 * Nothing is guessed. A code that is not on the class roster, a subject that
 * is not taught to the class, a mark above the exam maximum and a duplicated
 * student+subject pair are errors, never silent corrections. A blank mark is
 * *skipped* rather than stored as NULL: a blank cell in a file must never
 * erase a mark a teacher already entered by hand.
 */
final class ExamScoreImportService
{
    /** Canonical, language-neutral column names. Arabic aliases are accepted too. */
    public const HEADERS = ['Student Code', 'Student Name', 'Subject', 'Score', 'Notes'];

    public const MAX_ROWS = 5000;

    private const ALIASES = [
        'student code' => 'Student Code',
        'student id' => 'Student Code',
        'كود الطالب' => 'Student Code',
        'رقم الطالب' => 'Student Code',
        'student name' => 'Student Name',
        'full name' => 'Student Name',
        'اسم الطالب' => 'Student Name',
        'subject' => 'Subject',
        'subject code' => 'Subject',
        'المادة' => 'Subject',
        'score' => 'Score',
        'mark' => 'Score',
        'الدرجة' => 'Score',
        'notes' => 'Notes',
        'ملاحظات' => 'Notes',
    ];

    public function __construct(private readonly ExamRepository $exams = new ExamRepository())
    {
    }

    /**
     * A workbook with the real roster of the selected class pre-filled: code,
     * name and subject are there, only the score column is blank. This is not
     * a sample with invented pupils -- it contains the school's own enrolled
     * students, so filling it is all the operator has to do.
     */
    public function template(int $examId, int $classId, string $locale): string
    {
        $exam = $this->exams->find($examId);
        $yearId = AcademicYearContext::activeYearId();
        if ($exam === null || $yearId === null || (int) $exam['academic_year_id'] !== $yearId) {
            throw new RuntimeException('exam_not_found');
        }
        $class = (new \App\Repositories\ClassRepository())->find($classId);
        if ($class === null || (int) $class['academic_year_id'] !== $yearId) {
            throw new RuntimeException('class_not_found');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Scores');
        $sheet->setRightToLeft($locale === 'ar');
        $headers = $locale === 'ar'
            ? ['كود الطالب', 'اسم الطالب', 'المادة', 'الدرجة', 'ملاحظات']
            : self::HEADERS;
        $this->writeRow($sheet, 1, $headers, true);
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0F5132');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->freezePane('A2');

        $subjects = $this->subjectsForClass($classId, $yearId);
        $row = 2;
        foreach ($this->exams->rosterForClass($classId, $yearId) as $student) {
            foreach ($subjects as $subject) {
                $subjectName = $locale === 'ar' ? $subject['name_ar'] : $subject['name_en'];
                $this->writeRow($sheet, $row, [
                    (string) $student['student_code'],
                    (string) $student['full_name'],
                    // A plain ASCII separator survives every spreadsheet and
                    // upload path; an em dash was mangled by one test transfer.
                    (string) $subject['code'] . ' - ' . (string) $subjectName,
                    '',
                    '',
                ]);
                $row++;
            }
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $help = new Worksheet($spreadsheet, 'How to fill');
        $spreadsheet->addSheet($help, 1);
        $help->setRightToLeft($locale === 'ar');
        $lines = $locale === 'ar' ? [
            'املأ عمود الدرجة فقط. اترك الخلية فارغة إذا لم تُرصد درجة بعد.',
            'كود الطالب والمادة مملوءان من سجل المدرسة؛ لا تغيّرهما إلا إذا كانا خطأ.',
            'يمكن كتابة المادة بكودها أو باسمها العربي أو الإنجليزي.',
            'الدرجة يجب أن تكون رقماً بين صفر و' . (string) $exam['max_score'] . '.',
            'لا تضف طلاباً أو مواد جديدة: استخدم شاشة الطلاب أو التكليفات أولاً.',
        ] : [
            'Fill the Score column only. Leave a cell empty if no mark has been recorded.',
            'Student code and subject come from the school register; change them only if wrong.',
            'A subject may be written by code or by its Arabic or English name.',
            'The score must be a number between zero and ' . (string) $exam['max_score'] . '.',
            'Do not add students or subjects: use the students or assignments screen first.',
        ];
        foreach ($lines as $index => $line) {
            $help->setCellValue([1, $index + 1], $line);
        }
        $help->getColumnDimension('A')->setWidth(110);

        $temp = tempnam(sys_get_temp_dir(), 'nizam_scores_');
        if ($temp === false) {
            throw new RuntimeException('export_failed');
        }
        (new Xlsx($spreadsheet))->save($temp);
        $bytes = (string) file_get_contents($temp);
        @unlink($temp);

        return $bytes;
    }

    /**
     * @return list<array{line:int,data:array<string,string>}>
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('exam_import_file_missing');
        }
        $size = filesize($path);
        if ($size === false || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('exam_import_file_too_large');
        }
        // An uploaded file lives at a temporary path with no extension, so
        // inspect the bytes rather than the filename: .xlsx is a ZIP package.
        $handle = @fopen($path, 'rb');
        $magic = $handle === false ? '' : (string) fread($handle, 2);
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($magic !== 'PK') {
            throw new RuntimeException('exam_import_format');
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable) {
            throw new RuntimeException('exam_import_unreadable');
        }

        $sheet = $book->getSheet(0);
        if ($sheet === null) {
            throw new RuntimeException('exam_import_empty');
        }
        $grid = $sheet->toArray(null, true, true, false);
        if ($grid === []) {
            throw new RuntimeException('exam_import_empty');
        }

        $columns = [];
        foreach (array_shift($grid) as $index => $value) {
            $name = $this->normalizeHeader((string) $value);
            if ($name === '') {
                continue;
            }
            $canonical = self::ALIASES[$name] ?? null;
            if ($canonical === null || isset($columns[$canonical])) {
                throw new RuntimeException('exam_import_headers');
            }
            $columns[$canonical] = (int) $index;
        }
        foreach (['Student Code', 'Subject'] as $required) {
            if (!isset($columns[$required])) {
                throw new RuntimeException('exam_import_headers');
            }
        }

        $rows = [];
        $line = 1;
        foreach ($grid as $cells) {
            $line++;
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('exam_import_too_many_rows');
            }
            $data = [];
            $blank = true;
            foreach ($columns as $canonical => $index) {
                $value = trim($this->cellText($cells[$index] ?? null));
                $data[$canonical] = $value;
                if ($value !== '') {
                    $blank = false;
                }
            }
            if ($blank) {
                continue;
            }
            $rows[] = ['line' => $line, 'data' => $data];
        }

        return $rows;
    }

    /**
     * @param list<array{line:int,data:array<string,string>}> $rows
     * @return array{
     *   rows:list<array{line:int,student_id:int,student_name:string,subject_id:int,subject_name:string,score:?float,notes:?string,status:string,messages:list<string>}>,
     *   counts:array{ok:int,skipped:int,error:int}
     * }
     */
    public function validate(array $rows, int $examId, int $classId): array
    {
        $exam = $this->exams->find($examId);
        $yearId = AcademicYearContext::activeYearId();
        if ($exam === null || $yearId === null || (int) $exam['academic_year_id'] !== $yearId) {
            throw new RuntimeException('exam_not_found');
        }
        $class = (new \App\Repositories\ClassRepository())->find($classId);
        if ($class === null || (int) $class['academic_year_id'] !== $yearId) {
            throw new RuntimeException('class_not_found');
        }

        $byCode = [];
        foreach ($this->exams->rosterForClass($classId, $yearId) as $student) {
            $code = strtoupper(trim((string) $student['student_code']));
            if ($code !== '') {
                $byCode[$code] = $student;
            }
        }
        $subjects = [];
        foreach ($this->subjectsForClass($classId, $yearId) as $subject) {
            $subjects[strtoupper(trim((string) $subject['code']))] = $subject;
            $subjects[$this->fold((string) $subject['name_ar'])] = $subject;
            $subjects[$this->fold((string) $subject['name_en'])] = $subject;
        }

        $seen = [];
        $counts = ['ok' => 0, 'skipped' => 0, 'error' => 0];
        $validated = [];
        foreach ($rows as $row) {
            $data = $row['data'];
            $messages = [];
            $code = strtoupper(trim((string) ($data['Student Code'] ?? '')));
            $name = trim((string) ($data['Student Name'] ?? ''));
            $subjectRaw = trim((string) ($data['Subject'] ?? ''));
            $scoreRaw = trim((string) ($data['Score'] ?? ''));
            $notes = trim((string) ($data['Notes'] ?? ''));

            $student = null;
            if ($code === '') {
                $messages[] = 'student_code_required';
            } else {
                $student = $byCode[$code] ?? null;
                if ($student === null) {
                    $messages[] = 'student_not_in_class';
                }
            }
            if ($student !== null && $name !== '' && $this->fold($name) !== $this->fold((string) $student['full_name'])) {
                $messages[] = 'student_name_mismatch';
            }

            $subject = null;
            if ($subjectRaw === '') {
                $messages[] = 'subject_required';
            } else {
                $subject = $subjects[strtoupper($subjectRaw)] ?? $subjects[$this->fold($subjectRaw)] ?? null;
                // The template writes "CODE — Subject name". Accept that exact
                // wording as well as a bare code or name, so a workbook saved
                // without retyping the subject column validates cleanly.
                if ($subject === null) {
                    $parts = preg_split('/\s+(?:-|—|–)\s+/u', $subjectRaw, 2);
                    $code = is_array($parts) && $parts !== [] ? trim((string) $parts[0]) : '';
                    if ($code !== '') {
                        $subject = $subjects[strtoupper($code)] ?? $subjects[$this->fold($code)] ?? null;
                    }
                }
                if ($subject === null) {
                    $messages[] = 'subject_not_in_class';
                }
            }

            $score = null;
            if ($scoreRaw === '') {
                $messages[] = 'score_missing';
            } else {
                $number = $this->normalizeNumber($scoreRaw);
                if ($number === null) {
                    $messages[] = 'score_invalid';
                } elseif ($number < 0 || $number > (float) $exam['max_score']) {
                    $messages[] = 'score_out_of_range';
                } else {
                    $score = $number;
                }
            }

            $studentId = (int) ($student['student_id'] ?? 0);
            $subjectId = (int) ($subject['id'] ?? 0);
            if ($studentId > 0 && $subjectId > 0) {
                $key = $studentId . '|' . $subjectId;
                if (isset($seen[$key])) {
                    $messages[] = 'duplicate_in_file';
                }
                $seen[$key] = true;
            }
            if ($notes !== '' && mb_strlen($notes) > 255) {
                $messages[] = 'notes_too_long';
            }

            $status = 'error';
            if ($messages === []) {
                $status = 'ok';
            } elseif ($messages === ['score_missing']) {
                $status = 'skipped';
            }
            $counts[$status]++;

            $validated[] = [
                'line' => (int) $row['line'],
                'student_id' => $studentId,
                'student_name' => (string) ($student['full_name'] ?? $name),
                'subject_id' => $subjectId,
                'subject_name' => (string) ($subject['name_ar'] ?? ($subject['name_en'] ?? $subjectRaw)),
                'score' => $score,
                'notes' => $notes !== '' ? $notes : null,
                'status' => $status,
                'messages' => $messages,
            ];
        }

        return ['rows' => $validated, 'counts' => $counts];
    }

    /**
     * Write only fully validated rows. The caller re-validates the staged file
     * immediately before this call, so the roster cannot change between the
     * preview the operator saw and the marks that are actually written.
     *
     * @param list<array<string,mixed>> $validated
     * @return array{saved:int,batch:string}
     */
    public function commit(array $validated, int $examId, ?int $userId): array
    {
        $exam = $this->exams->find($examId);
        if ($exam === null) {
            throw new RuntimeException('exam_not_found');
        }
        $batch = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $saved = 0;
            foreach ($validated as $row) {
                if (($row['status'] ?? '') !== 'ok') {
                    continue;
                }
                $this->exams->upsertScore(
                    $examId,
                    (int) $row['student_id'],
                    (int) $row['subject_id'],
                    $row['score'] !== null ? (float) $row['score'] : null,
                    $row['notes'] !== null ? (string) $row['notes'] : null,
                    $userId,
                    'import',
                    $batch
                );
                $saved++;
            }
            ActivityLogger::log(
                'exam.scores_imported',
                'exams',
                $examId,
                sprintf('Imported %d marks from XLSX batch %s', $saved, $batch),
                ['batch' => $batch, 'saved' => $saved]
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['saved' => $saved, 'batch' => $batch];
    }

    /** @return list<array{id:int,code:string,name_ar:string,name_en:string}> */
    private function subjectsForClass(int $classId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT sub.id, sub.code, sub.name_ar, sub.name_en
             FROM teacher_assignments ta
             JOIN subjects sub ON sub.id = ta.subject_id
             WHERE ta.class_id = :class AND ta.academic_year_id = :year AND ta.status = 'active'
             ORDER BY sub.code ASC"
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);

        return $stmt->fetchAll();
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return $this->fold($value);
    }

    private function fold(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\s_:-]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function cellText(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        return trim((string) $value);
    }

    /** Accept both 12.5 and 12,5 (and the Arabic decimal separator). */
    private function normalizeNumber(string $raw): ?float
    {
        $value = str_replace(['٫', '٬', ' '], ['.', '', ''], trim($raw));
        if (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    /** @param list<string> $cells */
    private function writeRow(Worksheet $sheet, int $row, array $cells, bool $bold = false): void
    {
        foreach ($cells as $index => $value) {
            $sheet->setCellValue([$index + 1, $row], $value);
            if ($bold) {
                $sheet->getStyle([$index + 1, $row])->getFont()->setBold(true);
            }
            $sheet->getStyle([$index + 1, $row])->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setWrapText(true);
        }
    }
}
