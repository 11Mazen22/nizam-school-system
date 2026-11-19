<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Services\IdPattern;

final class StudentRepository
{
    /** Maximum rows per page — O-25: cap is enforced here server-side; callers may not exceed this. */
    public const MAX_PER_PAGE = 20;

    /** @return array<int, array<string, mixed>> */
    public function all(string $status = 'active'): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM students WHERE status = :status ORDER BY full_name ASC');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    /**
     * Return one page of students. The $perPage value is hard-capped at MAX_PER_PAGE
     * server-side regardless of what the caller supplies — this is the O-25 enforcement
     * point. Crafted values such as 999999 are silently reduced to MAX_PER_PAGE.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paginate(int $page, int $perPage, string $status = 'active', string $term = '', int $academicYearId = 0, int $gradeId = 0, int $classId = 0, string $missing = '', string $religion = ''): array
    {
        // O-25: cap regardless of caller-supplied value.
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $pdo = Database::connection();
        
        $sql = 'SELECT s.*, c.name as class_name, g.name_en as grade_name_en, g.name_ar as grade_name_ar
                FROM students s
                LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = :year_id
                LEFT JOIN classes c ON c.id = e.class_id
                LEFT JOIN grades g ON g.id = c.grade_id
                WHERE s.status = :status';

        if ($term !== '') {
            // LOWER() on both sides: MySQL's collation folds case inside LIKE,
            // PostgreSQL's does not -- the same term must find the same rows.
            $sql .= ' AND (LOWER(s.full_name) LIKE LOWER(:term1) OR LOWER(s.student_code) LIKE LOWER(:term2))';
        }
        if ($gradeId > 0) {
            $sql .= ' AND c.grade_id = :grade_id';
        }
        if ($classId > 0) {
            $sql .= ' AND e.class_id = :class_id';
        }
        if ($religion !== '') {
            $sql .= ' AND s.religion = :religion';
        }
        $sql .= $this->missingCondition($missing);
        
        $sql .= ' ORDER BY s.full_name ASC LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':year_id', $academicYearId, \PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        if ($term !== '') {
            $like = '%' . $term . '%';
            $stmt->bindValue(':term1', $like);
            $stmt->bindValue(':term2', $like);
        }
        if ($gradeId > 0) {
            $stmt->bindValue(':grade_id', $gradeId, \PDO::PARAM_INT);
        }
        if ($classId > 0) {
            $stmt->bindValue(':class_id', $classId, \PDO::PARAM_INT);
        }
        if ($religion !== '') {
            $stmt->bindValue(':religion', $religion);
        }
        $stmt->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Return the total count of students matching the same status + search filter used
     * by paginate() — ensures pagination math stays consistent with the displayed rows.
     */
    public function countFiltered(string $status = 'active', string $term = '', int $academicYearId = 0, int $gradeId = 0, int $classId = 0, string $missing = '', string $religion = ''): int
    {
        $pdo = Database::connection();
        
        $sql = 'SELECT COUNT(s.id) 
                FROM students s 
                LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = :year_id
                LEFT JOIN classes c ON c.id = e.class_id
                WHERE s.status = :status';
                
        if ($term !== '') {
            // Same LOWER() folding as paginate(): the search must match the
            // same rows on both engines.
            $sql .= ' AND (LOWER(s.full_name) LIKE LOWER(:term1) OR LOWER(s.student_code) LIKE LOWER(:term2))';
        }
        if ($gradeId > 0) {
            $sql .= ' AND c.grade_id = :grade_id';
        }
                if ($classId > 0) {
            $sql .= ' AND e.class_id = :class_id';
        }
        if ($religion !== '') {
            $sql .= ' AND s.religion = :religion';
        }
        $sql .= $this->missingCondition($missing);
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':year_id', $academicYearId, \PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        if ($term !== '') {
            $like = '%' . $term . '%';
            $stmt->bindValue(':term1', $like);
            $stmt->bindValue(':term2', $like);
        }
        if ($gradeId > 0) {
            $stmt->bindValue(':grade_id', $gradeId, \PDO::PARAM_INT);
        }
        if ($classId > 0) {
            $stmt->bindValue(':class_id', $classId, \PDO::PARAM_INT);
        }
        if ($religion !== '') {
            $stmt->bindValue(':religion', $religion);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function findActiveByCode(string $studentCode): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.id, s.student_code, s.full_name
             FROM students s
             JOIN student_enrollments e ON e.student_id = s.id
             WHERE s.student_code = :code AND s.status = 'active' AND e.status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['code' => $studentCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Every active student in the year with their class, for the enrichment
     * template. The template gives the school one file with every active name and
     * blank columns to fill, instead of editing profiles one by one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allActiveWithClass(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.student_code, s.full_name, s.gender, s.date_of_birth, s.religion,
                    s.class_attribute_raw, c.name AS class_name
             FROM students s
             JOIN student_enrollments e ON e.student_id = s.id
             JOIN classes c ON c.id = e.class_id
             WHERE s.status = 'active' AND e.status = 'active' AND e.academic_year_id = :year
             ORDER BY c.name ASC, s.full_name ASC"
        );
        $stmt->execute(['year' => $academicYearId]);

        return $stmt->fetchAll();
    }

    /**
     * Every active student in the year with the columns an official roster
     * prints -- the whole school in one query, not a paginated screen slice.
     * A document must never silently contain only the first page of students
     * that the list screen happened to be showing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rosterForYear(
        int $academicYearId,
        int $gradeId = 0,
        int $classId = 0,
        string $missing = '',
        string $religion = '',
        string $term = ''
    ): array {
        $sql = "SELECT s.id, s.student_code, s.full_name, s.gender, s.date_of_birth, s.religion,
                       s.phone, s.guardian_phone, s.address, s.status,
                       c.name AS class_name, c.grade_id,
                       g.name_en AS grade_name_en, g.name_ar AS grade_name_ar, g.sort_order
                FROM students s
                JOIN student_enrollments e ON e.student_id = s.id
                     AND e.academic_year_id = :year AND e.status = 'active'
                LEFT JOIN classes c ON c.id = e.class_id
                LEFT JOIN grades g ON g.id = c.grade_id
                WHERE s.status = 'active'";

        if ($gradeId > 0) {
            $sql .= ' AND c.grade_id = :grade_id';
        }
        if ($classId > 0) {
            $sql .= ' AND e.class_id = :class_id';
        }
        if ($religion !== '') {
            $sql .= ' AND s.religion = :religion';
        }
        if ($term !== '') {
            $sql .= ' AND (LOWER(s.full_name) LIKE LOWER(:term1) OR LOWER(s.student_code) LIKE LOWER(:term2))';
        }
        $sql .= $this->missingCondition($missing);
        $sql .= ' ORDER BY COALESCE(g.sort_order, 999) ASC, c.name ASC, s.full_name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':year', $academicYearId, \PDO::PARAM_INT);
        if ($gradeId > 0) {
            $stmt->bindValue(':grade_id', $gradeId, \PDO::PARAM_INT);
        }
        if ($classId > 0) {
            $stmt->bindValue(':class_id', $classId, \PDO::PARAM_INT);
        }
        if ($religion !== '') {
            $stmt->bindValue(':religion', $religion);
        }
        if ($term !== '') {
            $like = '%' . $term . '%';
            $stmt->bindValue(':term1', $like);
            $stmt->bindValue(':term2', $like);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * The completeness picture for the active year: how many students still
     * lack the fields the printed rosters never carried, and how many have
     * no linked guardian at all. Shown on the list so the school can work
     * through all of them instead of discovering gaps one profile at a time.
     *
     * @return array{total:int,no_gender:int,no_dob:int,no_religion:int,no_personal:int,no_guardian:int}
     */
    public function dataQuality(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN s.gender IS NULL THEN 1 ELSE 0 END) AS no_gender,
                    SUM(CASE WHEN s.date_of_birth IS NULL THEN 1 ELSE 0 END) AS no_dob,
                    SUM(CASE WHEN s.religion IS NULL THEN 1 ELSE 0 END) AS no_religion,
                    SUM(CASE WHEN s.gender IS NULL OR s.date_of_birth IS NULL OR s.religion IS NULL THEN 1 ELSE 0 END) AS no_personal,
                    SUM(CASE WHEN NOT EXISTS (
                        SELECT 1 FROM student_guardians sg WHERE sg.student_id = s.id
                    ) THEN 1 ELSE 0 END) AS no_guardian
             FROM students s
             JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = :year
             WHERE s.status = 'active' AND e.status = 'active'"
        );
        $stmt->execute(['year' => $academicYearId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'no_gender' => (int) ($row['no_gender'] ?? 0),
            'no_dob' => (int) ($row['no_dob'] ?? 0),
            'no_religion' => (int) ($row['no_religion'] ?? 0),
            'no_personal' => (int) ($row['no_personal'] ?? 0),
            'no_guardian' => (int) ($row['no_guardian'] ?? 0),
        ];
    }

    private function missingCondition(string $missing): string
    {
        return match ($missing) {
            'personal' => ' AND (s.gender IS NULL OR s.date_of_birth IS NULL OR s.religion IS NULL)',
            'guardian' => ' AND NOT EXISTS (SELECT 1 FROM student_guardians sg WHERE sg.student_id = s.id)',
            'contact' => ' AND (s.phone IS NULL OR s.guardian_phone IS NULL OR s.address IS NULL)',
            default => '',
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $term, string $status = 'active'): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM students WHERE status = :status
             AND (LOWER(full_name) LIKE LOWER(:term1) OR LOWER(student_code) LIKE LOWER(:term2))
             ORDER BY full_name ASC'
        );
        $like = '%' . $term . '%';
        $stmt->execute(['status' => $status, 'term1' => $like, 'term2' => $like]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM students WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{id:int} §O-7: insert first (placeholder code), then UPDATE the code from the row's own new id -- never a precomputed MAX+1. */
    public function create(
        string $fullName,
        ?string $gender,
        ?string $dateOfBirth,
        ?string $religion,
        ?string $phone,
        ?string $guardianPhone,
        ?string $address,
        ?string $notes,
        string $codePattern,
        string $codeYear
    ): int {
        $pdo = Database::connection();
        // Database::insert() returns the generated id on both engines:
        // PostgreSQL needs RETURNING (PDO::lastInsertId() without a sequence
        // name is not portable there), and the rendered student code is
        // built from this id.
        $id = Database::insert(
            $pdo,
            'INSERT INTO students
                (student_code, full_name, gender, gender_source, date_of_birth, religion, religion_source,
                 phone, guardian_phone, address, notes, status)
             VALUES
                (\'\', :name, :gender, :gender_source, :dob, :religion, :religion_source,
                 :phone, :guardian, :address, :notes, \'active\')',
            [
                'name' => $fullName, 'gender' => $gender, 'dob' => $dateOfBirth, 'religion' => $religion,
                'gender_source' => $gender !== null ? 'manual' : null,
                'religion_source' => $religion !== null ? 'manual' : null,
                'phone' => $phone, 'guardian' => $guardianPhone, 'address' => $address, 'notes' => $notes,
            ]
        );

        $code = IdPattern::render($codePattern, $id, $codeYear);
        $update = $pdo->prepare('UPDATE students SET student_code = :code WHERE id = :id');
        $update->execute(['code' => $code, 'id' => $id]);

        return $id;
    }

    public function update(
        int $id,
        string $fullName,
        string $gender,
        string $dateOfBirth,
        string $religion,
        ?string $phone,
        ?string $guardianPhone,
        ?string $address,
        ?string $notes
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE students
             SET full_name = :name,
                 gender = :gender,
                 gender_source = \'manual\',
                 date_of_birth = :dob,
                 religion = :religion,
                 religion_source = \'manual\',
                 phone = :phone,
                 guardian_phone = :guardian,
                 address = :address,
                 notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $fullName, 'gender' => $gender, 'dob' => $dateOfBirth, 'religion' => $religion,
            'phone' => $phone, 'guardian' => $guardianPhone, 'address' => $address, 'notes' => $notes, 'id' => $id,
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE students SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Decision #11 photo storage -- a separate, orthogonal write from update(), matching setStatus()'s own single-column pattern. */
    public function updatePhoto(int $id, ?string $photoPath): void
    {
        $stmt = Database::connection()->prepare('UPDATE students SET photo_path = :photo WHERE id = :id');
        $stmt->execute(['photo' => $photoPath, 'id' => $id]);
    }
}
