<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';

auth_start_session();
auth_require_api_audit_read();

$workspaceId = auth_workspace_id();
if ($workspaceId < 1) {
    json_response(['error' => 'No active workspace'], 403);
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$limit = max(1, min(100, $limit));

$pdo = db();
$stmt = $pdo->prepare('SELECT id, actor_user_id, action, entity_type, entity_id, details, ip, created_at FROM audit_log WHERE workspace_id = ? ORDER BY created_at DESC, id DESC LIMIT ?');
$stmt->execute([$workspaceId, $limit]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$r) {
    $d = json_decode((string) ($r['details'] ?? '{}'), true);
    $r['details'] = is_array($d) ? $d : [];
}
unset($r);

json_response(['entries' => $rows]);
