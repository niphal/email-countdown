<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/lib/braze.php';

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
        $status = braze_connection_public_status($pdo, $workspaceId);
        $status['blocks'] = braze_list_pushed_blocks($pdo, $workspaceId);
        json_response($status);
    }

    $raw = file_get_contents('php://input') ?: '{}';
    $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($body)) {
        $body = [];
    }

    if ($method === 'PUT') {
        auth_require_api_members_manage();
        $endpoint = braze_normalize_rest_endpoint((string) ($body['rest_endpoint'] ?? ''));
        $apiKey = trim((string) ($body['api_key'] ?? ''));
        $rotateToken = !empty($body['rotate_connected_content_token']);

        $existing = braze_connection_get($pdo, $workspaceId);
        if ($endpoint === '') {
            json_response(['error' => 'Choose a valid Braze REST endpoint'], 422);
        }
        if ($apiKey === '' && $existing === null) {
            json_response(['error' => 'API key is required'], 422);
        }
        if ($apiKey === '' && is_array($existing)) {
            $apiKey = (string) $existing['api_key'];
        }

        $now = time();
        $hint = braze_api_key_hint($apiKey);
        $plainToken = null;
        $tokenHash = is_array($existing) ? (string) $existing['connected_content_token_hash'] : '';
        $tokenHint = is_array($existing) ? (string) $existing['connected_content_token_hint'] : '';

        if ($tokenHash === '' || $rotateToken) {
            $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $tokenHash = braze_hash_token($plainToken);
            $tokenHint = braze_api_key_hint($plainToken);
        }

        $pdo->prepare('INSERT INTO braze_connections (
                workspace_id, rest_endpoint, api_key, api_key_hint,
                connected_content_token_hash, connected_content_token_hint,
                last_tested_at, last_error, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,0,"",?,?)
            ON CONFLICT(workspace_id) DO UPDATE SET
                rest_endpoint = excluded.rest_endpoint,
                api_key = excluded.api_key,
                api_key_hint = excluded.api_key_hint,
                connected_content_token_hash = excluded.connected_content_token_hash,
                connected_content_token_hint = excluded.connected_content_token_hint,
                updated_at = excluded.updated_at,
                last_error = ""')
            ->execute([$workspaceId, $endpoint, $apiKey, $hint, $tokenHash, $tokenHint, $now, $now]);

        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'braze.connected', 'integration', 'braze', [
            'rest_endpoint' => $endpoint,
            'rotated_token' => $plainToken !== null,
        ]);

        $test = braze_test_connection($pdo, $workspaceId);
        $status = braze_connection_public_status($pdo, $workspaceId);
        $status['blocks'] = braze_list_pushed_blocks($pdo, $workspaceId);
        $status['test_ok'] = $test['ok'];
        $status['test_error'] = $test['error'];
        if ($plainToken !== null) {
            $status['connected_content_token'] = $plainToken;
            $status['connected_content_token_notice'] = 'Copy this Connected Content token now — it is shown only once.';
        }
        json_response($status);
    }

    if ($method === 'DELETE') {
        auth_require_api_members_manage();
        $pdo->prepare('DELETE FROM braze_connections WHERE workspace_id = ?')->execute([$workspaceId]);
        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'braze.disconnected', 'integration', 'braze', []);
        json_response(['connected' => false, 'ok' => true]);
    }

    if ($method === 'POST') {
        $action = (string) ($body['action'] ?? '');

        if ($action === 'test') {
            auth_require_api_members_manage();
            $test = braze_test_connection($pdo, $workspaceId);
            $status = braze_connection_public_status($pdo, $workspaceId);
            $status['blocks'] = braze_list_pushed_blocks($pdo, $workspaceId);
            $status['test_ok'] = $test['ok'];
            $status['test_error'] = $test['error'];
            json_response($status, $test['ok'] ? 200 : 400);
        }

        if ($action === 'rotate_token') {
            auth_require_api_members_manage();
            $existing = braze_connection_get($pdo, $workspaceId);
            if ($existing === null) {
                json_response(['error' => 'Connect Braze first'], 400);
            }
            $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $now = time();
            $pdo->prepare('UPDATE braze_connections SET connected_content_token_hash = ?, connected_content_token_hint = ?, updated_at = ? WHERE workspace_id = ?')
                ->execute([braze_hash_token($plainToken), braze_api_key_hint($plainToken), $now, $workspaceId]);
            platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'braze.token_rotated', 'integration', 'braze', []);
            $status = braze_connection_public_status($pdo, $workspaceId);
            $status['blocks'] = braze_list_pushed_blocks($pdo, $workspaceId);
            $status['connected_content_token'] = $plainToken;
            $status['connected_content_token_notice'] = 'Copy this Connected Content token now — it is shown only once.';
            json_response($status);
        }

        if ($action === 'push_content_block') {
            auth_require_api_write();
            $timerId = (string) ($body['timer_id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/', $timerId)) {
                json_response(['error' => 'Invalid timer_id'], 422);
            }
            $dynamic = !empty($body['dynamic_end']);
            $stmt = $pdo->prepare('SELECT id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key FROM timers WHERE id = ? AND workspace_id = ? LIMIT 1');
            $stmt->execute([$timerId, $workspaceId]);
            $timer = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($timer === false) {
                json_response(['error' => 'Timer not found'], 404);
            }
            $result = braze_push_timer_content_block($pdo, $workspaceId, $timer, $dynamic);
            if ($result['ok']) {
                platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'braze.content_block_pushed', 'timer', $timerId, [
                    'content_block_id' => $result['content_block_id'],
                    'liquid_tag' => $result['liquid_tag'],
                    'dynamic_end' => $dynamic,
                ]);
                observability_log('braze.content_block_pushed', 'info', [
                    'workspace_id' => $workspaceId,
                    'timer_id' => $timerId,
                ]);
            }
            json_response($result, $result['ok'] ? 200 : 400);
        }

        if ($action === 'snippets') {
            $timerId = (string) ($body['timer_id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/', $timerId)) {
                json_response(['error' => 'Invalid timer_id'], 422);
            }
            $stmt = $pdo->prepare('SELECT id, name, ends_at, width, height, label FROM timers WHERE id = ? AND workspace_id = ? LIMIT 1');
            $stmt->execute([$timerId, $workspaceId]);
            $timer = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($timer === false) {
                json_response(['error' => 'Timer not found'], 404);
            }
            $status = braze_connection_public_status($pdo, $workspaceId);
            $ccUrl = (string) $status['connected_content_url'];
            $blockRow = $pdo->prepare('SELECT liquid_tag, block_name, content_block_id, last_pushed_at FROM braze_content_blocks WHERE workspace_id = ? AND timer_id = ? LIMIT 1');
            $blockRow->execute([$workspaceId, $timerId]);
            $block = $blockRow->fetch(PDO::FETCH_ASSOC) ?: null;

            json_response([
                'timer_id' => $timerId,
                'embed_html' => braze_timer_embed_html($timer, false),
                'embed_html_dynamic' => braze_timer_embed_html($timer, true),
                'connected_content_liquid' => braze_connected_content_snippet($timerId, $ccUrl, true),
                'content_block_liquid' => is_array($block) && (string) ($block['liquid_tag'] ?? '') !== ''
                    ? (string) $block['liquid_tag']
                    : '',
                'block' => $block,
                'connected' => !empty($status['connected']),
                'embed_https_ok' => !empty($status['embed_https_ok']),
            ]);
        }

        json_response(['error' => 'Unknown action'], 422);
    }

    json_response(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    observability_log('braze.api.error', 'error', ['error' => $e->getMessage()]);
    json_response(['error' => 'Braze API error'], 500);
}
