<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Isolated regression checks: no school database or credentials required.
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

$path = tempnam(sys_get_temp_dir(), 'school-import-');
$service = new App\Services\ImportService();
$checks = 0;
try {
    foreach ([
        ["Name,Name\nEnglish,Arabic\n", ['Name']],
        ["Name,\nEnglish,Arabic\n", ['Name']],
        ["Wrong\nValue\n", ['Name']],
        ["Name,Unexpected\nValue,Other\n", ['Name']],
    ] as [$csv, $headers]) {
        file_put_contents($path, $csv);
        try {
            $service->parseFile($path, $headers);
            throw new LogicException('Invalid headers accepted');
        } catch (RuntimeException $expected) {
            $checks++;
        }
    }
    file_put_contents($path, "Name (Arabic),Name (English)\nهضبة الأهرام,Hadaba\n");
    $rows = $service->parseFile($path, ['Name (English)', 'Name (Arabic)']);
    if ($rows !== [['Name (Arabic)' => 'هضبة الأهرام', 'Name (English)' => 'Hadaba']]) {
        throw new LogicException('Reordered bilingual headers mapped incorrectly');
    }
    $checks++;
    try {
        (new App\Services\UserService())->update(0, 'unused', 'unused', 'admin', 'short');
        throw new LogicException('Invalid password accepted');
    } catch (RuntimeException $expected) {
        if ($expected->getMessage() !== 'password_too_short') {
            throw $expected;
        }
        $checks++;
    }
    echo "PASS: {$checks} import and pre-write validation checks\n";
} finally {
    unlink($path);
}
