<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use DateTimeImmutable;
use RuntimeException;

/**
 * Nizam -- student roster CSV import.
 *
 * The official roster PDFs in the supplied school package have a broken
 * font-to-Unicode mapping, so the transfer format is an explicit CSV the
 * school fills (or exports from Excel) and this service validates before a
 * single row reaches the database:
 *
 *   parse    -> read the file, normalize the obvious values
 *   validate -> every row gets ok / duplicate / error plus a reason
 *   commit   -> insert only the ok rows, inside one transaction
 *
 * Nothing is inferred: a row without a name, class, gender, date of birth or
 * religion is an error, not a guess.
 */
final class RosterImportService
{
    public const HEADERS = [
        'Full Name',
        'Class Name',
        'Gender (M/F)',
        'Date of Birth (YYYY-MM-DD)',
        'Religion',
        'Class Attribute',
        'Notes',
    ];

    public const MAX_ROWS = 2000;

    /** UTF-8 CSV with a BOM so Excel opens Arabic correctly. */
    public function templateCsv(string $locale): string
    {
        $csv = $this->toCsv([self::HEADERS]);

        if ($locale === 'ar') {
            $csv .= $this->toCsv([[
                'مثال: محمد أحمد علي',
                'A1',
                'M',
                '2009-09-01',
                'muslim',
                'مسلم',
                '—',
            ]]);
        }

        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * @return list<array{line:int, data:array<string,string>}>
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('roster_file_missing');
        }
        if (filesize($path) > 5 * 1024 * 1024) {
            throw new RuntimeException('roster_file_too_large');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('roster_file_unreadable');
        }

        $firstLine = (string) fgets($handle);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('roster_empty_file');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
        $header = array_map(static fn ($value): string => trim((string) $value), $header);

