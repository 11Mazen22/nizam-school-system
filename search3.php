<?php
function rglob($pattern, $flags = 0) {
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern)."/*", GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir."/".basename($pattern), $flags));
    }
    return $files;
}
$files = rglob("*.php");
$files = array_merge($files, rglob("*.json"), rglob("*.html"), rglob("*.md"));
foreach ($files as $file) {
    $lines = file($file);
    foreach ($lines as $i => $line) {
        if (mb_stripos($line, "????") !== false || mb_stripos($line, "Nizam") !== false) {
            echo $file . ":" . ($i+1) . " - " . trim($line) . "\n";
        }
    }
}

