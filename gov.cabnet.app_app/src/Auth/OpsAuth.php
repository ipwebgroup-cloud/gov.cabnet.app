<?php
/**
 * gov.cabnet.app — Operator session authentication.
 *
 * Plain PHP + mysqli. No framework, no Composer dependency.
 */

declare(strict_types=1);

namespace Bridge\Auth;

use mysqli;
use Throwable;

final class OpsAuth
{
    public const VERSION = 'v1.0.0-ops-login';

    private mysqli $db;
    /** @var array<string,mixed> */
    private array $options;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(mysqli $db, array $options = [])
    {
        $this->db = $db;
        $this->options = array_merge([
            'session_name' => 'gov_cabnet_ops_session',
            'login_path' => '/ops/login.php',
            'logout_path' => '/ops/logout.php',
            'after_login_path' => '/ops/pre-ride-email-tool.php',
            'max_failed_attempts' => 10,
            'failed_window_minutes' => 15,
        ], $options);

        $this->startSession();
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = (string)$this->options['session_name'];
        if ($name !== '') {
            session_name($name);
        }

        $secure = $this->isHttps();
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['ops_user']) && is_array($_SESSION['ops_user']) && !empty($_SESSION['ops_user']['id']);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function user(): ?array
    {
        return $this->isLoggedIn() ? $_SESSION['ops_user'] : null;
    }

    public function userId(): ?int
    {
        $user = $this->user();
        return $user ? (int)$user['id'] : null;
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['ops_csrf']) || !is_string($_SESSION['ops_csrf'])) {
            $_SESSION['ops_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['ops_csrf'];
    }

    public function validateCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['ops_csrf'])
            && is_string($_SESSION['ops_csrf'])
            && hash_equals($_SESSION['ops_csrf'], $token);
    }

    public function requireLogin(string $mode = 'redirect'): void
    {
        if ($this->isLoggedIn()) {
            return;
        }

        $path = $this->currentRequestPath();
        $_SESSION['ops_after_login'] = $path;

        if ($mode === 'json') {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Authentication required.',
                'login_url' => (string)$this->options['login_path'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $loginPath = (string)$this->options['login_path'];
        header('Location: ' . $loginPath . '?next=' . rawurlencode($path), true, 302);
        exit;
    }

    /**
     * @return array{ok:bool,error:string,user?:array<string,mixed>}
     */
    public function login(string $login, string $password): array
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            $this->recordLoginAttempt($login, false, 'missing_credentials');
            return ['ok' => false, 'error' => 'Please enter both username/email and password.'];
        }

        if ($this->isRateLimited($login)) {
            $this->recordLoginAttempt($login, false, 'rate_limited');
            return ['ok' => false, 'error' => 'Too many failed login attempts. Please wait a few minutes and try again.'];
        }

        $user = $this->findUser($login);
        if (!$user || empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
            $this->recordLoginAttempt($login, false, 'invalid_credentials');
            return ['ok' => false, 'error' => 'Invalid username/email or password.'];
        }

        if ((int)($user['is_active'] ?? 0) !== 1) {
            $this->recordLoginAttempt($login, false, 'inactive_user');
            return ['ok' => false, 'error' => 'This user account is inactive.'];
        }

        session_regenerate_id(true);
        $_SESSION['ops_user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'email' => (string)($user['email'] ?? ''),
            'display_name' => (string)($user['display_name'] ?? $user['username']),
            'role' => (string)($user['role'] ?? 'operator'),
            'logged_in_at' => date('c'),
        ];
        unset($_SESSION['ops_csrf']);
        $this->csrfToken();

        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            $this->updatePasswordHash((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $this->markLastLogin((int)$user['id']);
        $this->recordLoginAttempt($login, true, 'login_ok', (int)$user['id']);
        $this->audit((int)$user['id'], 'login');

        return ['ok' => true, 'error' => '', 'user' => $_SESSION['ops_user']];
    }

    public function logout(): void
    {
        $userId = $this->userId();
        if ($userId !== null) {
            $this->audit($userId, 'logout');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function afterLoginPath(?string $requested = null): string
    {
        $candidate = $requested ?: (string)($_SESSION['ops_after_login'] ?? '');
        unset($_SESSION['ops_after_login']);

        if ($this->isSafeLocalPath($candidate)) {
            return $candidate;
        }

        return (string)$this->options['after_login_path'];
    }

    public function isInternalKeyAllowed(?string $provided, ?string $expected): bool
    {
        $provided = is_string($provided) ? trim($provided) : '';
        $expected = is_string($expected) ? trim($expected) : '';
        return $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }

    public static function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findUser(string $login): ?array
    {
        try {
            $sql = 'SELECT id, username, email, display_name, role, password_hash, is_active FROM ops_users WHERE username = ? OR email = ? LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('ss', $login, $login);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isRateLimited(string $login): bool
    {
        try {
            $window = max(1, (int)$this->options['failed_window_minutes']);
            $max = max(3, (int)$this->options['max_failed_attempts']);
            $ip = $this->clientIp();
            $sql = "SELECT COUNT(*) AS c
                    FROM ops_login_attempts
                    WHERE success = 0
                      AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                      AND (login_name = ? OR ip_address = ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('iss', $window, $login, $ip);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (int)($row['c'] ?? 0) >= $max;
        } catch (Throwable) {
            return false;
        }
    }

    private function recordLoginAttempt(string $login, bool $success, string $reason, ?int $userId = null): void
    {
        try {
            $ip = $this->clientIp();
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $ok = $success ? 1 : 0;
            $sql = 'INSERT INTO ops_login_attempts (user_id, login_name, success, reason, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('isisss', $userId, $login, $ok, $reason, $ip, $ua);
            $stmt->execute();
        } catch (Throwable) {
            // Authentication must not fatal if the audit table is missing during staged rollout.
        }
    }

    private function markLastLogin(int $userId): void
    {
        try {
            $ip = $this->clientIp();
            $sql = 'UPDATE ops_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ? LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('si', $ip, $userId);
            $stmt->execute();
        } catch (Throwable) {
        }
    }

    private function updatePasswordHash(int $userId, string $hash): void
    {
        try {
            $sql = 'UPDATE ops_users SET password_hash = ? WHERE id = ? LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
        } catch (Throwable) {
        }
    }

    private function audit(int $userId, string $event): void
    {
        try {
            $ip = $this->clientIp();
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $sql = 'INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, NOW())';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('isss', $userId, $event, $ip, $ua);
            $stmt->execute();
        } catch (Throwable) {
        }
    }

    private function currentRequestPath(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/ops/index.php');
        $path = parse_url($uri, PHP_URL_PATH);
        $query = parse_url($uri, PHP_URL_QUERY);
        $path = is_string($path) && $path !== '' ? $path : '/ops/index.php';
        return is_string($query) && $query !== '' ? $path . '?' . $query : $path;
    }

    private function isSafeLocalPath(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        if (str_starts_with($path, '//')) {
            return false;
        }
        $lower = strtolower($path);
        if (str_starts_with($lower, '/ops/login.php') || str_starts_with($lower, '/ops/logout.php')) {
            return false;
        }
        return true;
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return substr($value, 0, 45);
            }
        }
        return '';
    }
}
