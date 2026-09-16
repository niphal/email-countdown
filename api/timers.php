<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/timer_fonts.php';
require_once dirname(__DIR__) . '/lib/timer_layouts.php';
require_once dirname(__DIR__) . '/lib/monetization.php';
require_once dirname(__DIR__) . '/lib/timer_templates.php';
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
        $stmt = db()->prepare('SELECT id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, template_id, bg_image_file, bg_overlay_color, bg_overlay_opacity, created_at FROM timers WHERE workspace_id = ? ORDER BY created_at DESC');
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
        $pdo = db();
        $style = timer_style_from_body($pdo, $workspaceId, $body);
        $id = bin2hex(random_bytes(16));
        $label = mb_substr((string) ($body['label'] ?? $style['label']), 0, 120);
        $now = time();
        $ent = billing_assert_timer_create_allowed($pdo, $workspaceId, $style['layout_key'], $style['font_key']);
        $stmt = $pdo->prepare('INSERT INTO timers (id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, template_id, bg_image_file, bg_overlay_color, bg_overlay_opacity, workspace_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $id, $name, $endsAt, $style['bg_color'], $style['text_color'], $style['accent_color'], $label,
            $style['width'], $style['height'], $style['font_key'], $style['font_size_main'], $style['layout_key'],
            $style['template_id'], $style['bg_image_file'], $style['bg_overlay_color'], $style['bg_overlay_opacity'],
            $workspaceId, $now,
        ]);
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
        $pdo = db();
        $style = timer_style_from_body($pdo, $workspaceId, $body);
        $label = mb_substr((string) ($body['label'] ?? $style['label']), 0, 120);
        billing_assert_timer_update_allowed($pdo, $workspaceId, $style['layout_key'], $style['font_key']);
        $stmt = $pdo->prepare('UPDATE timers SET name = ?, ends_at = ?, bg_color = ?, text_color = ?, accent_color = ?, label = ?, width = ?, height = ?, font_key = ?, font_size_main = ?, layout_key = ?, template_id = ?, bg_image_file = ?, bg_overlay_color = ?, bg_overlay_opacity = ? WHERE id = ? AND workspace_id = ?');
        $stmt->execute([
            $name, $endsAt, $style['bg_color'], $style['text_color'], $style['accent_color'], $label,
            $style['width'], $style['height'], $style['font_key'], $style['font_size_main'], $style['layout_key'],
            $style['template_id'], $style['bg_image_file'], $style['bg_overlay_color'], $style['bg_overlay_opacity'],
            $id, $workspaceId,
        ]);
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

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function timer_style_from_body(PDO $pdo, int $workspaceId, array $body): array
{
    $templateId = trim((string) ($body['template_id'] ?? ''));
    $base = [
        'bg_color' => sanitize_hex((string) ($body['bg_color'] ?? '#1a1a2e'), '#1a1a2e'),
        'text_color' => sanitize_hex((string) ($body['text_color'] ?? '#eaeaea'), '#eaeaea'),
        'accent_color' => sanitize_hex((string) ($body['accent_color'] ?? '#e94560'), '#e94560'),
        'width' => clamp_int((int) ($body['width'] ?? 480), 200, 600),
        'height' => clamp_int((int) ($body['height'] ?? 120), 80, 300),
        'font_key' => timer_normalize_font_key((string) ($body['font_key'] ?? 'noto_sans_bold')),
        'font_size_main' => clamp_int((int) ($body['font_size_main'] ?? 32), 14, 72),
        'layout_key' => timer_normalize_layout_key((string) ($body['layout_key'] ?? 'segmented_pills')),
        'label' => (string) ($body['label'] ?? ''),
        'template_id' => '',
        'bg_image_file' => '',
        'bg_overlay_color' => sanitize_hex((string) ($body['bg_overlay_color'] ?? '#000000'), '#000000'),
        'bg_overlay_opacity' => clamp_int((int) ($body['bg_overlay_opacity'] ?? 0), 0, 100),
    ];
    if ($templateId !== '' && preg_match('/^[a-f0-9]{32}$/', $templateId)) {
        $tpl = timer_template_get($pdo, $workspaceId, $templateId);
        if ($tpl !== null) {
            $base['template_id'] = $templateId;
            $base['bg_image_file'] = (string) ($tpl['bg_image_file'] ?? '');
            $base['bg_overlay_color'] = (string) ($tpl['bg_overlay_color'] ?? $base['bg_overlay_color']);
            $base['bg_overlay_opacity'] = (int) ($tpl['bg_overlay_opacity'] ?? $base['bg_overlay_opacity']);
            if (trim((string) ($body['label'] ?? '')) === '' && (string) ($tpl['default_label'] ?? '') !== '') {
                $base['label'] = (string) $tpl['default_label'];
            }
        }
    }

    return $base;
}
