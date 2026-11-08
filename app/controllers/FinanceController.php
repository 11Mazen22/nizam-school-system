<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Request;
use App\Response;
use App\Repositories\FinanceRepository;
use App\Repositories\GradeRepository;
use App\Repositories\StudentRepository;

final class FinanceController extends Controller
{
    private FinanceRepository $repository;

    public function __construct(?FinanceRepository $repository = null)
    {
        $this->repository = $repository ?? new FinanceRepository();
    }

    public function index(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        $summary = $this->repository->getSummary($activeYearId);
        $recentInvoices = $this->repository->allInvoices($activeYearId);
        $this->view('finance/index', [
            'summary'  => $summary,
            'invoices' => array_slice($recentInvoices, 0, 10),
        ]);
    }

    public function invoices(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        $status = $request->query('status');
        $invoices = $this->repository->allInvoices($activeYearId, null, $status ? (string) $status : null);
        $this->view('finance/invoices', ['invoices' => $invoices, 'activeStatus' => $status]);
    }

    public function showInvoice(Request $request, string $id): void
    {
        $invoice = $this->repository->findInvoice((int) $id);
        if ($invoice === null) {
            Flash::error(__('finance.invoice_not_found'));
            Response::redirect('/finance/invoices');
            return;
        }

        $payments = $this->repository->getPayments((int) $id);
        $this->view('finance/show_invoice', ['invoice' => $invoice, 'payments' => $payments]);
    }

    public function createInvoice(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        $students = (new StudentRepository())->all();
        $this->view('finance/create_invoice', [
            'students'       => $students,
            'academicYearId' => $activeYearId,
        ]);
    }

    public function storeInvoice(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        if ($activeYearId === null) {
            Flash::error(__('finance.no_active_year'));
            Response::redirect('/finance');
            return;
        }

        $studentId = (int) $request->post('student_id');
        $title = trim((string) $request->post('title'));
        $amount = (float) $request->post('total_amount', 0.0);
        $discount = (float) $request->post('discount_amount', 0.0);
        $dueDate = (string) $request->post('due_date', date('Y-m-d', strtotime('+30 days')));

        if ($studentId <= 0 || $title === '' || $amount <= 0) {
            Flash::error(__('finance.invalid_invoice_data'));
            Response::redirect('/finance/invoices/create');
            return;
        }

        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        $id = $this->repository->createInvoice([
            'invoice_number'   => $invoiceNumber,
            'student_id'       => $studentId,
            'academic_year_id' => $activeYearId,
            'title'            => $title,
            'total_amount'     => $amount,
            'discount_amount'  => $discount,
            'due_date'         => $dueDate,
            'notes'            => trim((string) $request->post('notes')) ?: null,
        ]);

        Flash::success(__('finance.invoice_created', ['number' => $invoiceNumber]));
        Response::redirect('/finance/invoices/' . $id);
    }

    public function recordPayment(Request $request, string $id): void
    {
        $amount = (float) $request->post('amount', 0.0);
        if ($amount <= 0) {
            Flash::error(__('finance.invalid_payment_amount'));
            Response::redirect('/finance/invoices/' . $id);
            return;
        }

        $receiptNumber = 'REC-' . date('Y') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        $this->repository->recordPayment([
            'invoice_id'       => (int) $id,
            'receipt_number'   => $receiptNumber,
            'amount'           => $amount,
            'payment_date'     => (string) $request->post('payment_date', date('Y-m-d')),
            'payment_method'   => (string) $request->post('payment_method', 'cash'),
            'reference_number' => trim((string) $request->post('reference_number')) ?: null,
            'notes'            => trim((string) $request->post('notes')) ?: null,
            'recorded_by'      => $_SESSION['user_id'] ?? null,
        ]);

        Flash::success(__('finance.payment_recorded', ['receipt' => $receiptNumber]));
        Response::redirect('/finance/invoices/' . $id);
    }

    public function structures(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        $categories = $this->repository->allCategories();
        $structures = $activeYearId !== null ? $this->repository->allStructures($activeYearId) : [];
        $grades = (new GradeRepository())->all();

        $this->view('finance/structures', [
            'categories' => $categories,
            'structures' => $structures,
            'grades'     => $grades,
        ]);
    }

    public function storeStructure(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        if ($activeYearId === null) {
            Flash::error(__('finance.no_active_year'));
            Response::redirect('/finance/structures');
            return;
        }

        $gradeId = (int) $request->post('grade_id');
        $catId = (int) $request->post('fee_category_id');
        $nameEn = trim((string) $request->post('name_en'));
        $nameAr = trim((string) $request->post('name_ar'));
        $amount = (float) $request->post('amount', 0.0);

        if ($gradeId <= 0 || $catId <= 0 || $nameEn === '' || $nameAr === '' || $amount <= 0) {
            Flash::error(__('finance.invalid_structure_data'));
            Response::redirect('/finance/structures');
            return;
        }

        $this->repository->createStructure([
            'academic_year_id' => $activeYearId,
            'grade_id'         => $gradeId,
            'fee_category_id'  => $catId,
            'name_en'          => $nameEn,
            'name_ar'          => $nameAr,
            'amount'           => $amount,
            'due_date'         => $request->post('due_date') ?: null,
        ]);

        Flash::success(__('finance.structure_created'));
        Response::redirect('/finance/structures');
    }
}
