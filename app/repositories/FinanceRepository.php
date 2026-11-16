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

    /**
     * Record a payment against an invoice.
     *
     * Money is the one domain where "mostly right" is not acceptable, so this
     * is transactional and locks the invoice row before deciding whether the
     * payment is allowed. The previous version inserted the receipt first and
     * only then read the balance, which meant a cashier could take a payment
     * larger than the outstanding amount, pay a cancelled invoice, or pay one
     * that was already settled -- and a failure between the two statements
     * left a receipt with no matching balance change.
     *
     * @param array<string,mixed> $data
     * @throws \RuntimeException with a stable code: invoice_not_found,
     *         invoice_cancelled, invoice_already_paid, payment_exceeds_balance,
     *         invalid_payment_amount
     */
    public function recordPayment(array $data): int
    {
        $pdo = Database::connection();
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($invoiceId <= 0 || $amount <= 0) {
            throw new \RuntimeException('invalid_payment_amount');
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $invoice = $this->lockInvoice($invoiceId);
            if ($invoice === null) {
                throw new \RuntimeException('invoice_not_found');
            }
            if ((string) $invoice['status'] === 'cancelled') {
                throw new \RuntimeException('invoice_cancelled');
            }

            $netDue = round((float) $invoice['total_amount'] - (float) $invoice['discount_amount'], 2);
            $alreadyPaid = round((float) $invoice['paid_amount'], 2);
            $remaining = round($netDue - $alreadyPaid, 2);

            if ($remaining <= 0.0) {
                throw new \RuntimeException('invoice_already_paid');
            }
            // A penny of tolerance absorbs decimal rounding at the boundary;
            // anything more is a genuine overpayment and is refused rather
            // than silently accepted into the school's books.
            if ($amount > $remaining + 0.001) {
                throw new \RuntimeException('payment_exceeds_balance');
            }

            $paymentId = Database::insert(
                $pdo,
                'INSERT INTO invoice_payments (
                    invoice_id, receipt_number, amount, payment_date, payment_method,
                    reference_number, notes, recorded_by, created_at
                 ) VALUES (
                    :inv_id, :rec_no, :amount, :pdate, :method,
                    :ref_no, :notes, :rec_by, :now
                 )',
                [
                    'inv_id' => $invoiceId,
                    'rec_no' => $data['receipt_number'],
                    'amount' => $amount,
                    'pdate'  => $data['payment_date'],
                    'method' => $data['payment_method'] ?? 'cash',
                    'ref_no' => $data['reference_number'] ?? null,
                    'notes'  => $data['notes'] ?? null,
                    'rec_by' => $data['recorded_by'] ?? null,
                    'now'    => date('Y-m-d H:i:s'),
                ]
            );

            $newPaid = round($alreadyPaid + $amount, 2);
            $newStatus = $newPaid + 0.001 >= $netDue ? 'paid' : 'partial';

            $upStmt = $pdo->prepare(
                'UPDATE student_invoices SET paid_amount = :paid, status = :status, updated_at = :now WHERE id = :id'
            );
            $upStmt->execute([
                'paid'   => $newPaid,
                'status' => $newStatus,
                'now'    => date('Y-m-d H:i:s'),
                'id'     => $invoiceId,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $paymentId;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Row lock so two cashiers cannot both pass the balance check. @return array<string,mixed>|null */
    private function lockInvoice(int $invoiceId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, total_amount, discount_amount, paid_amount, status
             FROM student_invoices WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getSummary(?int $academicYearId = null): array
    {
        $sql = 'SELECT 
                    COUNT(*) as invoice_count,
                    COALESCE(SUM(total_amount - discount_amount), 0) as total_billed,
                    COALESCE(SUM(paid_amount), 0) as total_collected,
                    COALESCE(SUM(CASE WHEN status != \'paid\' AND status != \'cancelled\' THEN (total_amount - discount_amount - paid_amount) ELSE 0 END), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END), 0) as pending_count,
                    COALESCE(SUM(CASE WHEN status = \'partial\' THEN 1 ELSE 0 END), 0) as partial_count,
                    COALESCE(SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END), 0) as paid_count
                FROM student_invoices
                WHERE status != \'cancelled\'';
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
