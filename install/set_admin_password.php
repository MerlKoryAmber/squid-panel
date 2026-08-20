<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Core/Database.php';

$password = getenv('SPM_ADMIN_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDERR, "SPM_ADMIN_PASSWORD is empty\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Admin password must be at least 8 characters\n");
    exit(1);
}

Database::init();
$hash = password_hash($password, PASSWORD_BCRYPT);
Database::query(
    "UPDATE users SET password_hash = ? WHERE username = ?",
    [$hash, 'admin']
);

echo "Admin password updated.\n";
