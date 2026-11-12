<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

/**
 * Module integrity test: the invariants a school actually relies on.
 *
 * Connection-local temporary tables shadow the real school tables, so no
 * permanent row is created, changed or deleted by this test.
 */
$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This integration fixture requires MySQL/MariaDB');
}

$definitions = [
    'students' => 'id INT AUTO_INCREMENT PRIMARY KEY, student_code VARCHAR(30), full_name VARCHAR(150), gender VARCHAR(1) NULL, date_of_birth DATE NULL, religion VARCHAR(20) NULL, phone VARCHAR(30) NULL, guardian_phone VARCHAR(30) NULL, address VARCHAR(255) NULL, status VARCHAR(20) NULL, created_at DATETIME NULL, updated_at DATETIME NULL',
    'academic_years' => 'id INT PRIMARY KEY, label VARCHAR(20), start_date DATE, end_date DATE, is_active TINYINT, is_closed TINYINT',
    'grades' => 'id INT PRIMARY KEY, name_en VARCHAR(60), name_ar VARCHAR(60), sort_order INT',
    'classes' => 'id INT PRIMARY KEY, name VARCHAR(50), grade_id INT, academic_year_id INT',
    'student_enrollments' => 'id INT AUTO_INCREMENT PRIMARY KEY, student_id INT, academic_year_id INT, grade_id INT, class_id INT NULL, enrollment_date DATE, status VARCHAR(20), created_at DATETIME NULL',
    'guardians' => 'id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(150), phone VARCHAR(30), email VARCHAR(100) NULL, national_id VARCHAR(30) NULL, relationship_type VARCHAR(30), occupation VARCHAR(100) NULL, address VARCHAR(255) NULL, notes TEXT NULL, user_id INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL',
    'student_guardians' => 'id INT AUTO_INCREMENT PRIMARY KEY, student_id INT, guardian_id INT, relationship VARCHAR(30), is_primary TINYINT, is_emergency TINYINT, can_pickup TINYINT, receives_reports TINYINT, created_at DATETIME NULL, UNIQUE(student_id, guardian_id)',
    'admissions_applications' => 'id INT AUTO_INCREMENT PRIMARY KEY, application_number VARCHAR(30), applicant_name VARCHAR(150), gender VARCHAR(1), date_of_birth DATE, grade_id INT, academic_year_id INT, guardian_name VARCHAR(150), guardian_phone VARCHAR(30), guardian_email VARCHAR(100) NULL, guardian_relationship VARCHAR(30), status VARCHAR(20), assessment_date DATE NULL, assessment_score DECIMAL(5,2) NULL, assessment_notes TEXT NULL, decision_date DATE NULL, decision_notes TEXT NULL, enrolled_student_id INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL',
    'student_invoices' => 'id INT AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(30), student_id INT, academic_year_id INT, title VARCHAR(150), total_amount DECIMAL(10,2), discount_amount DECIMAL(10,2), paid_amount DECIMAL(10,2), status VARCHAR(20), due_date DATE, notes TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL',
    'invoice_payments' => 'id INT AUTO_INCREMENT PRIMARY KEY, invoice_id INT, receipt_number VARCHAR(30), amount DECIMAL(10,2), payment_date DATE, payment_method VARCHAR(20), reference_number VARCHAR(60) NULL, notes VARCHAR(255) NULL, recorded_by INT NULL, created_at DATETIME NULL',
    'users' => 'id INT PRIMARY KEY, full_name VARCHAR(150)',
    'library_books' => 'id INT AUTO_INCREMENT PRIMARY KEY, isbn VARCHAR(30) NULL, title VARCHAR(200), author VARCHAR(150), category VARCHAR(60), total_copies INT, available_copies INT, shelf_location VARCHAR(50) NULL, created_at DATETIME NULL',
    'library_borrows' => 'id INT AUTO_INCREMENT PRIMARY KEY, book_id INT, borrower_type VARCHAR(20), student_id INT NULL, teacher_id INT NULL, borrow_date DATE, due_date DATE, return_date DATE NULL, status VARCHAR(20), created_at DATETIME NULL',
    'transport_routes' => 'id INT AUTO_INCREMENT PRIMARY KEY, route_name VARCHAR(100), vehicle_number VARCHAR(30), driver_name VARCHAR(100), driver_phone VARCHAR(30), capacity INT, fee_amount DECIMAL(10,2), is_active TINYINT, created_at DATETIME NULL',
    'student_transport' => 'id INT AUTO_INCREMENT PRIMARY KEY, student_id INT, route_id INT, pickup_point VARCHAR(150) NULL, dropoff_point VARCHAR(150) NULL, status VARCHAR(20), created_at DATETIME NULL, UNIQUE(student_id, route_id)',
    'teachers' => 'id INT PRIMARY KEY, full_name VARCHAR(150), teacher_code VARCHAR(30), phone VARCHAR(30)',
    'staff_leaves' => 'id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT, leave_type VARCHAR(20), start_date DATE, end_date DATE, reason TEXT, status VARCHAR(20), approved_by INT NULL, action_notes VARCHAR(255) NULL, created_at DATETIME NULL',
    'activity_logs' => 'user_id INT, action TEXT, entity_type TEXT, entity_id INT, description TEXT, metadata TEXT, ip_address TEXT',
];
foreach ($definitions as $table => $columns) {
    $pdo->exec("CREATE TEMPORARY TABLE {$table} ({$columns}) ENGINE=InnoDB");
}

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new LogicException($message);
    }
};

