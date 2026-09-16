<?php

declare(strict_types=1);

function observability_request_id(): string
{
    static $rid = null;
    if (is_string($rid) && $rid !== '') {
        return $rid;
    }
    $incoming = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    if ($incoming !== '' && preg_match('/^[a-zA-Z0-9._:-]{6,128}$/', $incoming)) {
        $rid = $incoming;
    } else {
        $rid = bin2hex(random_bytes(8));
    }

    return $rid;
}

function observability_events_path(): string
{
    return APP_ROOT . '/data/events.jsonl';
}

/**
 * @param array<string,mixed> $fields
 */
function observability_log(string $event, string $level = 'info', array $fields = []): void
{
    $event = trim($event);
    if ($event === '') {
        return;
    }
    $level = strtolower(trim($level));
    if (!in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
        $level = 'info';
    }
    $dir = APP_ROOT . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $row = [
        'ts' => gmdate('c'),
        'level' => $level,
        'event' => $event,
        'request_id' => observability_request_id(),
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'path' => (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
        'ip' => observability_truncate((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 64),
        'user_id' => function_exists('auth_user_id') ? auth_user_id() : 0,
        'workspace_id' => function_exists('auth_workspace_id') ? auth_workspace_id() : 0,
        'fields' => observability_sanitize($fields),
    ];

    try {
        $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return;
    }
    @file_put_contents(observability_events_path(), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * @param mixed $value
 * @return mixed
 */
function observability_sanitize($value)
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $key = is_string($k) ? $k : (string) $k;
            if (preg_match('/(pass|password|secret|token|sig|key|hash|csrf)/i', $key)) {
                $out[$key] = '[redacted]';
            } else {
                $out[$key] = observability_sanitize($v);
            }
        }

        return $out;
    }
    if (is_string($value)) {
        return observability_truncate($value, 700);
    }
    if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
        return $value;
    }

    return observability_truncate((string) $value, 300);
}

function observability_truncate(string $value, int $max): string
{
    if ($max < 1 || strlen($value) <= $max) {
        return $value;
    }

    return substr($value, 0, $max) . '...';
}

function observability_should_sample(float $rate): bool
{
    if ($rate <= 0.0) {
        return false;
    }
    if ($rate >= 1.0) {
        return true;
    }

    return random_int(1, 1000000) <= (int) round($rate * 1000000);
}

function observability_config_float(string $key, float $default, float $min = 0.0, float $max = 1.0): float
{
    $cfg = function_exists('app_secrets_array') ? app_secrets_array() : null;
    $value = is_array($cfg) && array_key_exists($key, $cfg) ? (float) $cfg[$key] : $default;

    return max($min, min($max, $value));
}

/**
 * @return array<int,array<string,mixed>>
 */
function observability_recent_events(int $limit = 50, string $minLevel = ''): array
{
    $limit = max(1, min(200, $limit));
    $path = observability_events_path();
    if (!is_readable($path)) {
        return [];
    }
    $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    $minRank = array_key_exists($minLevel, $levels) ? $levels[$minLevel] : 0;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $events = [];
    for ($i = count($lines) - 1; $i >= 0 && count($events) < $limit; $i--) {
        $decoded = json_decode((string) $lines[$i], true);
        if (!is_array($decoded)) {
            continue;
        }
        $rank = $levels[(string) ($decoded['level'] ?? 'info')] ?? 1;
        if ($rank < $minRank) {
            continue;
        }
        $events[] = $decoded;
    }

    return $events;
}

/**
 * @return array<string,mixed>
 */
function observability_health(): array
{
    $dataDir = APP_ROOT . '/data';
    $eventsPath = observability_events_path();
    $checks = [
        'php' => [
            'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'detail' => PHP_VERSION,
        ],
        'data_writable' => [
            'ok' => is_dir($dataDir) && is_writable($dataDir),
            'detail' => is_dir($dataDir) ? (is_writable($dataDir) ? 'writable' : 'not writable') : 'missing',
        ],
        'sqlite_db' => [
            'ok' => is_file(DB_PATH) && is_readable(DB_PATH),
            'detail' => is_file(DB_PATH) ? 'present' : 'not created yet',
        ],
        'events_log' => [
            'ok' => is_writable(dirname($eventsPath)) && (!is_file($eventsPath) || is_writable($eventsPath)),
            'detail' => is_file($eventsPath) ? 'present' : 'not created yet',
        ],
        'gd' => [
            'ok' => function_exists('imagecreatetruecolor'),
            'detail' => function_exists('imagecreatetruecolor') ? 'enabled' : 'missing',
        ],
        'openssl' => [
            'ok' => extension_loaded('openssl'),
            'detail' => extension_loaded('openssl') ? 'loaded' : 'missing (SMTP TLS may fail)',
        ],
    ];
    if (function_exists('mail_app_config')) {
        $mail = mail_app_config();
        $transport = (string) ($mail['transport'] ?? 'log');
        $checks['mail_transport'] = [
            'ok' => $transport === 'log' || ((string) ($mail['smtp_host'] ?? '') !== '' && (string) ($mail['mail_from_email'] ?? '') !== ''),
            'detail' => $transport,
        ];
    }

    $ok = true;
    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            $ok = false;
            break;
        }
    }

    return [
        'ok' => $ok,
        'request_id' => observability_request_id(),
        'checks' => $checks,
    ];
}
