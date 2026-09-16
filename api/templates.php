<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/lib/timer_templates.php';
require_once dirname(__DIR__) . '/lib/timer_template_presets.php';
require_once dirname(__DIR__) . '/lib/monetization.php';

auth_start_session();
auth_require_api_login();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();
$workspaceId = auth_workspace_id();
if ($workspaceId < 1) {
    json_response(['error' => 'No active workspace'], 403);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['presets'])) {
            json_response(['presets' => timer_template_presets_list()]);
        }
        $id = (string) ($_GET['id'] ?? '');
        if ($id !== '') {
            $row = timer_template_get($pdo, $workspaceId, $id);
            if ($row === null) {
                json_response(['error' => 'Not found'], 404);
            }
            json_response(['template' => $row]);
        }
        $stmt = $pdo->prepare('SELECT * FROM timer_templates WHERE workspace_id = ? ORDER BY is_default DESC, updated_at DESC');
        $stmt->execute([$workspaceId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = timer_template_normalize_row($row);
        }
        json_response([
            'templates' => $rows,
            'presets' => timer_template_presets_list(),
        ]);
    }

    if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_bg') {
        auth_require_api_write();
        $templateId = (string) ($_POST['template_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $templateId)) {
            json_response(['error' => 'Invalid template_id'], 422);
        }
        $tpl = timer_template_get($pdo, $workspaceId, $templateId);
        if ($tpl === null) {
            json_response(['error' => 'Template not found'], 404);
        }
        if (!isset($_FILES['bg_image']) || !is_uploaded_file($_FILES['bg_image']['tmp_name'] ?? '')) {
            json_response(['error' => 'Upload bg_image file'], 422);
        }
        $tmp = $_FILES['bg_image']['tmp_name'];
        $orig = (string) ($_FILES['bg_image']['name'] ?? 'image.jpg');
        $ext = timer_allowed_upload_image($tmp, $orig);
        if ($ext === null) {
            json_response(['error' => 'Image must be JPG, PNG, GIF, or WebP under 2MB'], 422);
        }
        $destAbs = timer_template_asset_path($workspaceId, $templateId, $ext);
        $relative = timer_template_asset_relative($workspaceId, $templateId, $ext);
        if (!move_uploaded_file($tmp, $destAbs)) {
            json_response(['error' => 'Could not save image'], 500);
        }
        $old = (string) ($tpl['bg_image_file'] ?? '');
        if ($old !== '' && $old !== $relative) {
            timer_template_delete_assets($old);
        }
        $now = time();
        $pdo->prepare('UPDATE timer_templates SET bg_image_file = ?, updated_at = ? WHERE id = ? AND workspace_id = ?')
            ->execute([$relative, $now, $templateId, $workspaceId]);
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'template.bg_uploaded', 'template', $templateId, []);
        $row = timer_template_get($pdo, $workspaceId, $templateId);
        json_response(['template' => $row]);
    }

    if ($method === 'POST') {
        auth_require_api_write();
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            $body = [];
        }
        $action = (string) ($body['action'] ?? '');
        if ($action === 'seed_presets') {
            $mode = (string) ($body['mode'] ?? 'all');
            $onlyKeys = $mode === 'core' ? timer_template_core_preset_keys() : null;
            $result = timer_template_seed_presets($pdo, $workspaceId, !empty($body['only_if_empty']), $onlyKeys);
            platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'template.presets_seeded', 'workspace', (string) $workspaceId, [
                'added' => $result['added'],
                'skipped' => $result['skipped'],
            ]);
            $stmt = $pdo->prepare('SELECT * FROM timer_templates WHERE workspace_id = ? ORDER BY is_default DESC, updated_at DESC');
            $stmt->execute([$workspaceId]);
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = timer_template_normalize_row($row);
            }
            json_response([
                'ok' => true,
                'added' => $result['added'],
                'skipped' => $result['skipped'],
                'templates' => $rows,
            ]);
        }
        if ($action === 'add_preset') {
            $key = (string) ($body['preset_key'] ?? '');
            $row = timer_template_insert_from_preset($pdo, $workspaceId, $key, null, !empty($body['is_default']));
            platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'template.preset_added', 'template', (string) $row['id'], [
                'preset_key' => $key,
            ]);
            json_response(['template' => $row]);
        }
        $payload = timer_template_sanitize_payload($body, true);
        $id = bin2hex(random_bytes(16));
        $now = time();
        if ($payload['is_default']) {
            timer_template_clear_default($pdo, $workspaceId);
        }
        $stmt = $pdo->prepare('INSERT INTO timer_templates (
            id, workspace_id, name, description, bg_color, text_color, accent_color, default_label,
            width, height, font_key, font_size_main, layout_key, bg_image_file, bg_overlay_color, bg_overlay_opacity,
            is_default, created_at, updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $id, $workspaceId, $payload['name'], $payload['description'], $payload['bg_color'], $payload['text_color'],
            $payload['accent_color'], $payload['default_label'], $payload['width'], $payload['height'],
            $payload['font_key'], $payload['font_size_main'], $payload['layout_key'], '',
            $payload['bg_overlay_color'], $payload['bg_overlay_opacity'], $payload['is_default'], $now, $now,
        ]);
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'template.created', 'template', $id, ['name' => $payload['name']]);
        json_response(['template' => timer_template_get($pdo, $workspaceId, $id)]);
    }

    if ($method === 'PUT') {
        auth_require_api_write();
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            $body = [];
        }
        $id = (string) ($body['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            json_response(['error' => 'Invalid id'], 422);
        }
        if (timer_template_get($pdo, $workspaceId, $id) === null) {
            json_response(['error' => 'Not found'], 404);
        }
        $payload = timer_template_sanitize_payload($body, false);
        if ($payload['is_default']) {
            timer_template_clear_default($pdo, $workspaceId, $id);
        }
        $now = time();
        $pdo->prepare('UPDATE timer_templates SET
            name = ?, description = ?, bg_color = ?, text_color = ?, accent_color = ?, default_label = ?,
            width = ?, height = ?, font_key = ?, font_size_main = ?, layout_key = ?,
            bg_overlay_color = ?, bg_overlay_opacity = ?, is_default = ?, updated_at = ?
            WHERE id = ? AND workspace_id = ?')
            ->execute([
                $payload['name'], $payload['description'], $payload['bg_color'], $payload['text_color'],
                $payload['accent_color'], $payload['default_label'], $payload['width'], $payload['height'],
                $payload['font_key'], $payload['font_size_main'], $payload['layout_key'],
                $payload['bg_overlay_color'], $payload['bg_overlay_opacity'], $payload['is_default'],
                $now, $id, $workspaceId,
            ]);
        if (!empty($body['remove_bg_image'])) {
            $tpl = timer_template_get($pdo, $workspaceId, $id);
            if ($tpl !== null && (string) ($tpl['bg_image_file'] ?? '') !== '') {
                timer_template_delete_assets((string) $tpl['bg_image_file']);
                $pdo->prepare('UPDATE timer_templates SET bg_image_file = "", updated_at = ? WHERE id = ? AND workspace_id = ?')
                    ->execute([$now, $id, $workspaceId]);
            }
        }
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'template.updated', 'template', $id, ['name' => $payload['name']]);
        json_response(['template' => timer_template_get($pdo, $workspaceId, $id)]);
    }

    if ($method === 'DELETE') {
        auth_require_api_write();
        $id = (string) ($_GET['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            json_response(['error' => 'Invalid id'], 422);
        }
        $tpl = timer_template_get($pdo, $workspaceId, $id);
        if ($tpl === null) {
            json_response(['error' => 'Not found'], 404);
        }
        if ((string) ($tpl['bg_image_file'] ?? '') !== '') {
            timer_template_delete_assets((string) $tpl['bg_image_file']);
        }
        $pdo->prepare('DELETE FROM timer_templates WHERE id = ? AND workspace_id = ?')->execute([$id, $workspaceId]);
        $pdo->prepare('UPDATE timers SET template_id = "" WHERE workspace_id = ? AND template_id = ?')->execute([$workspaceId, $id]);
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'template.deleted', 'template', $id, []);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Method not allowed'], 405);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