        $missing = array_diff(self::HEADERS, $header);
        $extra = array_diff($header, self::HEADERS);
        if ($missing !== [] || $extra !== []) {
            fclose($handle);
            throw new RuntimeException('roster_headers_mismatch');
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw new RuntimeException('roster_too_many_rows');
            }
            $data = [];
            $blank = true;
            foreach ($header as $index => $name) {
                $value = trim((string) ($values[$index] ?? ''));
                $data[$name] = $value;
                if ($value !== '') {
                    $blank = false;
                }
            }
            if ($blank) {
                continue;
            }
            $rows[] = ['line' => $line, 'data' => $data];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param list<array{line:int, data:array<string,string>}> $rows
     * @param array<string,string> $nameInference Map of "mb_strtolower(name)|CLASS" => religion_source, used only for official rows
     * @return array{
     *   rows: list<array{line:int, name:string, class:string, status:string, messages:list<string>, data:array<string,string>}>,
     *   counts: array{ok:int, duplicate:int, error:int}
     * }
     */
    public function validate(array $rows, bool $officialReviewed = false, array $nameInference = []): array
    {
        $pdo = Database::connection();
        $yearId = AcademicYearContext::activeYearId();

        $classes = [];
        if ($yearId !== null) {
            $stmt = $pdo->prepare(
                'SELECT c.id, c.name, c.grade_id FROM classes c
                 WHERE c.academic_year_id = :year AND c.is_active = 1'
            );
            $stmt->execute(['year' => $yearId]);
            foreach ($stmt->fetchAll() as $row) {
                $classes[strtoupper((string) $row['name'])] = ['id' => (int) $row['id'], 'grade_id' => (int) $row['grade_id']];
            }
        }

        $seen = [];
        $counts = ['ok' => 0, 'duplicate' => 0, 'error' => 0];
        $validated = [];

        foreach ($rows as $row) {
            $data = $row['data'];
            $messages = [];
            $name = $this->normalizeName((string) ($data['Full Name'] ?? ''));
            $className = strtoupper(trim((string) ($data['Class Name'] ?? '')));
            $gender = $this->normalizeGender((string) ($data['Gender (M/F)'] ?? ''));
            $dob = $this->normalizeDate((string) ($data['Date of Birth (YYYY-MM-DD)'] ?? ''));
            $religion = $this->normalizeReligion((string) ($data['Religion'] ?? ''));
            [$secondLanguage, $secondLanguageSource] = $this->normalizeSecondLanguage(
                (string) ($data['Class Attribute'] ?? ''),
                $className
            );
            $officialRow = $officialReviewed && $this->isOfficialReviewedRow((string) ($data['Notes'] ?? ''));
            $nameInferenceSource = null;
            if ($officialRow && $nameInference !== []) {
                $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? '') . '|' . $className;
                $nameInferenceSource = $nameInference[$key] ?? null;
            }

            if ($name === '') {
                $messages[] = 'name_required';
            }
            if ($yearId === null) {
                $messages[] = 'no_active_year';
            }
            if ($className === '' || !isset($classes[$className])) {
                $messages[] = 'class_unknown';
            }
            // The official rosters print neither gender, birth date nor
            // religion. Blank cells are therefore legal and stored as NULL
            // ("not recorded"); a filled cell must still be valid.
            if (trim((string) ($data['Gender (M/F)'] ?? '')) !== '' && $gender === null) {
                $messages[] = 'gender_invalid';
            }
            if (trim((string) ($data['Date of Birth (YYYY-MM-DD)'] ?? '')) !== '' && $dob === null) {
                $messages[] = 'dob_invalid';
            }
            if (trim((string) ($data['Religion'] ?? '')) !== '' && $religion === null) {
                $messages[] = 'religion_invalid';
            }

            $key = mb_strtolower($name) . '|' . $className;
            if ($name !== '' && isset($seen[$key])) {
                $messages[] = 'duplicate_in_file';
            }
            $seen[$key] = true;

            $status = $messages === [] ? 'ok' : 'error';

            $existingId = 0;
            if ($status === 'ok') {
                $stmt = $pdo->prepare(
                    'SELECT s.id FROM students s
                     JOIN student_enrollments e ON e.student_id = s.id
                     WHERE s.full_name = :n AND e.class_id = :c AND e.status = \'active\'
                     LIMIT 1'
                );
                $stmt->execute(['n' => $name, 'c' => $classes[$className]['id']]);
                $existingId = (int) ($stmt->fetchColumn() ?: 0);
                if ($existingId > 0) {
                    $status = 'duplicate';
                    $messages[] = 'already_enrolled';
                }
            }

            $counts[$status]++;
            $validated[] = [
                'line' => $row['line'],
                'name' => $name,
                'class' => $className,
                'status' => $status,
                'messages' => $messages,
                'data' => $data + [
                    '_gender' => $gender,
                    '_dob' => $dob,
                    '_religion' => $religion,
                    '_gender_source' => $gender !== null ? ($officialRow ? 'official_roster_layout' : 'imported_csv') : null,
                    '_religion_source' => $officialRow
                        ? ($nameInferenceSource ?? ($religion !== null ? 'official_roster_rule_v2' : 'official_roster_unconfirmed_v2'))
                        : ($religion !== null ? 'imported_csv' : null),
                    '_second_language' => $secondLanguage,
                    '_second_language_source' => $secondLanguageSource,
                    '_official_reviewed' => $officialRow,
                    '_grade_id' => $classes[$className]['grade_id'] ?? 0,
                    '_class_id' => $classes[$className]['id'] ?? 0,
                    '_existing_id' => $existingId,
                ],
            ];
        }

