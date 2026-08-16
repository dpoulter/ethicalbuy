<?php

/**
 * Generates the ADMIN_PASSWORD_HASH value for a password.
 *
 *   php dev/admin-password.php 'the password you want'
 *
 * The plaintext password is never stored anywhere: only the hash below goes
 * into your environment configuration.
 */

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? "";

if ($password === "")
{
    fwrite(STDERR, "Usage: php dev/admin-password.php 'your password'\n");
    fwrite(STDERR, "Quote the password so the shell doesn't mangle it.\n");
    exit(1);
}

if (strlen($password) < 12)
{
    fwrite(STDERR, "Refusing: use at least 12 characters for an admin password.\n");
    exit(1);
}

echo "ADMIN_PASSWORD_HASH='" . password_hash($password, PASSWORD_DEFAULT) . "'\n";
