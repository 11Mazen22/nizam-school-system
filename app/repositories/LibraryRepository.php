<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class LibraryRepository
{
    public function allBooks(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM library_books ORDER BY title ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function findBook(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM library_books WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createBook(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO library_books (
                isbn, title, author, category, total_copies, available_copies, shelf_location, created_at
             ) VALUES (
                :isbn, :title, :author, :cat, :total, :avail, :shelf, :now
             )'
        );
        $total = (int) ($data['total_copies'] ?? 1);
        $stmt->execute([
            'isbn'   => $data['isbn'] ?? null,
            'title'  => $data['title'],
            'author' => $data['author'],
            'cat'    => $data['category'] ?? 'General',
            'total'  => $total,
            'avail'  => $total,
            'shelf'  => $data['shelf_location'] ?? null,
            'now'    => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function allBorrows(?string $status = null): array
    {
        $sql = 'SELECT b.*, bk.title AS book_title, bk.author AS book_author,
                       s.full_name AS student_name, s.student_code,
                       t.full_name AS teacher_name, t.teacher_code
                FROM library_borrows b
                JOIN library_books bk ON bk.id = b.book_id
                LEFT JOIN students s ON s.id = b.student_id
                LEFT JOIN teachers t ON t.id = b.teacher_id';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE b.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY b.borrow_date DESC, b.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function recordBorrow(int $bookId, string $borrowerType, ?int $studentId, ?int $teacherId, string $dueDate): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO library_borrows (
                book_id, borrower_type, student_id, teacher_id, borrow_date, due_date, status, created_at
             ) VALUES (
                :book_id, :b_type, :s_id, :t_id, :b_date, :due_date, "borrowed", :now
             )'
        );
        $stmt->execute([
            'book_id'  => $bookId,
            'b_type'   => $borrowerType,
            's_id'     => $studentId,
            't_id'     => $teacherId,
            'b_date'   => date('Y-m-d'),
            'due_date' => $dueDate,
            'now'      => date('Y-m-d H:i:s'),
        ]);
        $borrowId = (int) $pdo->lastInsertId();

        // Decrement available copies
        $up = $pdo->prepare('UPDATE library_books SET available_copies = GREATEST(0, available_copies - 1) WHERE id = :id');
        $up->execute(['id' => $bookId]);

        return $borrowId;
    }

    public function recordReturn(int $borrowId): void
    {
        $pdo = Database::connection();
        $borrowStmt = $pdo->prepare('SELECT * FROM library_borrows WHERE id = :id LIMIT 1');
        $borrowStmt->execute(['id' => $borrowId]);
        $borrow = $borrowStmt->fetch();
        if ($borrow === false) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE library_borrows SET status = "returned", return_date = :rdate WHERE id = :id'
        );
        $stmt->execute([
            'rdate' => date('Y-m-d'),
            'id'    => $borrowId,
        ]);

        // Increment available copies
        $up = $pdo->prepare('UPDATE library_books SET available_copies = LEAST(total_copies, available_copies + 1) WHERE id = :id');
        $up->execute(['id' => $borrow['book_id']]);
    }
}
