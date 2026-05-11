<?php
/**
 * Create or update a gov.cabnet.app operator login user.
 *
 * Usage:
 *   php /home/cabnet/gov.cabnet.app_app/cli/create_ops_user.php --username=andreas --email=name@example.com --display-name="Andreas" --role=admin
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

$bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
if (!is_file($bootstrap)) {
    fwrite(STDERR, "Private app bootstrap not found: {$bootstrap}\n");
    exit(1);
}

function usage(): void
{
    echo "Usage:\n";
    echo "  php create_ops_user.php --username=USER [--email=EMAIL] [--display-name=NAME] [--role=admin|operator|viewer]\n";
}

function read_secret(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $hasStty = trim((string)shell_exec('command -v stty 2>/dev/null')) !== '';
    if ($hasStty) {
        shell_exec('stty -echo');
    }
    $value = trim((string)fgets(STDIN));
    if ($hasStty) {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, "\n");
    return $value;
}

$options = getopt('', ['username:', 'email::', 'display-name::', 'role::']);
$username = trim((string)($options['username'] ?? ''));
$email = trim((string)($options['email'] ?? ''));
$displayName = trim((string)($options['display-name'] ?? $username));
$role = trim((string)($options['role'] ?? 'admin'));

if ($username === '') {
    usage();
    exit(1);
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
    fwrite(STDERR, "Username must be 3-80 characters and use letters, numbers, dot, dash, or underscore only.\n");
    exit(1);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

if (!in_array($role, ['admin', 'operator', 'viewer'], true)) {
    fwrite(STDERR, "Role must be admin, operator, or viewer.\n");
    exit(1);
}

$password = read_secret('Password: ');
$confirm = read_secret('Confirm password: ');

if ($password === '' || strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}
if (!hash_equals($password, $confirm)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

try {
    $ctx = require $bootstrap;
    $db = $ctx['db']->connection();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $emailValue = $email !== '' ? $email : null;
    $displayName = $displayName !== '' ? $displayName : $username;

    $sql = "INSERT INTO ops_users (username, email, display_name, role, password_hash, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                display_name = VALUES(display_name),
                role = VALUES(role),
                password_hash = VALUES(password_hash),
                is_active = 1,
                updated_at = NOW()";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('sssss', $username, $emailValue, $displayName, $role, $hash);
    $stmt->execute();

    echo "OK: operator user created/updated: {$username} ({$role})\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
