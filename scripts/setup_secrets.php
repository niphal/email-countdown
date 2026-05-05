<?php

declare(strict_types=1);

/**
 * First-time auth setup: creates data/secrets.php from the example and writes password_hash.
 * It also preserves or creates timer_signing_key for signed dynamic timer URLs.
 * For a browser wizard, open install.php instead.
 *
 * Usage (from project root):
 *   php scripts/setup_secrets.php "your-strong-password"
 *   php scripts/setup_secrets.php --force "new-password"   # replace existing hash
 *
 * Interactive (password typed in the terminal):
 *   php scripts/setup_secrets.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config.php';

$root = dirname(__DIR__);
$example = $root . '/data/secrets.example.php';
$target = $root . '/data/secrets.php';

$force = false;
$password = null;
for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--force') {
        $force = true;
        continue;
    }
    if ($password === null) {
        $password = $argv[$i];
    }
}

if ($password === null) {
    fwrite(STDERR, "Enter dashboard password: ");
    $password = rtrim((string) fgets(STDIN), "\r\n");
    fwrite(STDERR, 'Repeat password: ');
    $repeat = rtrim((string) fgets(STDIN), "\r\n");
    if ($password === '' || $password !== $repeat) {
        fwrite(STDERR, "Passwords empty or do not match.\n");
        exit(1);
    }
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Use at least 8 characters.\n");
    exit(1);
}

if (is_file($target)) {
    /** @var mixed $cfg */
    $cfg = require $target;
    $existing = is_array($cfg) ? (string) ($cfg['password_hash'] ?? '') : '';
    if ($existing !== '' && str_starts_with($existing, '$2') && !$force) {
        fwrite(STDERR, "data/secrets.php already has a password_hash. Re-run with --force to replace it.\n");
        exit(1);
    }
} else {
    if (!is_file($example)) {
        fwrite(STDERR, "Missing {$example}; cannot create secrets.php.\n");
        exit(1);
    }
    if (!@copy($example, $target)) {
        fwrite(STDERR, "Could not copy to {$target}. Check permissions.\n");
        exit(1);
    }
    fwrite(STDOUT, "Copied secrets.example.php → data/secrets.php\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "password_hash() failed.\n");
    exit(1);
}

$publicKeep = '';
$signingKeep = '';
if (is_file($target)) {
    $old = app_secrets_array();
    if (is_array($old) && !empty($old['public_base_url'])) {
        $publicKeep = trim((string) $old['public_base_url']);
    }
    if (is_array($old) && !empty($old['timer_signing_key'])) {
        $signingKeep = strtolower(trim((string) $old['timer_signing_key']));
    }
}

if (!preg_match('/^[a-f0-9]{64}$/', $signingKeep)) {
    $signingKeep = bin2hex(random_bytes(32));
}

$body = app_format_secrets_php($hash, $publicKeep !== '' ? $publicKeep : null, $signingKeep);

if (file_put_contents($target, $body) === false) {
    fwrite(STDERR, "Could not write {$target}\n");
    exit(1);
}

fwrite(STDOUT, "Updated password_hash in {$target}\n");
fwrite(STDOUT, "Reload login.php in your browser and sign in.\n");
exit(0);
