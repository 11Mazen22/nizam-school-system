<?php
$files = glob("lang/*.php");
foreach ($files as $file) {
    $lines = file($file);
    foreach ($lines as $i => $line) {
        if (mb_stripos($line, "????") !== false || mb_stripos($line, "Nizam") !== false) {
            echo $file . ":" . ($i+1) . " - " . trim($line) . "\n";
        }
    }
}

