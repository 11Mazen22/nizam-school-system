<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Comprehensive System Test Suite
 * Tests all major workflows and functionality
 */

require 'app/bootstrap.php';

$pdo = App\Database::connection();
$tests = [];
$passed = 0;
$failed = 0;

function test($name, $callable) {
    global $tests, $passed, $failed;
    try {
        $result = $callable();
        if ($result) {
            echo "✓ PASS: $name\n";
            $passed++;
            $tests[] = ['name' => $name, 'status' => 'PASS'];
        } else {
            echo "✗ FAIL: $name\n";
            $failed++;
            $tests[] = ['name' => $name, 'status' => 'FAIL'];
        }
    } catch (Exception $e) {
        echo "✗ ERROR: $name - {$e->getMessage()}\n";
        $failed++;
        $tests[] = ['name' => $name, 'status' => 'ERROR', 'message' => $e->getMessage()];
    }
}

echo "========================================\n";
echo "HADABA AL-AHRAM SYSTEM TEST SUITE\n";
echo "========================================\n\n";

// Database Tests
echo "--- DATABASE TESTS ---\n";
test("Database connection", function() use ($pdo) {
    return $pdo !== null;
});

test("All migrations applied (17)", function() use ($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn();
    return $count == 17;
});

test("Schools table has data", function() use ($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
    return $count > 0;
});

test("At least one active admin user exists", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code = 'admin' AND u.is_active = 1");
    return $stmt->fetchColumn() > 0;
});

test("Academic years table accessible", function() use ($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM academic_years")->fetchColumn();
    return $count >= 0;
});

// Translation Tests
echo "\n--- TRANSLATION TESTS ---\n";
test("Arabic translations file exists", function() {
    return file_exists('lang/ar.php');
});

test("English translations file exists", function() {
    return file_exists('lang/en.php');
});

test("Import translations present in Arabic", function() {
    $ar = require 'lang/ar.php';
    return isset($ar['import.no_file']) && isset($ar['import.failed']);
});

test("Import translations present in English", function() {
    $en = require 'lang/en.php';
    return isset($en['import.no_file']) && isset($en['import.failed']);
});

// Controller Tests
echo "\n--- CONTROLLER TESTS ---\n";
test("ImportController exists", function() {
    return class_exists('App\Controllers\ImportController');
});

test("AuthController exists", function() {
    return class_exists('App\Controllers\AuthController');
});

test("StudentController exists", function() {
    return class_exists('App\Controllers\StudentController');
});

test("BackupController exists", function() {
    return class_exists('App\Controllers\BackupController');
});

// Service Tests
echo "\n--- SERVICE TESTS ---\n";
test("ImportService exists", function() {
    return class_exists('App\Services\ImportService');
});

test("BackupService exists", function() {
    return class_exists('App\Services\BackupService');
});

test("AuthService exists", function() {
    return class_exists('App\Services\AuthService');
});

// Security Tests
echo "\n--- SECURITY TESTS ---\n";
test("CSRF Middleware exists", function() {
    return class_exists('App\Middleware\CsrfMiddleware');
});

test("RoleGuard Middleware exists", function() {
    return class_exists('App\Middleware\RoleGuardMiddleware');
});

test("Escape helper function exists", function() {
    return function_exists('e');
});

test("Translation helper function exists", function() {
    return function_exists('__');
});

// File Structure Tests
echo "\n--- FILE STRUCTURE TESTS ---\n";
test("Config example exists", function() {
    return file_exists('config/config.example.php');
});

test("Bootstrap file exists", function() {
    return file_exists('app/bootstrap.php');
});

test("Router exists", function() {
    return file_exists('app/Router.php');
});

test("Database class exists", function() {
    return file_exists('app/Database.php');
});

// Assets Tests
echo "\n--- ASSETS TESTS ---\n";
test("CSS app file exists", function() {
    return file_exists('public/assets/css/app.css');
});

test("JS app file exists", function() {
    return file_exists('public/assets/js/app.js');
});

test("Service worker exists", function() {
    return file_exists('public/sw.js');
});

test("Manifest exists", function() {
    return file_exists('public/manifest.json');
});

// Import Functionality Tests
echo "\n--- IMPORT FUNCTIONALITY ---\n";
test("ImportService can generate templates", function() {
    $service = new App\Services\ImportService();
    return method_exists($service, 'generateTemplate');
});

test("ImportService can parse files", function() {
    $service = new App\Services\ImportService();
    return method_exists($service, 'parseFile');
});

test("ImportController has all entity configs", function() {
    $reflection = new ReflectionClass('App\Controllers\ImportController');
    $method = $reflection->getMethod('getEntityConfig');
    $method->setAccessible(true);
    $controller = new App\Controllers\ImportController();
    
    $entities = ['grades', 'classes', 'subjects', 'teachers', 'students', 'users', 'assignments'];
    foreach ($entities as $entity) {
        $config = $method->invoke($controller, $entity);
        if ($config === null) {
            return false;
        }
    }
    return true;
});

echo "\n========================================\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo "========================================\n";

if ($failed > 0) {
    exit(1);
}
