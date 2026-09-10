<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

class BackupRepository
{
    public function getPaginated(int $limit, int $offset): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT b.*, u.full_name as created_by_name
            FROM backups b
            LEFT JOIN users u ON b.created_by = u.id
            ORDER BY b.created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalCount(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM backups');
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
