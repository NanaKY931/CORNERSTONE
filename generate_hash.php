<?php
// Generate password hash for 'Demo2025!'
$password = 'Demo2025!';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Password: $password\n";
echo "Hash: $hash\n";
?>
