<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Isolated regression checks: no school database or credentials required.
//
// The printed rosters shorten the religion to a leading letter in mixed
// columns ("م - ف", "م- فرنسى", "ج - س"). The same shapes are printed on
// students of both religions -- "م- فرنسى" and "م- المانى" appear on
// Christian students -- so decoding them once produced wrong religions.
// These checks pin the contract: only an explicit printed value
// (مسلم/مسلمة/مسيحى/مسيحية) names a religion; shorthand and subject/track
// values are stored verbatim and never derive one.
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

$attributes = new App\Services\RosterAttributeService();
$import = new App\Services\RosterImportService();
$checks = 0;

try {
    // 1. Explicit printed values are the only ones that name a religion.
    foreach ([
        'مسلم' => 'muslim',
        'مسلمة' => 'muslim',
        'مسيحى' => 'christian',
        'مسيحية' => 'christian',
        'مسيحي' => 'christian',
        ' مسلمة ' => 'muslim',
    ] as $printed => $expected) {
        $decoded = $attributes->decodeReligion($printed);
        if ($decoded !== $expected) {
            throw new LogicException("Explicit value \"{$printed}\" decoded to " . var_export($decoded, true) . " instead of {$expected}");
        }
        $checks++;
    }

    // 2. Ambiguous shorthand and non-religion values never derive a religion.
    foreach (['م - ف', 'م - ن', 'م - س', 'م- فرنسى', 'م- المانى', 'ح - س', 'ج - س', 'م-', 'م', 'رياضة', 'معفى', 'المانى', 'فرنسى'] as $ambiguous) {
        $decoded = $attributes->decodeReligion($ambiguous);
        if ($decoded !== null) {
            throw new LogicException("Ambiguous value \"{$ambiguous}\" must not decode to a religion (got {$decoded})");
        }
        $checks++;
    }

    // 3. Shorthand detection matches every printed shorthand shape and
    //    nothing else (the verification screen counts these values).
    foreach (['م - ف', 'م - ن', 'م - س', 'م- فرنسى', 'م- المانى', 'ح - س', 'ج - س'] as $value) {
        if (!$attributes->isShorthand($value)) {
            throw new LogicException("\"{$value}\" should be detected as shorthand");
        }
        $checks++;
    }
    foreach (['', 'مسلمة', 'مسيحية', 'رياضة', 'المانى'] as $value) {
        if ($attributes->isShorthand($value)) {
            throw new LogicException("\"{$value}\" must not be detected as shorthand");
        }
        $checks++;
    }

    // 4. Carry-down: an explicit value printed once covers the rows below,
    //    shorthand is carried verbatim but never becomes a religion, and a
    //    new class block resets the carry instead of leaking the value.
    $csv = "Full Name,Class Name,Gender (M/F),Date of Birth (YYYY-MM-DD),Religion,Class Attribute,Notes\n"
        . "طالب أول,A1,,,,مسلمة,\n"
        . "طالب ثان,A1,,,,,\n"
        . "طالب ثالث,A1,,,,م - ن,\n"
        . "طالب رابع,A1,,,,,\n"
        . "طالب خامس,A1,,,,مسيحية,\n"
        . "طالب سادس,A2,,,,,\n";
    $path = tempnam(sys_get_temp_dir(), 'roster-religion-');
    file_put_contents($path, $csv);
    $expanded = $attributes->expandCsv($path);
    unlink($path);

    $expectedRows = [
        ['attr' => 'مسلمة', 'source' => 'printed', 'religion' => 'muslim'],
        ['attr' => 'مسلمة', 'source' => 'carried_forward', 'religion' => 'muslim'],
        ['attr' => 'م - ن', 'source' => 'printed', 'religion' => ''],
        ['attr' => 'م - ن', 'source' => 'carried_forward', 'religion' => ''],
        ['attr' => 'مسيحية', 'source' => 'printed', 'religion' => 'christian'],
        ['attr' => '', 'source' => '', 'religion' => ''],
    ];
    if (count($expanded['rows']) !== count($expectedRows)) {
        throw new LogicException('expandCsv returned ' . count($expanded['rows']) . ' rows instead of ' . count($expectedRows));
    }
    foreach ($expanded['rows'] as $index => $row) {
        $want = $expectedRows[$index];
        if ($row['_attribute'] !== $want['attr'] || $row['_attribute_source'] !== $want['source'] || $row['_religion'] !== $want['religion']) {
            throw new LogicException("Row {$index}: got attribute \"{$row['_attribute']}\" ({$row['_attribute_source']}) with religion \"{$row['_religion']}\", expected \"{$want['attr']}\" ({$want['source']}) with religion \"{$want['religion']}\"");
        }
        $checks++;
    }
    if ($expanded['counts']['religion'] !== 3) {
        throw new LogicException('expandCsv counted ' . $expanded['counts']['religion'] . ' religions instead of 3 (two مسلمة rows and one مسيحية row)');
    }
    $checks++;

    // 5. The Religion column of an uploaded roster accepts every explicit
    //    printed spelling (including مسيحى, the form the printed pages use)
    //    and rejects shorthand, so no inferred value passes the gate.
    $normalize = new ReflectionMethod(App\Services\RosterImportService::class, 'normalizeReligion');
    foreach ([
        'مسلم' => 'muslim',
        'مسلمة' => 'muslim',
        'مسيحي' => 'christian',
        'مسيحية' => 'christian',
        'مسيحى' => 'christian',
        'muslim' => 'muslim',
        'christian' => 'christian',
        'other' => 'other',
    ] as $value => $expected) {
        if ($normalize->invoke($import, $value) !== $expected) {
            throw new LogicException("Religion column value \"{$value}\" did not normalize to {$expected}");
        }
        $checks++;
    }
    foreach (['م - ف', 'م-', 'رياضة', 'علم نفس', ''] as $value) {
        if ($normalize->invoke($import, $value) !== null) {
            throw new LogicException("Religion column value \"{$value}\" must not normalize to a religion");
        }
        $checks++;
    }

    echo "PASS: {$checks} roster religion decode checks\n";
} finally {
    if (isset($path) && is_file($path)) {
        @unlink($path);
    }
}
