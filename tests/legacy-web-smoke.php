<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Web Application Flow Test
 * Tests actual HTTP requests and responses
 */

$baseUrl = 'http://127.0.0.1:8095';
$passed = 0;
$failed = 0;

function testRequest($name, $url, $checks) {
    global $baseUrl, $passed, $failed;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $allChecksPassed = true;
    foreach ($checks as $check) {
        if (!$check($response, $httpCode)) {
            $allChecksPassed = false;
            break;
        }
    }
    
    if ($allChecksPassed) {
        echo "✓ PASS: $name\n";
        $passed++;
    } else {
        echo "✗ FAIL: $name (HTTP $httpCode)\n";
        $failed++;
    }
}

echo "========================================\n";
echo "WEB APPLICATION FLOW TESTS\n";
echo "========================================\n\n";

echo "--- PUBLIC PAGES ---\n";
testRequest("Login page loads", "/login", [
    function($response, $code) { return $code == 200; },
    function($response, $code) { return strlen($response) > 1000; }
]);

testRequest("Login page has correct branding", "/login", [
    function($response, $code) { return strpos($response, 'هضبة الأهرام') !== false; }
]);

testRequest("Login page has no raw translation keys", "/login", [
    function($response, $code) { return strpos($response, 'auth.login.') === false; }
]);

testRequest("Login page has CSRF field", "/login", [
    function($response, $code) { return strpos($response, '_csrf_token') !== false; }
]);

testRequest("Setup page redirects when complete", "/setup", [
    function($response, $code) { return $code == 302; }
]);

echo "\n--- PROTECTED ROUTES (should redirect) ---\n";
testRequest("Dashboard requires auth", "/dashboard", [
    function($response, $code) { return $code == 302; }
]);

testRequest("Students requires auth", "/students", [
    function($response, $code) { return $code == 302; }
]);

testRequest("Teachers requires auth", "/teachers", [
    function($response, $code) { return $code == 302; }
]);

echo "\n--- STATIC ASSETS ---\n";
testRequest("CSS loads", "/assets/css/app.css", [
    function($response, $code) { return $code == 200; },
    function($response, $code) { return strpos($response, 'n-auth-body') !== false; }
]);

testRequest("JS loads", "/assets/js/app.js", [
    function($response, $code) { return $code == 200; },
    function($response, $code) { return strpos($response, 'serviceWorker') !== false; }
]);

testRequest("Service worker loads", "/sw.js", [
    function($response, $code) { return $code == 200; }
]);

testRequest("Manifest loads", "/manifest.json", [
    function($response, $code) { return $code == 200; }
]);

echo "\n--- HEALTHCHECK ---\n";
testRequest("Health endpoint responds", "/healthz", [
    function($response, $code) { return $code == 200; },
    function($response, $code) { return trim($response) == 'ok'; }
]);

echo "\n========================================\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo "========================================\n";

if ($failed > 0) {
    exit(1);
}
