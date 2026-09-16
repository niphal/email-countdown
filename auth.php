<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/platform.php';
require_once __DIR__ . '/lib/mail_transport.php';

const AUTH_SESSION_KEY = 'email_timer_auth_ok';
const AUTH_SESSION_FAILS = 'email_timer_auth_fails';
const AUTH_SESSION_LOCK = 'email_timer_auth_lock_until';
const AUTH_SESSION_CSRF = 'email_timer_csrf';
const AUTH_SESSION_USER_ID = 'email_timer_user_id';
const AUTH_SESSION_USER_ROLE = 'email_timer_user_role';
const AUTH_SESSION_WORKSPACE_ID = 'email_timer_workspace_id';
const AUTH_SESSION_USER_NAME = 'email_timer_user_name';
const AUTH_ROLE_OWNER = 'owner';
const AUTH_ROLE_ADMIN = 'admin';
const AUTH_ROLE_EDITOR = 'editor';
const AUTH_ROLE_VIEWER = 'viewer';
const AUTH_LOGIN_BLOCK_VERIFY_EMAIL = 'verify_email';
const AUTH_SESSION_LOGIN_BLOCK = 'email_timer_login_block';

function auth_secrets_path(): string
{
    return APP_ROOT . '/data/secrets.php';
}

/**
 * @return array{password_hash?: string, public_base_url?: string}|null
 */
function auth_secrets(): ?array
{
    return app_secrets_array();
}

function auth_password_hash_configured(): bool
{
    $h = (string) (auth_secrets()['password_hash'] ?? '');
    return $h !== '' && str_starts_with($h, '$2');
}

