<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/platform.php';

const AUTH_SESSION_KEY = 'email_timer_auth_ok';
const AUTH_SESSION_FAILS = 'email_timer_auth_fails';
const AUTH_SESSION_LOCK = 'email_timer_auth_lock_until';
const AUTH_SESSION_CSRF = 'email_timer_csrf';
const AUTH_SESSION_USER_ID = 'email_timer_user_id';
const AUTH_SESSION_USER_ROLE = 'email_timer_user_role';
const AUTH_SESSION_WORKSPACE_ID = 'email_timer_workspace_id';
const AUTH_SESSION_USER_NAME = 'email_timer_user_name';

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

function auth_can_write_dashboard(): bool
{
    $u = auth_current_user();
    if ($u === null) {
        return false;
    }

    return in_array($u['role'], ['owner', 'admin', 'editor'], true);
}

function auth_can_view_audit(): bool
{
    return auth_can_write_dashboard();
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
    }
}

function auth_clear_lock_state(): void
{
    unset($_SESSION[AUTH_SESSION_FAILS], $_SESSION[AUTH_SESSION_LOCK]);
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
        auth_login_success((int) $user['id'], (int) $user['workspace_id'], (string) $user['role'], (string) $user['name']);

        return true;
    }

    $secrets = auth_secrets();
    $hash = (string) ($secrets['password_hash'] ?? '');
    if ($hash === '' || !str_starts_with($hash, '$2') || !password_verify($password, $hash)) {
        auth_record_failed_login();

        return false;
    }

    platform_seed_owner_user_from_secrets($pdo);
    $lookupEmail = $emailTrim !== '' ? $emailTrim : platform_seed_owner_email();
    $user = auth_resolve_user_for_login($pdo, $lookupEmail);
    if ($user !== null && password_verify($password, (string) $user['password_hash'])) {
        auth_login_success((int) $user['id'], (int) $user['workspace_id'], (string) $user['role'], (string) $user['name']);

        return true;
    }

    auth_record_failed_login();

    return false;
}

/**
 * @return array{id:int,workspace_id:int,role:string,name:string,password_hash:string}|null
 */
function auth_resolve_user_for_login(PDO $pdo, ?string $email): ?array
{
    if ($email !== null && $email !== '') {
        $sql = 'SELECT u.id, u.display_name AS name, u.password_hash, wm.workspace_id, wm.role
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

    $sql = 'SELECT u.id, u.display_name AS name, u.password_hash, wm.workspace_id, wm.role
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
