<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/timer_fonts.php';
require_once dirname(__DIR__) . '/lib/timer_layouts.php';
require_once dirname(__DIR__) . '/lib/monetization.php';
require_once dirname(__DIR__) . '/auth.php';

auth_start_session();
auth_require_api_login();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $workspaceId = auth_workspace_id();
    if ($workspaceId < 1) {
        json_response(['error' => 'No active workspace'], 403);
    }

    if ($method === 'GET') {
        $stmt = db()->prepare('SELECT id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, created_at FROM timers WHERE workspace_id = ? ORDER BY created_at DESC');
        $stmt->execute([$workspaceId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['font_key'] = timer_normalize_font_key((string) ($row['font_key'] ?? 'noto_sans_bold'));
            $row['layout_key'] = timer_normalize_layout_key((string) ($row['layout_key'] ?? 'segmented_pills'));
            $row['dynamic_sig'] = app_timer_signature_for_id((string) ($row['id'] ?? ''));
        }
        unset($row);
        $ent = billing_workspace_entitlements(db(), $workspaceId);
        json_response(['timers' => $rows, 'entitlements' => $ent]);
    }

    if ($method === 'POST') {
        auth_require_api_write();
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
        $pdo = db();
        $ent = billing_assert_timer_create_allowed($pdo, $workspaceId, $layoutKey, $fontKey);
        $stmt = $pdo->prepare('INSERT INTO timers (id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, workspace_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$id, $name, $endsAt, $bg, $fg, $ac, $label, $w, $h, $fontKey, $fontSizeMain, $layoutKey, $workspaceId, $now]);
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'timer.created', 'timer', $id, ['name' => $name, 'ends_at' => $endsAt, 'plan' => $ent['plan_key']]);
        json_response(['id' => $id]);
    }

    if ($method === 'PUT') {
        auth_require_api_write();
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $id = (string) ($body['id'] ?? '');
        if (!is_valid_id($id)) {
            json_response(['error' => 'invalid id'], 422);
        }
        $name = trim((string) ($body['name'] ?? ''));
        $endsAt = (int) ($body['ends_at'] ?? 0);
        if ($name === '' || $endsAt <= 0) {
            json_response(['error' => 'name and ends_at (unix seconds) required'], 422);
        }
        $bg = sanitize_hex((string) ($body['bg_color'] ?? '#1a1a2e'), '#1a1a2e');
        $fg = sanitize_hex((string) ($body['text_color'] ?? '#eaeaea'), '#eaeaea');
        $ac = sanitize_hex((string) ($body['accent_color'] ?? '#e94560'), '#e94560');
        $label = mb_substr((string) ($body['label'] ?? ''), 0, 120);
        $w = clamp_int((int) ($body['width'] ?? 560), 200, 900);
        $h = clamp_int((int) ($body['height'] ?? 140), 80, 400);
        $fontKey = timer_normalize_font_key((string) ($body['font_key'] ?? 'noto_sans_bold'));
        $fontSizeMain = clamp_int((int) ($body['font_size_main'] ?? 32), 14, 72);
        $layoutKey = timer_normalize_layout_key((string) ($body['layout_key'] ?? 'segmented_pills'));
        $pdo = db();
        billing_assert_timer_update_allowed($pdo, $workspaceId, $layoutKey, $fontKey);
        $stmt = $pdo->prepare('UPDATE timers SET name = ?, ends_at = ?, bg_color = ?, text_color = ?, accent_color = ?, label = ?, width = ?, height = ?, font_key = ?, font_size_main = ?, layout_key = ? WHERE id = ? AND workspace_id = ?');
        $stmt->execute([$name, $endsAt, $bg, $fg, $ac, $label, $w, $h, $fontKey, $fontSizeMain, $layoutKey, $id, $workspaceId]);
        if ($stmt->rowCount() < 1) {
            json_response(['error' => 'not found'], 404);
        }
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'timer.updated', 'timer', $id, ['name' => $name, 'ends_at' => $endsAt]);
        json_response(['ok' => true]);
    }

    if ($method === 'DELETE') {
        auth_require_api_write();
        $id = $_GET['id'] ?? '';
        if (!is_valid_id($id)) {
            json_response(['error' => 'invalid id'], 422);
        }
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM timers WHERE id = ? AND workspace_id = ?');
        $stmt->execute([$id, $workspaceId]);
        if ($stmt->rowCount() < 1) {
            json_response(['error' => 'not found'], 404);
        }
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'timer.deleted', 'timer', $id, []);
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
