<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/timer_fonts.php';
require_once dirname(__DIR__) . '/lib/timer_layouts.php';
require_once dirname(__DIR__) . '/auth.php';

auth_start_session();
auth_require_api_login();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stmt = db()->query('SELECT id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, created_at FROM timers ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['font_key'] = timer_normalize_font_key((string) ($row['font_key'] ?? 'noto_sans_bold'));
            $row['layout_key'] = timer_normalize_layout_key((string) ($row['layout_key'] ?? 'segmented_pills'));
            $row['dynamic_sig'] = app_timer_signature_for_id((string) ($row['id'] ?? ''));
        }
        unset($row);
        json_response(['timers' => $rows]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $name = trim((string) ($body['name'] ?? ''));
        $endsAt = (int) ($body['ends_at'] ?? 0);
        if ($name === '' || $endsAt <= 0) {
            json_response(['error' => 'name and ends_at (unix seconds) required'], 422);
        }
        $id = bin2hex(random_bytes(16));
        $bg = sanitize_hex((string) ($body['bg_color'] ?? '#1a1a2e'), '#1a1a2e');
        $fg = sanitize_hex((string) ($body['text_color'] ?? '#eaeaea'), '#eaeaea');
        $ac = sanitize_hex((string) ($body['accent_color'] ?? '#e94560'), '#e94560');
        $label = mb_substr((string) ($body['label'] ?? ''), 0, 120);
        $w = clamp_int((int) ($body['width'] ?? 560), 200, 900);
        $h = clamp_int((int) ($body['height'] ?? 140), 80, 400);
        $fontKey = timer_normalize_font_key((string) ($body['font_key'] ?? 'noto_sans_bold'));
        $fontSizeMain = clamp_int((int) ($body['font_size_main'] ?? 32), 14, 72);
        $layoutKey = timer_normalize_layout_key((string) ($body['layout_key'] ?? 'segmented_pills'));
        $now = time();
        $stmt = db()->prepare('INSERT INTO timers (id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$id, $name, $endsAt, $bg, $fg, $ac, $label, $w, $h, $fontKey, $fontSizeMain, $layoutKey, $now]);
        json_response(['id' => $id]);
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!is_valid_id($id)) {
            json_response(['error' => 'invalid id'], 422);
        }
        $stmt = db()->prepare('DELETE FROM timers WHERE id = ?');
        $stmt->execute([$id]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'method not allowed'], 405);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}

function sanitize_hex(string $s, string $fallback): string
{
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $s)) {
        return '#' . strtolower(substr($s, 1));
    }
    return $fallback;
}

function clamp_int(int $v, int $min, int $max): int
{
    return max($min, min($max, $v));
}

function is_valid_id(string $id): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
}
