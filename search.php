<?php
$files = ["lang/en.php", "lang/ar.php", "views/auth/login.php", "views/layout/start.php", "views/layout/setup-start.php", "packaging/installer.iss"];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $lines = file($file);
    foreach ($lines as $i => $line) {
        if (mb_stripos($line, "????") !== false || mb_stripos($line, "Nizam") !== false) {
            echo $file . ":" . ($i+1) . " - " . trim($line) . "\n";
        }
    }
}

