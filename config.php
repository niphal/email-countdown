<?php

declare(strict_types=1);

const APP_ROOT = __DIR__;
const DB_PATH = APP_ROOT . '/data/app.db';

/**
 * Root-relative timer image URL prefix, e.g. "/email_timer/timer.php?id=".
 * Derived from SCRIPT_NAME only (no HTTP_HOST / scheme). Works when the request
 * is handled under /api/* by normalizing to the parent app directory first.
 */
function app_timer_url_prefix(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    if (str_contains($script, '/api/')) {
        $appPath = dirname(dirname($script));
    } else {
        $appPath = dirname($script);
    }
    $appPath = str_replace('\\', '/', (string) $appPath);
    $appPath = rtrim($appPath, '/');
    if ($appPath === '' || $appPath === '.' || $appPath === '/') {
        $timerPath = '/timer.php';
    } else {
        $timerPath = $appPath . '/timer.php';
    }
    if ($timerPath[0] !== '/') {
        $timerPath = '/' . ltrim($timerPath, '/');
    }

    return $timerPath . '?id=';
}

/**
 * @return array{password_hash?: string, public_base_url?: string, timer_signing_key?: string}|null
 */
function app_secrets_array(): ?array
{
    $path = APP_ROOT . '/data/secrets.php';
    if (!is_readable($path)) {
        return null;
    }
    $data = require $path;

    return is_array($data) ? $data : null;
}

/** Origin for absolute email image URLs (no trailing slash). */
function app_embed_origin(): string
{
    $cfg = app_secrets_array();
    $custom = isset($cfg['public_base_url']) ? trim((string) $cfg['public_base_url']) : '';
    if ($custom !== '') {
        return rtrim($custom, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    return ($https ? 'https' : 'http') . '://' . $host;
}

/** Full URL prefix for pasted email HTML; falls back to root-relative if no host. */
function app_timer_embed_src_prefix(): string
{
    $rel = app_timer_url_prefix();
    $origin = app_embed_origin();
    if ($origin === '') {
        return $rel;
    }

    return $origin . $rel;
}

/**
 * PHP source for data/secrets.php.
 *
 * - password hash required
 * - public_base_url optional
 * - timer_signing_key optional (hex)
 */
function app_format_secrets_php(string $passwordHash, ?string $publicBaseUrl = null, ?string $timerSigningKey = null): string
{
    $pub = $publicBaseUrl !== null ? trim($publicBaseUrl) : '';
    $sign = $timerSigningKey !== null ? trim($timerSigningKey) : '';
    $lines = [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'return [',
        '    \'password_hash\' => ' . var_export($passwordHash, true) . ',',
    ];
    if ($pub !== '') {
        $lines[] = '    \'public_base_url\' => ' . var_export($pub, true) . ',';
    }
    if ($sign !== '') {
        $lines[] = '    \'timer_signing_key\' => ' . var_export($sign, true) . ',';
    }
    $lines[] = '];';
    $lines[] = '';

    return implode("\n", $lines);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS timers (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        ends_at INTEGER NOT NULL,
        bg_color TEXT NOT NULL DEFAULT "#1a1a2e",
        text_color TEXT NOT NULL DEFAULT "#eaeaea",
        accent_color TEXT NOT NULL DEFAULT "#e94560",
        label TEXT NOT NULL DEFAULT "",
        width INTEGER NOT NULL DEFAULT 560,
        height INTEGER NOT NULL DEFAULT 140,
        font_key TEXT NOT NULL DEFAULT "noto_sans_bold",
        font_size_main INTEGER NOT NULL DEFAULT 32,
        layout_key TEXT NOT NULL DEFAULT "segmented_pills",
        created_at INTEGER NOT NULL
    )');
    db_migrate_timers($pdo);

    return $pdo;
}

function db_migrate_timers(PDO $pdo): void
{
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(timers)') as $row) {
        $cols[(string) $row['name']] = true;
    }
    if (!isset($cols['font_key'])) {
        $pdo->exec('ALTER TABLE timers ADD COLUMN font_key TEXT NOT NULL DEFAULT "noto_sans_bold"');
    }
    if (!isset($cols['font_size_main'])) {
        $pdo->exec('ALTER TABLE timers ADD COLUMN font_size_main INTEGER NOT NULL DEFAULT 32');
    }
    if (!isset($cols['layout_key'])) {
        $pdo->exec('ALTER TABLE timers ADD COLUMN layout_key TEXT NOT NULL DEFAULT "segmented_pills"');
    }
    if (isset($cols['font_key'])) {
        $pdo->exec("UPDATE timers SET font_key = 'noto_sans_bold' WHERE font_key IN ('system','dejavu_bold','segoe_bold','arial_bold')");
        $pdo->exec("UPDATE timers SET font_key = 'noto_sans' WHERE font_key = 'dejavu_book'");
    }
}

function json_response(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function app_timer_signing_key(): string
{
    $cfg = app_secrets_array();
    if (!is_array($cfg)) {
        return '';
    }
    $raw = trim((string) ($cfg['timer_signing_key'] ?? ''));
    if ($raw === '' || !preg_match('/^[a-f0-9]{32,128}$/', strtolower($raw))) {
        return '';
    }

    return strtolower($raw);
}

function app_timer_signature_for_id(string $id): string
{
    $key = app_timer_signing_key();
    if ($key === '' || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        return '';
    }

    return hash_hmac('sha256', $id, $key);
}

function app_timer_has_valid_signature(string $id, ?string $sig): bool
{
    $expected = app_timer_signature_for_id($id);
    if ($expected === '') {
        return false;
    }
    $given = strtolower(trim((string) ($sig ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $given)) {
        return false;
    }

    return hash_equals($expected, $given);
}
