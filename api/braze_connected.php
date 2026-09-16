<?php

declare(strict_types=1);

/**
 * Braze Connected Content endpoint.
 * Authenticate with: Authorization: Bearer <connected_content_token>
 * Query: timer_id (required), dynamic=1 (optional Liquid-ready image URL)
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/braze.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['error' => 'GET only'], 405);
}

$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$token = '';
if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authHeader, $m)) {
    $token = $m[1];
}
if ($token === '') {
    $token = trim((string) ($_GET['token'] ?? ''));
}
if ($token === '') {
    observability_log('braze.connected.unauthorized', 'warning');
    json_response(['error' => 'Missing Bearer token'], 401);
}

$timerId = (string) ($_GET['timer_id'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $timerId)) {
    json_response(['error' => 'Invalid timer_id'], 422);
}

$pdo = db();
$tokenHash = braze_hash_token($token);
$stmt = $pdo->prepare('SELECT workspace_id FROM braze_connections WHERE connected_content_token_hash = ? LIMIT 1');
$stmt->execute([$tokenHash]);
$workspaceId = (int) $stmt->fetchColumn();
if ($workspaceId < 1) {
    observability_log('braze.connected.bad_token', 'warning');
    json_response(['error' => 'Invalid token'], 403);
}

$t = $pdo->prepare('SELECT id, name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key FROM timers WHERE id = ? AND workspace_id = ? LIMIT 1');
$t->execute([$timerId, $workspaceId]);
$timer = $t->fetch(PDO::FETCH_ASSOC);
if ($timer === false) {
    json_response(['error' => 'Timer not found'], 404);
}

$dynamic = isset($_GET['dynamic']) && (string) $_GET['dynamic'] !== '0' && strtolower((string) $_GET['dynamic']) !== 'false';
$html = braze_timer_embed_html($timer, $dynamic);
$imageUrl = braze_timer_image_url($timer, $dynamic);

observability_log('braze.connected.ok', 'info', [
    'workspace_id' => $workspaceId,
    'timer_id' => $timerId,
    'dynamic' => $dynamic,
]);

json_response([
    'html' => $html,
    'image_url' => $imageUrl,
    'width' => (int) ($timer['width'] ?? 480),
    'height' => (int) ($timer['height'] ?? 120),
    'ends_at' => (int) ($timer['ends_at'] ?? 0),
    'alt' => 'Countdown — ' . app_format_deadline_label((int) ($timer['ends_at'] ?? 0)),
    'name' => (string) ($timer['name'] ?? ''),
    'timer_id' => $timerId,
]);