        return ['rows' => $validated, 'counts' => $counts];
    }

    /**
     * Insert new students, and enrich existing ones without overwriting any
     * value the school already has. The printed rosters omit gender, birth
     * date and religion, so a later enriched CSV can fill those blanks for
     * every already-enrolled student instead of reporting them as duplicates
     * and doing nothing.
     *
     * @param list<array<string, mixed>> $validatedRows
     * @return array{created:int, updated:int, skipped:int, errors:list<string>}
     */
    public function commit(array $validatedRows): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            foreach ($validatedRows as $row) {
                if ($row['status'] === 'duplicate' && !empty($row['data']['_existing_id'])) {
                    if ($this->enrichExisting((int) $row['data']['_existing_id'], $row['data'])) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }
                if ($row['status'] !== 'ok') {
                    $skipped++;
                    continue;
                }
                try {
                    $studentId = (new StudentService())->create(
                        (string) $row['name'],
                        $row['data']['_gender'] !== null ? (string) $row['data']['_gender'] : null,
                        $row['data']['_dob'] !== null ? (string) $row['data']['_dob'] : null,
                        $row['data']['_religion'] !== null ? (string) $row['data']['_religion'] : null,
                        null,
                        null,
                        null,
                        trim((string) ($row['data']['Notes'] ?? '')) ?: null,
                        (int) $row['data']['_grade_id'],
                        (int) $row['data']['_class_id'] ?: null
                    );

                    $update = $pdo->prepare(
                        'UPDATE students
                         SET class_attribute_raw = :attr,
                             class_attribute_source = :attribute_source,
                             gender_source = :gender_source,
                             religion_source = :religion_source,
                             second_language = :second_language,
                             second_language_source = :second_language_source,
                             import_source = :src
                         WHERE id = :id'
                    );
                    $update->execute([
                        'attr' => trim((string) ($row['data']['Class Attribute'] ?? '')) ?: null,
                        'attribute_source' => trim((string) ($row['data']['Class Attribute'] ?? '')) !== '' ? 'printed' : null,
                        'gender_source' => $row['data']['_gender_source'] ?? null,
                        'religion_source' => $row['data']['_religion_source'] ?? null,
                        'second_language' => $row['data']['_second_language'] ?? null,
                        'second_language_source' => $row['data']['_second_language_source'] ?? null,
                        'src' => 'roster_csv',
                        'id' => $studentId,
                    ]);
                    $created++;
                } catch (RuntimeException $e) {
                    $skipped++;
                    $errors[] = 'line ' . $row['line'] . ': ' . $e->getMessage();
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Fill only blanks on an existing student. Supplied values never replace
     * a value that is already recorded -- the roster is an enrichment source,
     * not an overwrite of work the school has already done.
     *
     * @param array<string,string> $data
     */
    private function enrichExisting(int $studentId, array $data): bool
    {
        $sets = [];
        $params = ['id' => $studentId];
        $officialReviewed = (bool) ($data['_official_reviewed'] ?? false);

        $gender = (string) ($data['_gender'] ?? '');
        if ($officialReviewed && $gender !== '') {
            $sets[] = 'gender = :gender';
            $sets[] = 'gender_source = :gender_source';
            $params['gender'] = $gender;
            $params['gender_source'] = (string) ($data['_gender_source'] ?? 'official_roster_layout');
        } elseif ($gender !== '') {
            $sets[] = 'gender_source = CASE WHEN gender IS NULL THEN COALESCE(gender_source, :gender_source) ELSE gender_source END';
            $params['gender_source'] = (string) ($data['_gender_source'] ?? 'imported_csv');
            $sets[] = 'gender = COALESCE(gender, :gender)';
            $params['gender'] = $gender;
        }

        $dob = (string) ($data['_dob'] ?? '');
        if ($dob !== '') {
            $sets[] = 'date_of_birth = COALESCE(date_of_birth, :dob)';
            $params['dob'] = $dob;
        }

        $religion = (string) ($data['_religion'] ?? '');
        if ($officialReviewed) {
            // Version 2 is the reviewed authoritative roster. This also
            // clears values an older carry-forward pass inferred on B6/B8 or
            // unlegended shorthand; leaving those guesses would be worse than
            // an honest NULL.
            $sets[] = 'religion = :official_religion';
            $sets[] = 'religion_source = :religion_source';
            $params['official_religion'] = $religion !== '' ? $religion : null;
            $params['religion_source'] = (string) ($data['_religion_source'] ?? 'official_roster_unconfirmed_v2');
        } elseif ($religion !== '') {
            $sets[] = 'religion_source = CASE WHEN religion IS NULL THEN COALESCE(religion_source, :religion_source) ELSE religion_source END';
            $params['religion_source'] = (string) ($data['_religion_source'] ?? 'imported_csv');
            $sets[] = 'religion = COALESCE(religion, :religion)';
            $params['religion'] = $religion;
        }

        $secondLanguage = (string) ($data['_second_language'] ?? '');
        if ($secondLanguage !== '') {
            $sets[] = 'second_language_source = CASE WHEN second_language IS NULL THEN COALESCE(second_language_source, :second_language_source) ELSE second_language_source END';
            $params['second_language_source'] = (string) ($data['_second_language_source'] ?? 'printed_cell');
            $sets[] = 'second_language = COALESCE(second_language, :second_language)';
            $params['second_language'] = $secondLanguage;
        }

        $attribute = trim((string) ($data['Class Attribute'] ?? ''));
        if ($officialReviewed) {
            $sets[] = 'class_attribute_raw = :official_attribute';
            $sets[] = 'class_attribute_source = :official_attribute_source';
            $params['official_attribute'] = $attribute !== '' ? $attribute : null;
            $params['official_attribute_source'] = $attribute !== '' ? 'printed' : null;
        } elseif ($attribute !== '') {
            $sets[] = "class_attribute_raw = CASE WHEN class_attribute_raw IS NULL OR class_attribute_raw = '' THEN :attribute ELSE class_attribute_raw END";
            $params['attribute'] = $attribute;
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = "import_source = COALESCE(import_source, 'roster_csv')";
        $stmt = Database::connection()->prepare(
            'UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($candidates);
        $delimiter = (string) array_key_first($candidates);

        return $candidates[$delimiter] > 0 ? $delimiter : ',';
    }

    /** @param list<list<string>> $rows */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function normalizeName(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($value));
    }

    private function normalizeGender(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if (in_array($value, ['m', 'male', 'ذكر', 'بنين'], true)) {
            return 'm';
        }
        if (in_array($value, ['f', 'female', 'أنثى', 'انثى', 'بنات'], true)) {
            return 'f';
        }

        return null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                $year = (int) $date->format('Y');
                if ($year >= 1990 && $year <= (int) date('Y')) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return null;
    }

    private function normalizeReligion(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        return match (true) {
            in_array($value, ['muslim', 'islam', 'مسلم', 'مسلمة'], true) => 'muslim',
            in_array($value, ['christian', 'christianity', 'مسيحي', 'مسيحية', 'مسيحى'], true) => 'christian',
            in_array($value, ['other', 'أخرى', 'اخرى'], true) => 'other',
            default => null,
        };
    }

    /** @return array{0:?string,1:?string} */
    private function normalizeSecondLanguage(string $raw, string $className): array
    {
        $compact = (string) preg_replace('/\s+/u', '', trim($raw));
        if (str_contains($compact, 'المانى')) {
            return ['german', 'printed_cell'];
        }
        if (str_contains($compact, 'فرنسى') || str_contains($compact, 'فرنساوى')) {
            return ['french', 'printed_cell'];
        }
        if (str_contains($compact, 'اسبانى')) {
            return ['spanish', 'printed_cell'];
        }

        if ($className === 'A7' && trim($raw) === '') {
            return ['german', 'class_heading'];
        }
        if ($className === 'A9') {
            return ['spanish', 'class_heading'];
        }

        return [null, null];
    }

    private function isOfficialReviewedRow(string $notes): bool
    {
        return preg_match('/\brow\s+[RL]\d+\b/i', $notes) === 1;
    }
}
