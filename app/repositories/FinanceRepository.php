<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class FinanceRepository
{
    public function allCategories(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM fee_categories ORDER BY name_en ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function createCategory(string $code, string $nameEn, string $nameAr): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO fee_categories (code, name_en, name_ar) VALUES (:c, :en, :ar)');
        $stmt->execute(['c' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
        return (int) $pdo->lastInsertId();
    }

    public function allStructures(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT fs.*, fc.name_en AS category_name_en, fc.name_ar AS category_name_ar,
                    g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM fee_structures fs
             JOIN fee_categories fc ON fc.id = fs.fee_category_id
             JOIN grades g ON g.id = fs.grade_id
             WHERE fs.academic_year_id = :year_id
             ORDER BY g.sort_order ASC, fs.name_en ASC'
        );
        $stmt->execute(['year_id' => $academicYearId]);
        return $stmt->fetchAll() ?: [];
    }

    public function createStructure(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO fee_structures (
                academic_year_id, grade_id, fee_category_id, name_en, name_ar, amount, due_date, created_at
             ) VALUES (
                :year_id, :grade_id, :cat_id, :name_en, :name_ar, :amount, :due_date, :now
             )'
        );
        $stmt->execute([
            'year_id'  => $data['academic_year_id'],
            'grade_id' => $data['grade_id'],
            'cat_id'   => $data['fee_category_id'],
            'name_en'  => $data['name_en'],
            'name_ar'  => $data['name_ar'],
            'amount'   => $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'now'      => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function allInvoices(?int $academicYearId = null, ?int $studentId = null, ?string $status = null): array
    {
        $sql = 'SELECT i.*, s.student_code, s.full_name AS student_name, y.label AS academic_year_label
                FROM student_invoices i
                JOIN students s ON s.id = i.student_id
                JOIN academic_years y ON y.id = i.academic_year_id
                WHERE 1=1';
        $params = [];

        if ($academicYearId !== null) {
            $sql .= ' AND i.academic_year_id = :year_id';
            $params['year_id'] = $academicYearId;
        }
        if ($studentId !== null) {
            $sql .= ' AND i.student_id = :student_id';
            $params['student_id'] = $studentId;
        }
        if ($status !== null) {
            $sql .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY i.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findInvoice(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, s.student_code, s.full_name AS student_name, s.phone AS student_phone,
                    y.label AS academic_year_label
             FROM student_invoices i
             JOIN students s ON s.id = i.student_id
             JOIN academic_years y ON y.id = i.academic_year_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createInvoice(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO student_invoices (
                invoice_number, student_id, academic_year_id, title, total_amount,
                discount_amount, paid_amount, status, due_date, notes, created_at, updated_at
             ) VALUES (
                :inv_no, :student_id, :year_id, :title, :total,
                :discount, 0.00, "pending", :due_date, :notes, NOW(), NOW()
             )'
        );
        $stmt->execute([
            'inv_no'     => $data['invoice_number'],
            'student_id' => $data['student_id'],
            'year_id'    => $data['academic_year_id'],
            'title'      => $data['title'],
            'total'      => $data['total_amount'],
            'discount'   => $data['discount_amount'] ?? 0.00,
            'due_date'   => $data['due_date'],
            'notes'      => $data['notes'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function getPayments(int $invoiceId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, u.full_name AS recorded_by_name
             FROM invoice_payments p
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.invoice_id = :inv_id
             ORDER BY p.payment_date DESC, p.id DESC'
        );
        $stmt->execute(['inv_id' => $invoiceId]);
        return $stmt->fetchAll() ?: [];
    }

    public function recordPayment(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO invoice_payments (
                invoice_id, receipt_number, amount, payment_date, payment_method,
                reference_number, notes, recorded_by, created_at
             ) VALUES (
                :inv_id, :rec_no, :amount, :pdate, :method,
                :ref_no, :notes, :rec_by, :now
             )'
        );
        $stmt->execute([
            'inv_id' => $data['invoice_id'],
            'rec_no' => $data['receipt_number'],
            'amount' => $data['amount'],
            'pdate'  => $data['payment_date'],
            'method' => $data['payment_method'] ?? 'cash',
            'ref_no' => $data['reference_number'] ?? null,
            'notes'  => $data['notes'] ?? null,
            'rec_by' => $data['recorded_by'] ?? null,
            'now'    => date('Y-m-d H:i:s'),
        ]);
        $paymentId = (int) $pdo->lastInsertId();

        // Update invoice paid amount and status
        $invoice = $this->findInvoice((int) $data['invoice_id']);
        if ($invoice !== null) {
            $newPaid = (float) $invoice['paid_amount'] + (float) $data['amount'];
            $netDue = (float) $invoice['total_amount'] - (float) $invoice['discount_amount'];
            $newStatus = ($newPaid >= $netDue) ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending');

            $upStmt = $pdo->prepare(
                'UPDATE student_invoices SET paid_amount = :paid, status = :status, updated_at = :now WHERE id = :id'
            );
            $upStmt->execute([
                'paid'   => $newPaid,
                'status' => $newStatus,
                'now'    => date('Y-m-d H:i:s'),
                'id'     => $invoice['id'],
            ]);
        }

        return $paymentId;
    }

    public function getSummary(?int $academicYearId = null): array
    {
        $sql = 'SELECT 
                    COUNT(*) as invoice_count,
                    COALESCE(SUM(total_amount - discount_amount), 0) as total_billed,
                    COALESCE(SUM(paid_amount), 0) as total_collected,
                    COALESCE(SUM(CASE WHEN status != "paid" AND status != "cancelled" THEN (total_amount - discount_amount - paid_amount) ELSE 0 END), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END), 0) as pending_count,
                    COALESCE(SUM(CASE WHEN status = "partial" THEN 1 ELSE 0 END), 0) as partial_count,
                    COALESCE(SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END), 0) as paid_count
                FROM student_invoices
                WHERE status != "cancelled"';
        $params = [];
        if ($academicYearId !== null) {
            $sql .= ' AND academic_year_id = :year_id';
            $params['year_id'] = $academicYearId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: [
            'invoice_count'     => 0,
            'total_billed'      => 0,
            'total_collected'   => 0,
            'total_outstanding' => 0,
            'pending_count'     => 0,
            'partial_count'     => 0,
            'paid_count'        => 0,
        ];
    }
}
