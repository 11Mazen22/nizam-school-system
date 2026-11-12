<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Nizam -- universal academic configuration (§6.3).
 *
 * Migration 019 introduced `educational_stages`, `academic_terms`,
 * `grading_scales` and `grading_scale_bands`, but nothing ever read or wrote
 * them: the tables existed while every screen still assumed a single fixed
 * 1..N grade ladder, a two-term year and a 100-point score. This repository
 * is the one place that owns those three configuration surfaces, so the rest
 * of the platform can ask "what are this school's stages/terms/grading
 * bands?" instead of assuming.
 *
 * Dialect discipline: every statement below is valid on both MySQL/MariaDB
 * and PostgreSQL. Two things are handled explicitly rather than assumed --
 *   - BOOLEAN columns: literal TRUE/FALSE is accepted by MySQL (as 1/0) and
 *     by PostgreSQL, whereas binding a PHP int to a PostgreSQL boolean column
 *     fails, so booleans are never bound as parameters.
 *   - generated ids: PostgreSQL needs RETURNING, MySQL needs lastInsertId().
 */
final class AcademicConfigRepository
{
    // ----------------------------------------------------------- Stages

    /** @return array<int, array<string, mixed>> ordered the way the school wants its stages presented */
    public function stages(): array
    {
        return Database::connection()->query(
            'SELECT s.*, (SELECT COUNT(*) FROM grades g WHERE g.stage_id = s.id) AS grade_count
             FROM educational_stages s
             ORDER BY s.sort_order ASC, s.id ASC'
        )->fetchAll();
    }

    public function findStage(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, (SELECT COUNT(*) FROM grades g WHERE g.stage_id = s.id) AS grade_count
             FROM educational_stages s WHERE s.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function stageCodeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM educational_stages WHERE code = :code';
        $params = ['code' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function createStage(string $code, string $nameEn, string $nameAr, int $sortOrder): int
    {
        return $this->insert(
            'INSERT INTO educational_stages (code, name_en, name_ar, sort_order, is_active)
             VALUES (:code, :en, :ar, :sort, TRUE)',
            ['code' => $code, 'en' => $nameEn, 'ar' => $nameAr, 'sort' => $sortOrder]
        );
    }

    public function updateStage(int $id, string $code, string $nameEn, string $nameAr, int $sortOrder, bool $active): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE educational_stages
             SET code = :code, name_en = :en, name_ar = :ar, sort_order = :sort,
                 is_active = ' . ($active ? 'TRUE' : 'FALSE') . '
             WHERE id = :id'
        );
        $stmt->execute(['code' => $code, 'en' => $nameEn, 'ar' => $nameAr, 'sort' => $sortOrder, 'id' => $id]);
    }

