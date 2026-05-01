<?php

declare(strict_types=1);

const APP_ROOT = __DIR__;
const DB_PATH = APP_ROOT . '/data/app.db';

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
        created_at INTEGER NOT NULL
    )');
    return $pdo;
}

function json_response(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
