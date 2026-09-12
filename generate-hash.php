<?php
/**
 * Generate password hash for Nizam system
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nSQL Query:\n";
echo "UPDATE users SET password_hash = '$hash' WHERE id = 1;\n";