    /**
     * Hard delete is deliberately limited to a stage no grade points at --
     * a stage with grades in it is part of the school's live academic
     * structure, and silently orphaning those grades (the FK is ON DELETE
     * SET NULL) would quietly erase the association an administrator just
     * made. The service layer checks grade_count first; the FK stays as the
     * final backstop.
     */
    public function deleteStage(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM educational_stages WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<int,int|null> $map grade_id => stage_id|null */
    public function assignGradeStages(array $map): void
    {
        if ($map === []) {
            return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE grades SET stage_id = :stage WHERE id = :id');
        $pdo->beginTransaction();
        try {
            foreach ($map as $gradeId => $stageId) {
                $stmt->execute(['stage' => $stageId, 'id' => $gradeId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------ Terms

    /** @return array<int, array<string, mixed>> */
    public function terms(?int $yearId = null): array
    {
        $sql = 'SELECT * FROM academic_terms';
        $params = [];
        if ($yearId !== null) {
            $sql .= ' WHERE academic_year_id = :year';
            $params['year'] = $yearId;
        }
        $sql .= ' ORDER BY academic_year_id DESC, term_number ASC, start_date ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findTerm(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM academic_terms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** A school's term count is its own decision -- nothing here assumes two. */
    public function termNumberExists(int $yearId, int $termNumber, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM academic_terms WHERE academic_year_id = :year AND term_number = :num';
        $params = ['year' => $yearId, 'num' => $termNumber];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Overlapping terms are refused because attendance, assessment and
     * report-card windows all resolve "which term is this date in?" -- two
     * terms covering the same day make that question unanswerable.
     */
    public function termsOverlap(int $yearId, string $start, string $end, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM academic_terms
                WHERE academic_year_id = :year
                  AND start_date <= :end
                  AND end_date >= :start';
        $params = ['year' => $yearId, 'start' => $start, 'end' => $end];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function createTerm(int $yearId, string $nameEn, string $nameAr, int $termNumber, string $start, string $end): int
    {
        return $this->insert(
            'INSERT INTO academic_terms
                (academic_year_id, name_en, name_ar, term_number, start_date, end_date, is_active, created_at)
             VALUES (:year, :en, :ar, :num, :start, :end, FALSE, NOW())',
            ['year' => $yearId, 'en' => $nameEn, 'ar' => $nameAr, 'num' => $termNumber, 'start' => $start, 'end' => $end]
        );
    }

    public function updateTerm(int $id, string $nameEn, string $nameAr, int $termNumber, string $start, string $end, bool $active): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE academic_terms
             SET name_en = :en, name_ar = :ar, term_number = :num,
                 start_date = :start, end_date = :end,
                 is_active = ' . ($active ? 'TRUE' : 'FALSE') . '
             WHERE id = :id'
        );
        $stmt->execute(['en' => $nameEn, 'ar' => $nameAr, 'num' => $termNumber, 'start' => $start, 'end' => $end, 'id' => $id]);
    }

    /** Exactly one current term, mirroring the single-active-academic-year rule. */
    public function clearActiveTerms(int $yearId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE academic_terms SET is_active = FALSE WHERE academic_year_id = :year'
        );
        $stmt->execute(['year' => $yearId]);
    }

    public function setTermActive(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE academic_terms SET is_active = TRUE WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteTerm(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM academic_terms WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    // ---------------------------------------------------------- Grading

    /** @return array<int, array<string, mixed>> */
    public function scales(): array
    {
        return Database::connection()->query(
            'SELECT s.*, (SELECT COUNT(*) FROM grading_scale_bands b WHERE b.grading_scale_id = s.id) AS band_count
             FROM grading_scales s
             ORDER BY s.is_default DESC, s.id ASC'
        )->fetchAll();
    }

    public function findScale(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, (SELECT COUNT(*) FROM grading_scale_bands b WHERE b.grading_scale_id = s.id) AS band_count
             FROM grading_scales s WHERE s.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function defaultScale(): ?array
    {
        $stmt = Database::connection()->query('SELECT * FROM grading_scales WHERE is_default = TRUE ORDER BY id LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> highest band first, matching how a report card reads */
    public function bands(int $scaleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM grading_scale_bands WHERE grading_scale_id = :id ORDER BY min_score DESC, id ASC'
        );
        $stmt->execute(['id' => $scaleId]);
        return $stmt->fetchAll();
    }

    public function createScale(string $nameEn, string $nameAr, string $scaleType, string $description, bool $isDefault): int
    {
        return $this->insert(
            'INSERT INTO grading_scales (name_en, name_ar, scale_type, is_default, description, created_at)
             VALUES (:en, :ar, :type, ' . ($isDefault ? 'TRUE' : 'FALSE') . ', :description, NOW())',
            ['en' => $nameEn, 'ar' => $nameAr, 'type' => $scaleType, 'description' => $description]
        );
    }

    public function updateScale(int $id, string $nameEn, string $nameAr, string $scaleType, string $description): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE grading_scales SET name_en = :en, name_ar = :ar, scale_type = :type, description = :description
             WHERE id = :id'
        );
        $stmt->execute(['en' => $nameEn, 'ar' => $nameAr, 'type' => $scaleType, 'description' => $description, 'id' => $id]);
    }

    public function setDefaultScale(int $id): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE grading_scales SET is_default = FALSE WHERE is_default = TRUE');
            $stmt = $pdo->prepare('UPDATE grading_scales SET is_default = TRUE WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteScale(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM grading_scales WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Replace-all is intentional: a grade band set is only meaningful as a
     * complete, non-overlapping partition of the score range, so editing one
     * band in isolation is not a state this domain wants to allow.
     *
     * @param array<int, array{label:string,min:string|float,max:string|float,gpa:?string,passing:bool,desc_en:?string,desc_ar:?string}> $bands
     */
    public function replaceBands(int $scaleId, array $bands): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM grading_scale_bands WHERE grading_scale_id = :id');
            $delete->execute(['id' => $scaleId]);

            // is_passing is deliberately not part of the INSERT: binding a
            // PHP bool/int to a PostgreSQL BOOLEAN fails, so the flag is set
            // by a literal-TRUE/FALSE UPDATE below. The scale+minimum pair is
            // unique (bands may not overlap), which is what identifies the row.
            $insert = $pdo->prepare(
                'INSERT INTO grading_scale_bands
                    (grading_scale_id, band_label, min_score, max_score, gpa_point, description_en, description_ar)
                 VALUES (:scale, :label, :min, :max, :gpa, :desc_en, :desc_ar)'
            );

            foreach ($bands as $band) {
                $insert->execute([
                    'scale'   => $scaleId,
                    'label'   => $band['label'],
                    'min'     => $band['min'],
                    'max'     => $band['max'],
                    'gpa'     => $band['gpa'] === null || $band['gpa'] === '' ? null : $band['gpa'],
                    'desc_en' => $band['desc_en'] ?? null,
                    'desc_ar' => $band['desc_ar'] ?? null,
                ]);
                // Rebind the literal pass/fail flag per band. Done by
                // re-preparing a tiny statement rather than binding a
                // boolean, which PostgreSQL rejects when given a PHP int.
                $flag = $pdo->prepare(
                    'UPDATE grading_scale_bands SET is_passing = ' . (!empty($band['passing']) ? 'TRUE' : 'FALSE')
                    . ' WHERE grading_scale_id = :scale AND band_label = :label AND min_score = :min'
                );
                $flag->execute(['scale' => $scaleId, 'label' => $band['label'], 'min' => $band['min']]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ---------------------------------------------------------- Helpers

    /** @param array<string,mixed> $params */
    private function insert(string $sql, array $params): int
    {
        $pdo = Database::connection();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $stmt = $pdo->prepare($sql . ' RETURNING id');
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }
}
