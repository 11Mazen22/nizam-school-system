<?php

declare(strict_types=1);

/**
 * i18n contract: both locales must define exactly the same keys, and every
 * literal translation key used in code must exist in both.
 */

$root = dirname(__DIR__);
$en = require $root . '/lang/en.php';
$ar = require $root . '/lang/ar.php';

$passed = 0;
$failed = 0;
function i18nCheck(bool $condition, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok    {$label}\n";
    } else {
        $failed++;
        echo '  FAIL  ' . $label . ($detail !== '' ? " -- {$detail}" : '') . "\n";
    }
}

echo "Translation parity\n" . str_repeat('=', 60) . "\n";

$enKeys = array_keys($en);
$arKeys = array_keys($ar);
sort($enKeys);
sort($arKeys);
i18nCheck($enKeys === $arKeys, 'Arabic and English define exactly the same keys',
    'en=' . count($enKeys) . ' ar=' . count($arKeys));
i18nCheck(count($enKeys) === count(array_unique($enKeys)), 'No duplicate English keys');
i18nCheck(count($arKeys) === count(array_unique($arKeys)), 'No duplicate Arabic keys');

$used = [];
$dirs = ['views', 'app/controllers', 'app/services', 'app/repositories', 'app/middleware', 'app/helpers'];
foreach ($dirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match_all('/__\(\s*[\'"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'"]/i', $source, $matches)) {
            foreach ($matches[1] as $key) {
                if (str_ends_with($key, '_')) {
                    continue; // dynamic key family assembled with a suffix
                }
                $used[$key] = true;
            }
        }
    }
}

$missingEn = [];
$missingAr = [];
foreach (array_keys($used) as $key) {
    if (!array_key_exists($key, $en)) {
        $missingEn[] = $key;
    }
    if (!array_key_exists($key, $ar)) {
        $missingAr[] = $key;
    }
}
i18nCheck($missingEn === [], 'Every literal key used in code exists in en.php', implode(', ', $missingEn));
i18nCheck($missingAr === [], 'Every literal key used in code exists in ar.php', implode(', ', $missingAr));

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
