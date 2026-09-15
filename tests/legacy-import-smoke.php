<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * End-to-end Import Test
 * Tests the complete import workflow
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ImportService;
use App\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "========================================\n";
echo "END-TO-END IMPORT TEST\n";
echo "========================================\n\n";

$pdo = Database::connection();
$passed = 0;
$failed = 0;

// Test 1: Generate template
echo "TEST 1: Generate template\n";
try {
    $importService = new ImportService();
    $headers = ["Name (English)", "Name (Arabic)", "Sort Order"];
    
    // Capture output
    ob_start();
    $importService->generateTemplate($headers, "test_template", "en");
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "✓ PASS: Template generated successfully\n";
        $passed++;
    } else {
        echo "✗ FAIL: Template output too small\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Create test Excel file with data
echo "\nTEST 2: Create test Excel file\n";
try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Headers
    $sheet->setCellValue('A1', 'Name (English)');
    $sheet->setCellValue('B1', 'Name (Arabic)');
    $sheet->setCellValue('C1', 'Code');
    $sheet->setCellValue('D1', 'Weekly Periods');
    $sheet->setCellValue('E1', 'Sort Order');
    
    // Test data rows
    $sheet->setCellValue('A2', 'Test Subject A');
    $sheet->setCellValue('B2', 'مادة اختبار أ');
    $sheet->setCellValue('C2', 'TESTA');
    $sheet->setCellValue('D2', '5');
    $sheet->setCellValue('E2', '100');
    
    $sheet->setCellValue('A3', 'Test Subject B');
    $sheet->setCellValue('B3', 'مادة اختبار ب');
    $sheet->setCellValue('C3', 'TESTB');
    $sheet->setCellValue('D3', '4');
    $sheet->setCellValue('E3', '101');
    
    $testFile = __DIR__ . '/test_import.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($testFile);
    
    if (file_exists($testFile)) {
        echo "✓ PASS: Test file created\n";
        $passed++;
    } else {
        echo "✗ FAIL: Test file not created\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Parse the file
echo "\nTEST 3: Parse test file\n";
try {
    $rows = $importService->parseFile($testFile);
    
    if (count($rows) == 2) {
        echo "✓ PASS: Parsed 2 data rows\n";
        $passed++;
    } else {
        echo "✗ FAIL: Expected 2 rows, got " . count($rows) . "\n";
        $failed++;
    }
    
    if ($rows[0]['Name (English)'] == 'Test Subject A') {
        echo "✓ PASS: Data correctly parsed\n";
        $passed++;
    } else {
        echo "✗ FAIL: Data parsing incorrect\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Verify ImportController process functions exist and work
echo "\nTEST 4: Import process functions\n";
try {
    // Check that we can instantiate the controller
    $controller = new \App\Controllers\ImportController();
    echo "✓ PASS: ImportController instantiates\n";
    $passed++;
    
    // Verify getEntityConfig method works (via reflection)
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getEntityConfig');
    $method->setAccessible(true);
    
    $subjectConfig = $method->invoke($controller, 'subjects');
    if ($subjectConfig !== null && isset($subjectConfig['process'])) {
        echo "✓ PASS: Subject import config exists\n";
        $passed++;
    } else {
        echo "✗ FAIL: Subject import config missing\n";
        $failed++;
    }
    
    $teacherConfig = $method->invoke($controller, 'teachers');
    if ($teacherConfig !== null && isset($teacherConfig['process'])) {
        echo "✓ PASS: Teacher import config exists\n";
        $passed++;
    } else {
        echo "✗ FAIL: Teacher import config missing\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    $failed++;
}

// Clean up
if (file_exists($testFile)) {
    unlink($testFile);
}

echo "\n========================================\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo "========================================\n";

if ($failed > 0) {
    exit(1);
}