/** True when dashboard password is configured (same as auth_password_hash_configured). */
function auth_is_installed(): bool
{
    return auth_password_hash_configured();
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_name('EMAILTIMERSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_is_logged_in(): bool
{
    if (empty($_SESSION[AUTH_SESSION_KEY])) {
        return false;
    }

    return auth_current_user() !== null;
}

/**
 * @return array{id:int, role:string, workspace_id:int, name:string}|null
 */
function auth_current_user(): ?array
{
    if (empty($_SESSION[AUTH_SESSION_KEY])) {
        return null;
    }
    $id = (int) ($_SESSION[AUTH_SESSION_USER_ID] ?? 0);
    $workspaceId = (int) ($_SESSION[AUTH_SESSION_WORKSPACE_ID] ?? 0);
    $role = (string) ($_SESSION[AUTH_SESSION_USER_ROLE] ?? '');
    if ($id <= 0 || $workspaceId <= 0 || $role === '') {
        return null;
    }

    return [
        'id' => $id,
        'role' => $role,
        'workspace_id' => $workspaceId,
        'name' => (string) ($_SESSION[AUTH_SESSION_USER_NAME] ?? 'User'),
    ];
}

function auth_workspace_id(): int
{
    $u = auth_current_user();

    return $u !== null ? (int) $u['workspace_id'] : 0;
}

function auth_user_id(): int
{
    $u = auth_current_user();

    return $u !== null ? (int) $u['id'] : 0;
}

function auth_role_rank(string $role): int
{
    return match ($role) {
        AUTH_ROLE_OWNER => 4,
        AUTH_ROLE_ADMIN => 3,
        AUTH_ROLE_EDITOR => 2,
        AUTH_ROLE_VIEWER => 1,
        default => 0,
    };
}

function auth_has_min_role(string $minRole): bool
{
    $u = auth_current_user();
    if ($u === null) {
        return false;
    }

    return auth_role_rank((string) $u['role']) >= auth_role_rank($minRole);
}

function auth_is_valid_role(string $role): bool
{
    return in_array($role, [AUTH_ROLE_OWNER, AUTH_ROLE_ADMIN, AUTH_ROLE_EDITOR, AUTH_ROLE_VIEWER], true);
}

function auth_can_write_dashboard(): bool
{
    return auth_current_user() !== null;
}

function auth_can_view_audit(): bool
{
    return auth_current_user() !== null;
}

function auth_can_manage_members(): bool
{
    return auth_current_user() !== null;
}

function auth_can_manage_billing(): bool
{
    return auth_current_user() !== null;
}

function auth_require_api_write(): void
{
    auth_require_api_login();
    if (!auth_can_write_dashboard()) {
        json_response(['error' => 'Forbidden'], 403);
    }
}

function auth_require_api_audit_read(): void
{
    auth_require_api_login();
    if (!auth_can_view_audit()) {
        json_response(['error' => 'Forbidden'], 403);
    }
}

function auth_require_api_members_manage(): void
{
    auth_require_api_login();
    if (!auth_can_manage_members()) {
        json_response(['error' => 'Forbidden'], 403);
    }
}

function auth_require_api_billing_manage(): void
{
    auth_require_api_login();
    if (!auth_can_manage_billing()) {
        json_response(['error' => 'Forbidden'], 403);
    }
}

function auth_csrf_token(): string
{
    if (empty($_SESSION[AUTH_SESSION_CSRF])) {
        $_SESSION[AUTH_SESSION_CSRF] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION[AUTH_SESSION_CSRF];
}

function auth_verify_csrf(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    $expected = $_SESSION[AUTH_SESSION_CSRF] ?? '';
    return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function auth_is_locked(): bool
{
    $until = (int) ($_SESSION[AUTH_SESSION_LOCK] ?? 0);

    return $until > time();
}

function auth_lock_seconds_remaining(): int
{
    $until = (int) ($_SESSION[AUTH_SESSION_LOCK] ?? 0);

    return max(0, $until - time());
}

function auth_record_failed_login(): void
{
    $fails = (int) ($_SESSION[AUTH_SESSION_FAILS] ?? 0) + 1;
    $_SESSION[AUTH_SESSION_FAILS] = $fails;
    if ($fails >= 5) {
        $_SESSION[AUTH_SESSION_LOCK] = time() + 60;
        $_SESSION[AUTH_SESSION_FAILS] = 0;
        observability_log('auth.login.locked', 'warning', ['lock_seconds' => 60]);
    }
}

function auth_clear_lock_state(): void
{
    unset($_SESSION[AUTH_SESSION_FAILS], $_SESSION[AUTH_SESSION_LOCK]);
}

function auth_set_login_block(string $reason): void
{
    $_SESSION[AUTH_SESSION_LOGIN_BLOCK] = $reason;
}

function auth_take_login_block(): ?string
{
    $v = $_SESSION[AUTH_SESSION_LOGIN_BLOCK] ?? null;
    unset($_SESSION[AUTH_SESSION_LOGIN_BLOCK]);

    return is_string($v) && $v !== '' ? $v : null;
}

function auth_attempt_login(string $password, ?string $email = null): bool
{
    if (auth_is_locked()) {
        return false;
    }
    $pdo = db();
    $emailTrim = trim((string) ($email ?? ''));

    $user = auth_resolve_user_for_login($pdo, $emailTrim !== '' ? $emailTrim : null);
    if ($user !== null && password_verify($password, (string) $user['password_hash'])) {
        if ((int) ($user['email_verified_at'] ?? 0) <= 0) {
            auth_set_login_block(AUTH_LOGIN_BLOCK_VERIFY_EMAIL);
            observability_log('auth.login.blocked_unverified', 'warning', ['user_id' => (int) $user['id']]);

            return false;
        }
        auth_login_success((int) $user['id'], (int) $user['workspace_id'], (string) $user['role'], (string) $user['name']);
        observability_log('auth.login.success', 'info', ['user_id' => (int) $user['id'], 'workspace_id' => (int) $user['workspace_id']]);

        return true;
    }

    $secrets = auth_secrets();
    $hash = (string) ($secrets['password_hash'] ?? '');
    if ($hash === '' || !str_starts_with($hash, '$2') || !password_verify($password, $hash)) {
        auth_record_failed_login();
        observability_log('auth.login.failed', 'warning', ['email_present' => $emailTrim !== '']);

        return false;
    }

    platform_seed_owner_user_from_secrets($pdo);
    $lookupEmail = $emailTrim !== '' ? $emailTrim : platform_seed_owner_email();
    $user = auth_resolve_user_for_login($pdo, $lookupEmail);
    if ($user !== null && password_verify($password, (string) $user['password_hash'])) {
        if ((int) ($user['email_verified_at'] ?? 0) <= 0) {
            auth_set_login_block(AUTH_LOGIN_BLOCK_VERIFY_EMAIL);
            observability_log('auth.login.blocked_unverified', 'warning', ['user_id' => (int) $user['id']]);

            return false;
        }
        auth_login_success((int) $user['id'], (int) $user['workspace_id'], (string) $user['role'], (string) $user['name']);
        observability_log('auth.login.success', 'info', ['user_id' => (int) $user['id'], 'workspace_id' => (int) $user['workspace_id']]);

        return true;
    }

    auth_record_failed_login();
    observability_log('auth.login.failed', 'warning', ['email_present' => $emailTrim !== '']);

    return false;
}

/**
 * @return array{id:int,workspace_id:int,role:string,name:string,password_hash:string,email_verified_at:int}|null
 */
function auth_resolve_user_for_login(PDO $pdo, ?string $email): ?array
{
    if ($email !== null && $email !== '') {
        $sql = 'SELECT u.id, u.display_name AS name, u.password_hash, COALESCE(u.email_verified_at, 0) AS email_verified_at, wm.workspace_id, wm.role
            FROM users u
            INNER JOIN workspace_members wm ON wm.user_id = u.id
            WHERE u.is_active = 1 AND LOWER(u.email) = LOWER(?)
            ORDER BY CASE wm.role WHEN \'owner\' THEN 0 WHEN \'admin\' THEN 1 WHEN \'editor\' THEN 2 ELSE 3 END, wm.workspace_id ASC
            LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    $sql = 'SELECT u.id, u.display_name AS name, u.password_hash, COALESCE(u.email_verified_at, 0) AS email_verified_at, wm.workspace_id, wm.role
        FROM users u
        INNER JOIN workspace_members wm ON wm.user_id = u.id
        WHERE u.is_active = 1
        ORDER BY CASE wm.role WHEN \'owner\' THEN 0 WHEN \'admin\' THEN 1 WHEN \'editor\' THEN 2 ELSE 3 END, u.id ASC, wm.workspace_id ASC
        LIMIT 1';
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function auth_login_success(int $userId, int $workspaceId, string $role, string $name): void
{
    auth_clear_lock_state();
    session_regenerate_id(true);
    $_SESSION[AUTH_SESSION_KEY] = true;
    $_SESSION[AUTH_SESSION_USER_ID] = $userId;
    $_SESSION[AUTH_SESSION_WORKSPACE_ID] = $workspaceId;
    $_SESSION[AUTH_SESSION_USER_ROLE] = $role;
    $_SESSION[AUTH_SESSION_USER_NAME] = $name !== '' ? $name : 'User';
    unset($_SESSION[AUTH_SESSION_CSRF]);
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Safe in-app redirect target (same host, path only). */
function auth_redirect_target(?string $next): string
{
    if ($next === null || $next === '') {
        return 'index.php';
    }
    if (!str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '://')) {
        return 'index.php';
    }

    return $next;
}

function auth_require_login_redirect(): void
{
    if (!auth_is_installed()) {
        header('Location: install.php', true, 302);
        exit;
    }
    if (auth_is_logged_in()) {
        return;
    }
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/index.php');
    header('Location: login.php?next=' . $next, true, 302);
    exit;
}

function auth_require_admin_page_redirect(): void
{
    auth_require_login_redirect();
}

function auth_require_api_login(): void
{
    if (!auth_is_installed()) {
        json_response(['error' => 'Not installed', 'install' => 'install.php'], 503);
    }
    if (auth_is_logged_in()) {
        return;
    }
    json_response(['error' => 'Unauthorized'], 401);
}

function auth_password_reset_ttl_seconds(): int
{
    return 3600;
}

function auth_password_reset_base_url(): string
{
    return app_absolute_page_url('reset_password.php');
}

function auth_email_verification_ttl_seconds(): int
{
    return 86400 * 2;
}

function auth_email_verify_base_url(): string
{
    return app_absolute_page_url('verify_email.php');
}

/**
 * @return array{ok:bool, error:string}
 */
function auth_send_email_verification(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, email, COALESCE(email_verified_at, 0) AS email_verified_at FROM users WHERE is_active = 1 AND id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($u)) {
        return ['ok' => false, 'error' => 'User not found'];
    }
    if ((int) $u['email_verified_at'] > 0) {
        return ['ok' => true, 'error' => ''];
    }
    $throttleStmt = $pdo->prepare('SELECT MAX(created_at) FROM email_verifications WHERE user_id = ? AND used_at IS NULL');
    $throttleStmt->execute([$userId]);
    $last = $throttleStmt->fetchColumn();
    if ($last !== false && $last !== null && (time() - (int) $last) < 60) {
        return ['ok' => true, 'error' => ''];
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $now = time();
    $exp = $now + auth_email_verification_ttl_seconds();
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (strlen($ip) > 64) {
        $ip = substr($ip, 0, 64);
    }

    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
    $pdo->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at, used_at, requested_ip, created_at) VALUES (?, ?, ?, NULL, ?, ?)')
        ->execute([$userId, $tokenHash, $exp, $ip, $now]);

    $link = auth_email_verify_base_url() . '?token=' . rawurlencode($token);
    $subject = 'Confirm your email';
    $plain = "Confirm your workspace account email by opening this link (valid for 48 hours):\n\n{$link}\n\nIf you did not create an account, you can ignore this message.";
    $html = '<p>Confirm your workspace account email by clicking the link below (valid for 48 hours).</p>'
        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Verify email address</a></p>'
        . '<p>If you did not create an account, you can ignore this message.</p>';

    $sent = mail_app_send((string) $u['email'], $subject, $plain, $html);
    if (!$sent['ok']) {
        observability_log('identity.email_verification.send_failed', 'error', ['user_id' => $userId, 'error' => $sent['error']]);
        $logPath = APP_ROOT . '/data/verify-email-fail.log';
        $line = '[' . gmdate('c') . '] user_id=' . $userId . ' email=' . (string) $u['email'] . ' error=' . $sent['error'] . ' link=' . $link . PHP_EOL;
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    } else {
        observability_log('identity.email_verification.sent', 'info', ['user_id' => $userId]);
    }

    return $sent;
}

function auth_request_public_email_verification_resend(string $email): void
{
    $email = trim($email);
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, COALESCE(email_verified_at, 0) AS email_verified_at FROM users WHERE is_active = 1 AND LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($u) || (int) $u['email_verified_at'] > 0) {
        return;
    }
    auth_send_email_verification($pdo, (int) $u['id']);
}

function auth_consume_email_verification_token(string $token): bool
{
    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    $pdo = db();
    $now = time();
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM email_verifications WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return false;
    }
    if (!empty($row['used_at']) || (int) $row['expires_at'] < $now) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?')->execute([$now, (int) $row['user_id']]);
        $pdo->prepare('UPDATE email_verifications SET used_at = ? WHERE id = ?')->execute([$now, (int) $row['id']]);
        $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND id <> ?')->execute([(int) $row['user_id'], (int) $row['id']]);
        $pdo->commit();
        observability_log('identity.email_verification.verified', 'info', ['user_id' => (int) $row['user_id']]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }

    return true;
}

function auth_request_password_reset(string $email): void
{
    $email = trim($email);
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE is_active = 1 AND LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($u)) {
        return;
    }

    $userId = (int) $u['id'];
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $now = time();
    $exp = $now + auth_password_reset_ttl_seconds();
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (strlen($ip) > 64) {
        $ip = substr($ip, 0, 64);
    }

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
    $ins = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, requested_ip, created_at) VALUES (?, ?, ?, NULL, ?, ?)');
    $ins->execute([$userId, $tokenHash, $exp, $ip, $now]);

    $link = auth_password_reset_base_url() . '?token=' . rawurlencode($token);
    $subject = 'Password reset link';
    $body = "Use this link to reset your password (valid for 60 minutes):\n\n" . $link . "\n\nIf you did not request this, you can ignore this email.";
    $html = '<p>Use this link to reset your password (valid for 60 minutes):</p>'
        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Reset password</a></p>'
        . '<p>If you did not request this, you can ignore this email.</p>';
    $sent = mail_app_send((string) $u['email'], $subject, $body, $html);
    observability_log($sent['ok'] ? 'identity.password_reset.sent' : 'identity.password_reset.send_failed', $sent['ok'] ? 'info' : 'error', [
        'user_id' => $userId,
        'error' => $sent['error'],
    ]);

    $logPath = APP_ROOT . '/data/reset-links.log';
    $line = '[' . gmdate('c') . '] ' . (string) $u['email'] . ' mail_ok=' . ($sent['ok'] ? '1' : '0') . ' err=' . $sent['error'] . ' ' . $link . PHP_EOL;
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

function auth_consume_password_reset_token(string $token, string $newPassword): bool
{
    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    if (strlen($newPassword) < 8) {
        return false;
    }
    $pdo = db();
    $now = time();
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return false;
    }
    if (!empty($row['used_at']) || (int) $row['expires_at'] < $now) {
        return false;
    }

    $pwHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($pwHash === false) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1, email_verified_at = CASE WHEN COALESCE(email_verified_at,0) <= 0 THEN ? ELSE email_verified_at END WHERE id = ?')
            ->execute([$pwHash, $now, (int) $row['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE id = ?')->execute([$now, (int) $row['id']]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')->execute([(int) $row['user_id'], (int) $row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }

    return true;
}
