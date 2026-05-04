<?php

declare(strict_types=1);

const AUTH_SESSION_KEY = 'email_timer_auth_ok';
const AUTH_SESSION_FAILS = 'email_timer_auth_fails';
const AUTH_SESSION_LOCK = 'email_timer_auth_lock_until';
const AUTH_SESSION_CSRF = 'email_timer_csrf';

function auth_secrets_path(): string
{
    return APP_ROOT . '/data/secrets.php';
}

/**
 * @return array{password_hash?: string}|null
 */
function auth_secrets(): ?array
{
    $path = auth_secrets_path();
    if (!is_readable($path)) {
        return null;
    }
    $data = require $path;
    return is_array($data) ? $data : null;
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
    return !empty($_SESSION[AUTH_SESSION_KEY]);
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

function auth_attempt_login(string $password): bool
{
    if (auth_is_locked()) {
        return false;
    }
    $secrets = auth_secrets();
    $hash = (string) ($secrets['password_hash'] ?? '');
    if ($hash === '' || !str_starts_with($hash, '$2')) {
        return false;
    }
    if (!password_verify($password, $hash)) {
        auth_record_failed_login();

        return false;
    }
    auth_clear_lock_state();
    session_regenerate_id(true);
    $_SESSION[AUTH_SESSION_KEY] = true;
    unset($_SESSION[AUTH_SESSION_CSRF]);

    return true;
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
