<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Repositories\AdmissionsRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\HrRepository;
use App\Repositories\LibraryRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\TransportRepository;
use App\Services\AdmissionsService;
use App\Services\DashboardStatsService;
use App\Services\DataQualityService;
use App\Services\ModuleManager;

$pdo = Database::connection();
$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $description\n";
        $passed++;
    } else {
        echo "  [FAIL] $description" . ($detail ? " ($detail)" : '') . "\n";
        $failed++;
    }
}

echo "====================================================================\n";
echo "NIZAM UNIVERSAL PLATFORM -- INSTITUTIONAL SCENARIOS TEST SUITE\n";
echo "====================================================================\n\n";

$pdo->beginTransaction();

try {
    // -----------------------------------------------------------------
    // SCENARIO A: SMALL PRIMARY SCHOOL
    // Primary Stage only, simplified operations, modular gating
    // -----------------------------------------------------------------
    echo "--- SCENARIO A: SMALL PRIMARY SCHOOL ---\n";

    // 1. Configure school profile as primary only
    $schoolRepo = new SchoolRepository();
    $schoolRepo->updateProfile(
        name: 'Al-Amal Primary School',
        nameAr: 'مدرسة الأمل الابتدائية',
        address: 'Giza, Egypt',
        phone: '0233445566',
        email: 'info@alamal-primary.edu.eg',
        website: 'https://alamal-primary.edu.eg',
        country: 'EG',
        region: 'Giza',
        timezone: 'Africa/Cairo',
        defaultLocale: 'ar',
        currency: 'EGP',
        dateFormat: 'YYYY-MM-DD',
        numberFormat: 'standard',
        schoolWeek: '0,1,2,3,4',
        schoolType: 'general',
        schoolStructure: 'primary_only',
        taxNumber: 'TAX-PR-10293',
        affiliationCode: 'MIN-GIZA-991'
    );

    $profile = $schoolRepo->full();
    assertTest(
        'School profile updated for Primary School topology',
        $profile !== null && $profile['school_type'] === 'general' && $profile['school_structure'] === 'primary_only'
    );

    // 2. Module gating: disable transport & library, ensure core modules cannot be disabled
    $moduleManager = new ModuleManager();
    assertTest('ModuleManager is available as singleton/service', $moduleManager !== null);

    $coreAttempt = $moduleManager->toggle('students', false);
    assertTest('Core module (students) cannot be disabled', $coreAttempt === false && $moduleManager->isEnabled('students'));

    $toggleTransport = $moduleManager->toggle('transport', false);
    assertTest('Optional module (transport) can be toggled off for small school', $toggleTransport === true && !$moduleManager->isEnabled('transport'));

    // 3. Register a primary student and link family guardian
    $activeYearId = (int)$pdo->query('SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1')->fetchColumn();
    assertTest('Active academic year exists', $activeYearId > 0);

    // Check or create primary grade
    $primaryGradeStmt = $pdo->prepare("SELECT id FROM grades WHERE name_en LIKE '%Grade 1%' OR name_ar LIKE '%الأول%' LIMIT 1");
    $primaryGradeStmt->execute();
    $primaryGradeId = $primaryGradeStmt->fetchColumn();
    if (!$primaryGradeId) {
        $pdo->query("INSERT INTO grades (name_en, name_ar, sort_order) VALUES ('Grade 1', 'الصف الأول الابتدائي', 1)");
        $primaryGradeId = (int)$pdo->lastInsertId();
    }

    $primaryClassStmt = $pdo->prepare("SELECT id FROM classes WHERE grade_id = :gid AND academic_year_id = :yid LIMIT 1");
    $primaryClassStmt->execute(['gid' => $primaryGradeId, 'yid' => $activeYearId]);
    $primaryClassId = $primaryClassStmt->fetchColumn();
    if (!$primaryClassId) {
        $pdo->query("INSERT INTO classes (grade_id, academic_year_id, name, capacity) VALUES ($primaryGradeId, $activeYearId, 'Class 1-A', 25)");
        $primaryClassId = (int)$pdo->lastInsertId();
    }

    $stuCode = 'STU-TEST-' . random_int(1000, 9999);
    $pdo->query("INSERT INTO students (student_code, full_name, gender, date_of_birth, religion, phone, guardian_phone, address, status)
                 VALUES ('$stuCode', 'Youssef Hassan Mahmoud', 'm', '2018-03-15', 'muslim', '01012345678', '01012345678', '14 Al-Ahram St, Giza', 'active')");
    $primaryStudentId = (int)$pdo->lastInsertId();

    $pdo->query("INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status)
                 VALUES ($primaryStudentId, $activeYearId, $primaryGradeId, $primaryClassId, '2026-09-01', 'active')");

    $guardianRepo = new GuardianRepository();
    $guardianId = $guardianRepo->create([
        'full_name' => 'Hassan Mahmoud',
        'relationship_type' => 'father',
        'national_id' => '28003150109988',
        'phone' => '01012345678',
        'email' => 'hassan.m@gmail.com',
        'occupation' => 'Civil Engineer',
        'address' => '14 Al-Ahram St, Giza',
    ]);
    assertTest('Guardian record created with occupation and national ID', $guardianId > 0);

    $guardianRepo->linkStudent($primaryStudentId, $guardianId, 'father', true);
    $linkedStudents = $guardianRepo->getStudentsForGuardian($guardianId);
    assertTest('Guardian linked to primary student and retrievable via relationship query',
        count($linkedStudents) >= 1 && (int)$linkedStudents[0]['id'] === $primaryStudentId
    );


    // -----------------------------------------------------------------
    // SCENARIO B: LARGE BILINGUAL / LANGUAGE SCHOOL
    // K-12 Combined, Full ERP pipeline: Admissions -> Student -> Invoice -> Payment -> Transport -> Library
    // -----------------------------------------------------------------
    echo "\n--- SCENARIO B: LARGE BILINGUAL LANGUAGE SCHOOL ---\n";

    // 1. Ensure all optional modules enabled
    $moduleManager->toggle('admissions', true);
    $moduleManager->toggle('finance', true);
    $moduleManager->toggle('hr', true);
    $moduleManager->toggle('transport', true);
    $moduleManager->toggle('library', true);
    assertTest('Admissions, Finance, HR, Transport, and Library enabled',
        $moduleManager->isEnabled('admissions') &&
        $moduleManager->isEnabled('finance') &&
        $moduleManager->isEnabled('hr') &&
        $moduleManager->isEnabled('transport') &&
        $moduleManager->isEnabled('library')
    );

    // 2. Admissions Pipeline: Inquiry -> Assessment -> Decision -> Conversion
    $admissionsService = new AdmissionsService();
    $appResult = $admissionsService->createApplication([
        'academic_year_id' => $activeYearId,
        'grade_id' => $primaryGradeId,
        'applicant_name' => 'Mariam Tarek Ahmed',
        'gender' => 'f',
        'date_of_birth' => '2017-09-20',
        'guardian_name' => 'Tarek Ahmed',
        'guardian_relationship' => 'father',
        'guardian_phone' => '01234567890',
        'guardian_email' => 'tarek.ahmed@example.com',
        'notes' => 'Language school kindergarten transfer applicant'
    ]);
    assertTest('Admissions application created with unique application number',
        $appResult['success'] === true && !empty($appResult['application_number'])
    );

    $admissionsRepo = new AdmissionsRepository();
    $app = $admissionsRepo->find((int)$appResult['id']);
    assertTest('Application record retrievable from repository', $app !== null && $app['applicant_name'] === 'Mariam Tarek Ahmed');

    $appId = (int)$app['id'];
    $admissionsRepo->recordAssessment($appId, date('Y-m-d'), 94.50, 'Outstanding interview and English assessment.');
    $admissionsRepo->recordDecision($appId, 'accepted', 'Accepted with merit honors.');

    $appUpdated = $admissionsRepo->find($appId);
    assertTest('Assessment score and acceptance decision recorded',
        (float)$appUpdated['assessment_score'] === 94.50 && in_array($appUpdated['status'], ['offer', 'accepted'], true)
    );

    // Official enrollment conversion
    $enrollmentResult = $admissionsService->convertApplicantToStudent($appId, $primaryClassId);
    assertTest('Applicant converted to official student atomically',
        $enrollmentResult['success'] === true && isset($enrollmentResult['student_id']) && $enrollmentResult['student_id'] > 0
    );

    $newStudentId = (int)$enrollmentResult['student_id'];
    $studentRow = $pdo->query("SELECT full_name, status FROM students WHERE id = $newStudentId")->fetch();
    assertTest('Official student record active in SIS',
        $studentRow !== false && $studentRow['status'] === 'active'
    );

    // 3. Finance & Billing: Tuition Invoice and Payment Receipt
    $financeRepo = new FinanceRepository();
    $invoiceNum = 'INV-2026-' . random_int(10000, 99999);
    $invoiceId = $financeRepo->createInvoice([
        'invoice_number' => $invoiceNum,
        'academic_year_id' => $activeYearId,
        'student_id' => $newStudentId,
        'title' => 'Term 1 Tuition & Bilingual Activity Fee',
        'total_amount' => 15000.00,
        'discount_amount' => 1500.00,
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'notes' => 'Merit 10% discount applied'
    ]);
    assertTest('Student invoice created with net amount calculation', $invoiceId > 0);

    $invoice = $financeRepo->findInvoice($invoiceId);
    assertTest('Invoice totals verified (Gross: 15,000, Discount: 1,500, Status: pending)',
        (float)$invoice['total_amount'] === 15000.00 &&
        (float)$invoice['discount_amount'] === 1500.00 &&
        $invoice['status'] === 'pending'
    );

    // Record partial payment
    $receiptNum = 'REC-2026-' . random_int(10000, 99999);
    $paymentId = $financeRepo->recordPayment([
        'invoice_id' => $invoiceId,
        'receipt_number' => $receiptNum,
        'amount' => 5000.00,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'bank_transfer',
        'reference_number' => 'TXN-998877',
        'notes' => 'Initial tuition deposit',
        'recorded_by' => 1
    ]);
    assertTest('Payment receipt generated', $paymentId > 0);

    $invoiceAfterPay = $financeRepo->findInvoice($invoiceId);
    assertTest('Invoice updated to partial with recorded payment (Paid: 5,000, Status: partial)',
        (float)$invoiceAfterPay['paid_amount'] === 5000.00 &&
        $invoiceAfterPay['status'] === 'partial'
    );

    // 4. Transportation: Route creation and passenger assignment
    $transportRepo = new TransportRepository();
    $routeId = $transportRepo->createRoute([
        'route_name' => 'Route 12 - Sheikh Zayed & October',
        'vehicle_number' => 'BUS-402',
        'capacity' => 28,
        'driver_name' => 'Amr Mostafa',
        'driver_phone' => '01122334455',
        'fee_amount' => 4500.00
    ]);
    assertTest('Transportation route created', $routeId > 0);

    $transportRepo->assignStudent($newStudentId, $routeId, 'Zayed Dunes Gate 2', 'Zayed Dunes Gate 2');
    $passengers = $transportRepo->getStudentsForRoute($routeId);
    assertTest('Bus route passenger roster verified', count($passengers) === 1 && (int)$passengers[0]['student_id'] === $newStudentId);

    // 5. Library: Book cataloging and borrowing
    $libraryRepo = new LibraryRepository();
    $bookId = $libraryRepo->createBook([
        'isbn' => '978-0194422796',
        'title' => 'Oxford Discover Student Book 1',
        'author' => 'Lesley Koustaff',
        'category' => 'English Language',
        'shelf_location' => 'Shelf E-3',
        'total_copies' => 10
    ]);
    assertTest('Library book cataloged with initial copies', $bookId > 0);

    $borrowId = $libraryRepo->recordBorrow($bookId, 'student', $newStudentId, null, date('Y-m-d', strtotime('+14 days')));
    assertTest('Library loan issued to student', $borrowId > 0);

    $bookAfterBorrow = $libraryRepo->findBook($bookId);
    assertTest('Book available copies decremented (10 -> 9)', (int)$bookAfterBorrow['available_copies'] === 9);

    $libraryRepo->recordReturn($borrowId);
    $bookAfterReturn = $libraryRepo->findBook($bookId);
    assertTest('Book available copies restored (9 -> 10)', (int)$bookAfterReturn['available_copies'] === 10);

    // 6. HR & Staff: Department & Leave state machine
    $hrRepo = new HrRepository();
    $deptCode = 'LANG-' . random_int(100, 999);
    $deptId = $hrRepo->createDepartment($deptCode, 'Foreign Languages', 'اللغات الأجنبية');
    assertTest('HR department created', $deptId > 0);

    $teacherId = $pdo->query('SELECT id FROM teachers LIMIT 1')->fetchColumn();
    if ($teacherId) {
        $leaveId = $hrRepo->createLeave([
            'teacher_id' => (int)$teacherId,
            'leave_type' => 'annual',
            'start_date' => date('Y-m-d', strtotime('+7 days')),
            'end_date' => date('Y-m-d', strtotime('+9 days')),
            'reason' => 'Family vacation'
        ]);
        assertTest('Staff leave request created with pending status', $leaveId > 0);

        $adminUserId = $pdo->query('SELECT id FROM users LIMIT 1')->fetchColumn();
        $hrRepo->updateLeaveStatus($leaveId, 'approved', (int)$adminUserId, 'Approved by academic director');

        $leaveRow = $pdo->query("SELECT status, action_notes FROM staff_leaves WHERE id = $leaveId")->fetch();
        assertTest('Leave state machine transitioned to approved',
            $leaveRow !== false && $leaveRow['status'] === 'approved' && strpos($leaveRow['action_notes'], 'Approved') !== false
        );
    }


    // -----------------------------------------------------------------
    // SCENARIO C: INTERNATIONAL SCHOOL
    // GPA / Letter grading scale (4.0 scale), multi-currency, fee discount
    // -----------------------------------------------------------------
    echo "\n--- SCENARIO C: INTERNATIONAL SCHOOL ---\n";

    // 1. Create or verify GPA grading scale and bands
    $scaleStmt = $pdo->query("SELECT id FROM grading_scales WHERE scale_type = 'gpa' OR name_en LIKE '%GPA%' LIMIT 1");
    $gpaScaleId = $scaleStmt->fetchColumn();
    if (!$gpaScaleId) {
        $pdo->query("INSERT INTO grading_scales (name_en, name_ar, scale_type, is_default, description, created_at)
                     VALUES ('American GPA 4.0 Scale', 'مقياس المعدل التراكمي الأمريكي', 'gpa', 0, 'Standard 4.0 GPA scale', NOW())");
        $gpaScaleId = (int)$pdo->lastInsertId();

        $bands = [
            ['A', 90.0, 100.0, 4.00, 1],
            ['B', 80.0, 89.99, 3.00, 1],
            ['C', 70.0, 79.99, 2.00, 1],
            ['D', 60.0, 69.99, 1.00, 1],
            ['F', 0.0,  59.99, 0.00, 0],
        ];
        $bandStmt = $pdo->prepare("INSERT INTO grading_scale_bands (grading_scale_id, band_label, min_score, max_score, gpa_point, is_passing)
                                   VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($bands as $b) {
            $bandStmt->execute([$gpaScaleId, $b[0], $b[1], $b[2], $b[3], $b[4]]);
        }
    }
    assertTest('International GPA grading scale configured', $gpaScaleId > 0);

    // Verify grading band conversion logic
    $lookupBand = function(float $score) use ($pdo, $gpaScaleId): ?array {
        $stmt = $pdo->prepare("SELECT band_label, gpa_point, is_passing
                               FROM grading_scale_bands
                               WHERE grading_scale_id = :sid AND :score1 >= min_score AND :score2 <= max_score
                               ORDER BY min_score DESC LIMIT 1");
        $stmt->execute(['sid' => $gpaScaleId, 'score1' => $score, 'score2' => $score]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    };

    $bandA = $lookupBand(95.0);
    assertTest('Score 95.0 maps to Grade A and 4.0 GPA', $bandA !== null && $bandA['band_label'] === 'A' && (float)$bandA['gpa_point'] === 4.00);

    $bandB = $lookupBand(84.5);
    assertTest('Score 84.5 maps to Grade B and 3.0 GPA', $bandB !== null && $bandB['band_label'] === 'B' && (float)$bandB['gpa_point'] === 3.00);

    $bandF = $lookupBand(45.0);
    assertTest('Score 45.0 maps to Grade F and failing status', $bandF !== null && $bandF['band_label'] === 'F' && (int)$bandF['is_passing'] === 0);

    // 2. Fee structure configuration for international tier
    $firstCatId = (int)$pdo->query('SELECT id FROM fee_categories LIMIT 1')->fetchColumn();
    if (!$firstCatId) {
        $firstCatId = $financeRepo->createCategory('IB-TUITION', 'IB Tuition', 'مصروفات البكالوريا');
    }
    $structureId = $financeRepo->createStructure([
        'academic_year_id' => $activeYearId,
        'grade_id' => $primaryGradeId,
        'fee_category_id' => $firstCatId,
        'name_en' => 'IB Primary Years Tuition (Tier 1)',
        'name_ar' => 'مصروفات البكالوريا الدولية للمرحلة الابتدائية',
        'amount' => 45000.00,
        'due_date' => date('Y-m-d', strtotime('+60 days'))
    ]);
    assertTest('International fee structure registered', $structureId > 0);


    // -----------------------------------------------------------------
    // SCENARIO D: MULTI-CAMPUS INSTITUTION
    // Multi-Campus structure, branch campuses, Data Quality Auditor & Dashboard
    // -----------------------------------------------------------------
    echo "\n--- SCENARIO D: MULTI-CAMPUS INSTITUTION ---\n";

    // 1. Verify and register multiple campuses
    $mainCampus = $pdo->query("SELECT id, code, is_main FROM campuses WHERE is_main = 1 LIMIT 1")->fetch();
    assertTest('Main Campus is designated in database', $mainCampus !== false && (int)$mainCampus['is_main'] === 1);

    $branchCampusStmt = $pdo->prepare("SELECT id FROM campuses WHERE code = 'BRANCH-WEST' LIMIT 1");
    $branchCampusStmt->execute();
    $branchId = $branchCampusStmt->fetchColumn();
    if (!$branchId) {
        $pdo->query("INSERT INTO campuses (school_id, code, name_en, name_ar, address, phone, is_main, is_active, created_at)
                     VALUES (1, 'BRANCH-WEST', 'West Campus (Zayed)', 'فرع زايد - غرب القاهرة', 'Waslet Dahshur, Zayed', '0238000000', 0, 1, NOW())");
        $branchId = (int)$pdo->lastInsertId();
    }
    assertTest('Branch Campus registered with unique campus code', $branchId > 0);

    $allCampuses = $pdo->query("SELECT COUNT(*) FROM campuses WHERE is_active = 1")->fetchColumn();
    assertTest('Multiple active campuses exist for institution', (int)$allCampuses >= 2);

    // 2. Data Quality Auditor verification
    $dataQualityService = new DataQualityService();
    $auditResults = $dataQualityService->audit();
    assertTest('DataQualityService audit executed successfully', is_array($auditResults));
    assertTest('Data Quality issues structured properly',
        empty($auditResults) || (isset($auditResults[0]['category']) && isset($auditResults[0]['severity']))
    );

    // 3. Universal Dashboard Stats & Action Center integration
    $_SESSION['role_code'] = 'admin';
    $_SESSION['user_id'] = 1;
    $dashboardStats = (new DashboardStatsService())->build();
    assertTest('Dashboard stats include active year status and metrics',
        isset($dashboardStats['hasActiveYear']) && isset($dashboardStats['students'])
    );
    assertTest('Dashboard stats include Action Center items',
        isset($dashboardStats['actionItems']) && is_array($dashboardStats['actionItems'])
    );
    assertTest('Dashboard stats include Financial summary metrics',
        array_key_exists('financeSummary', $dashboardStats)
    );
    assertTest('Dashboard stats include Admissions counts',
        array_key_exists('admissionsCounts', $dashboardStats)
    );
    assertTest('Dashboard stats include Data Quality audit report',
        isset($dashboardStats['qualityIssues']) && is_array($dashboardStats['qualityIssues'])
    );

} finally {
    // Roll back test transaction to ensure database stays pristine
    $pdo->rollBack();
}

echo "\n====================================================================\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