$throws = static function (callable $fn, string $expected, string $message) use ($check): void {
    try {
        $fn();
    } catch (RuntimeException $e) {
        $check($e->getMessage() === $expected, $message . ' (got: ' . $e->getMessage() . ')');
        return;
    }
    throw new LogicException($message . ' (no exception thrown)');
};

$pdo->exec("INSERT INTO students (id, student_code, full_name, status) VALUES
    (1,'S1','Student One','active'),(2,'S2','Student Two','active'),(3,'S3','Student Three','active')");
$pdo->exec("INSERT INTO academic_years VALUES (1,'2025/2026','2025-09-01','2026-06-30',1,0)");
$pdo->exec("INSERT INTO grades VALUES (1,'Grade 1','الصف الأول',1),(2,'Grade 2','الصف الثاني',2)");
$pdo->exec("INSERT INTO classes VALUES (10,'1A',2,1),(11,'1B',1,1)");
$pdo->exec("INSERT INTO teachers VALUES (1,'Teacher One','T1','0100')");
$pdo->exec("INSERT INTO users VALUES (1,'Admin')");

// ───────────────────────────────────────────────────────────────── Finance
$finance = new App\Repositories\FinanceRepository();

$invoiceId = $finance->createInvoice([
    'invoice_number'   => 'INV-T-1',
    'student_id'       => 1,
    'academic_year_id' => 1,
    'title'            => 'Tuition',
    'total_amount'     => 1000.00,
    'discount_amount'  => 100.00,
    'due_date'         => '2026-01-31',
]);

$finance->recordPayment([
    'invoice_id' => $invoiceId, 'receipt_number' => 'REC-1',
    'amount' => 400.00, 'payment_date' => date('Y-m-d'),
]);
$invoice = $finance->findInvoice($invoiceId);
$check(abs((float) $invoice['paid_amount'] - 400.00) < 0.001, 'Partial payment was not recorded');
$check($invoice['status'] === 'partial', 'Partial payment did not set the invoice to partial');

$finance->recordPayment([
    'invoice_id' => $invoiceId, 'receipt_number' => 'REC-2',
    'amount' => 500.00, 'payment_date' => date('Y-m-d'),
]);
$invoice = $finance->findInvoice($invoiceId);
$check(abs((float) $invoice['paid_amount'] - 900.00) < 0.001, 'Settlement did not reach the discounted total');
$check($invoice['status'] === 'paid', 'Fully settled invoice did not become paid');

$throws(
    static fn () => $finance->recordPayment([
        'invoice_id' => $invoiceId, 'receipt_number' => 'REC-3',
        'amount' => 1.00, 'payment_date' => date('Y-m-d'),
    ]),
    'invoice_already_paid',
    'A settled invoice accepted another payment'
);
$check(count($finance->getPayments($invoiceId)) === 2, 'A rejected payment left a receipt behind');

$overpaidId = $finance->createInvoice([
    'invoice_number'   => 'INV-T-2',
    'student_id'       => 1,
    'academic_year_id' => 1,
    'title'            => 'Books',
    'total_amount'     => 500.00,
    'discount_amount'  => 0.00,
    'due_date'         => '2026-01-31',
]);
$throws(
    static fn () => $finance->recordPayment([
        'invoice_id' => $overpaidId, 'receipt_number' => 'REC-4',
        'amount' => 600.00, 'payment_date' => date('Y-m-d'),
    ]),
    'payment_exceeds_balance',
    'An overpayment was accepted'
);
$overpaid = $finance->findInvoice($overpaidId);
$check(abs((float) $overpaid['paid_amount']) < 0.001, 'A rejected overpayment changed the invoice balance');
$check(count($finance->getPayments($overpaidId)) === 0, 'A rejected overpayment left a receipt behind');

$pdo->exec("UPDATE student_invoices SET status = 'cancelled' WHERE id = {$overpaidId}");
$throws(
    static fn () => $finance->recordPayment([
        'invoice_id' => $overpaidId, 'receipt_number' => 'REC-5',
        'amount' => 100.00, 'payment_date' => date('Y-m-d'),
    ]),
    'invoice_cancelled',
    'A cancelled invoice accepted a payment'
);

// ───────────────────────────────────────────────────────────────── Library
$library = new App\Repositories\LibraryRepository();
$bookId = $library->createBook(['title' => 'Book', 'author' => 'Author', 'total_copies' => 2]);

$loanA = $library->recordBorrow($bookId, 'student', 1, null, '2026-02-01');
$check((int) $library->findBook($bookId)['available_copies'] === 1, 'Borrowing did not reduce availability');

$loanB = $library->recordBorrow($bookId, 'student', 2, null, '2026-02-01');
$check((int) $library->findBook($bookId)['available_copies'] === 0, 'Second loan did not reduce availability');

$throws(
    static fn () => $library->recordBorrow($bookId, 'student', 3, null, '2026-02-01'),
    'no_copies_available',
    'A book with no available copies was lent out'
);

$library->recordReturn($loanA);
$check((int) $library->findBook($bookId)['available_copies'] === 1, 'Return did not restore availability');

// Returning the same loan twice must be idempotent.
$library->recordReturn($loanA);
$check((int) $library->findBook($bookId)['available_copies'] === 1, 'A double return inflated the shelf count');

$library->recordReturn($loanB);
$check((int) $library->findBook($bookId)['available_copies'] === 2, 'Returning the last loan did not restore the full stock');

// ─────────────────────────────────────────────────────────────── Transport
$transport = new App\Repositories\TransportRepository();
$routeId = $transport->createRoute([
    'route_name' => 'Route A', 'vehicle_number' => 'V1',
    'driver_name' => 'Driver', 'driver_phone' => '0100', 'capacity' => 2,
]);

$transport->assignStudent(1, $routeId, 'Stop 1');
$transport->assignStudent(2, $routeId, 'Stop 2');
$check(count($transport->getStudentsForRoute($routeId)) === 2, 'Assignments were not persisted');

$throws(
    static fn () => $transport->assignStudent(3, $routeId),
    'route_full',
    'A route accepted a student beyond its capacity'
);
$check(count($transport->getStudentsForRoute($routeId)) === 2, 'A rejected assignment changed the manifest');

// Re-assigning an existing passenger is allowed and must not consume a seat.
$transport->assignStudent(1, $routeId, 'Updated stop');
$check(count($transport->getStudentsForRoute($routeId)) === 2, 'Re-assignment duplicated the passenger');
$check($transport->getStudentsForRoute($routeId)[0]['pickup_point'] === 'Updated stop'
    || $transport->getStudentsForRoute($routeId)[1]['pickup_point'] === 'Updated stop',
    'Re-assignment did not update the pickup point');

// ───────────────────────────────────────────────────────────────────── HR
$hr = new App\Repositories\HrRepository();
$leaveId = $hr->createLeave([
    'teacher_id' => 1, 'start_date' => '2026-01-01',
    'end_date' => '2026-01-02', 'reason' => 'Family',
]);
$check($hr->updateLeaveStatus($leaveId, 'approved', 1, 'ok') === true, 'A pending leave could not be approved');
$check($hr->updateLeaveStatus($leaveId, 'rejected', 1) === false, 'An already-decided leave was decided again');
$check($hr->updateLeaveStatus($leaveId, 'nonsense', 1) === false, 'An invalid leave status was accepted');

$leaveRow = $pdo->query("SELECT status FROM staff_leaves WHERE id = {$leaveId}")->fetch();
$check($leaveRow['status'] === 'approved', 'The first leave decision was overwritten');

// ────────────────────────────────────────────────────────── Admissions
$admissions = new App\Repositories\AdmissionsRepository();
$service = new App\Services\AdmissionsService($admissions);

$studentsBefore = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

$applicationId = $admissions->create([
    'application_number' => 'APP-T-1',
    'applicant_name'     => 'Applicant Child',
    'gender'             => 'm',
    'date_of_birth'      => '2018-05-01',
    'grade_id'           => 1,
    'academic_year_id'   => 1,
    'guardian_name'      => 'Guardian',
    'guardian_phone'     => '0111111111',
    'guardian_email'     => null,
    'guardian_relationship' => 'father',
    'status'             => 'inquiry',
]);

$result = $service->convertApplicantToStudent($applicationId);
$check($result['success'] === false && $result['error'] === 'admissions.convert_invalid_status',
    'An unreviewed inquiry was converted into a student');
$check((int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === $studentsBefore,
    'A rejected conversion created a student anyway');

$pdo->exec("UPDATE admissions_applications SET status = 'offer' WHERE id = {$applicationId}");

$result = $service->convertApplicantToStudent($applicationId, 10);
$check($result['success'] === false && $result['error'] === 'admissions.convert_class_mismatch',
    'A class from another grade was accepted');
$check((int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === $studentsBefore,
    'A mismatched class conversion created a student anyway');

$result = $service->convertApplicantToStudent($applicationId, 11);
$check($result['success'] === true, 'A valid offer could not be converted to a student');

$newStudentId = (int) $result['student_id'];
$enrollment = $pdo->query("SELECT * FROM student_enrollments WHERE student_id = {$newStudentId}")->fetch();
$check($enrollment !== false && $enrollment['status'] === 'active', 'Conversion did not create an active enrollment');
$check((int) $enrollment['class_id'] === 11, 'Conversion enrolled the student in the wrong class');

$linked = $pdo->query("SELECT COUNT(*) FROM student_guardians WHERE student_id = {$newStudentId}")->fetchColumn();
$check((int) $linked === 1, 'Conversion did not link the guardian');

$appRow = $pdo->query("SELECT status, enrolled_student_id FROM admissions_applications WHERE id = {$applicationId}")->fetch();
$check($appRow['status'] === 'enrolled' && (int) $appRow['enrolled_student_id'] === $newStudentId,
    'The application was not marked as enrolled');

$again = $service->convertApplicantToStudent($applicationId, 11);
$check($again['success'] === false, 'An already-converted application was converted twice');
$check((int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === $studentsBefore + 1,
    'A duplicate conversion created a second student');

echo "PASS: finance settlement/overpayment/cancellation atomicity, library stock and double-return, transport capacity, leave decisions, admissions conversion guards\n";
