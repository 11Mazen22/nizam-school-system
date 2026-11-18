<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

class BackupRepository
{
    /**
     * One page of the backup history, optionally narrowed by a term that
     * matches the file name or the notes. The term is applied in SQL so it
     * covers the entire history, not just the rows of the current page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPaginated(int $limit, int $offset, string $term = ''): array
    {
        $pdo = Database::connection();
        $where = '';
        if ($term !== '') {
            // LOWER() on both sides: MySQL folds case inside LIKE, PostgreSQL
            // does not -- the same term must find the same rows on both.
            // Two placeholders: a named parameter may be bound only once.
            $where = " WHERE (LOWER(b.filename) LIKE LOWER(:term1) OR LOWER(COALESCE(b.notes, '')) LIKE LOWER(:term2))";
        }
        $stmt = $pdo->prepare('
            SELECT b.*, u.full_name as created_by_name
            FROM backups b
            LEFT JOIN users u ON b.created_by = u.id' . $where . '
            ORDER BY b.created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        if ($term !== '') {
            $like = '%' . $term . '%';
            $stmt->bindValue(':term1', $like);
            $stmt->bindValue(':term2', $like);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Count the whole history, or only the rows a search term matches. */
    public function getTotalCount(string $term = ''): int
    {
        $pdo = Database::connection();
        if ($term === '') {
            return (int) $pdo->query('SELECT COUNT(*) FROM backups')->fetchColumn();
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM backups b
             WHERE (LOWER(b.filename) LIKE LOWER(:term1) OR LOWER(COALESCE(b.notes, '')) LIKE LOWER(:term2))"
        );
        $like = '%' . $term . '%';
        $stmt->execute(['term1' => $like, 'term2' => $like]);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM backups WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO backups (filename, file_size, type, status, created_by, notes, created_at)
            VALUES (:filename, :file_size, :type, :status, :created_by, :notes, :created_at)
        ');
        $stmt->execute([
            'filename'   => $data['filename'],
            'file_size'  => $data['file_size'] ?? null,
            'type'       => $data['type'],
            'status'     => $data['status'],
            'created_by' => $data['created_by'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }
}
