<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/monetization.php';
require_once dirname(__DIR__) . '/auth.php';

auth_start_session();
auth_require_api_login();

$workspaceId = auth_workspace_id();
if ($workspaceId < 1) {
    json_response(['error' => 'No active workspace'], 403);
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'PUT') {
    auth_require_api_billing_manage();
    $raw = file_get_contents('php://input') ?: '{}';
    $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $planKey = billing_normalize_plan_key((string) ($body['plan_key'] ?? 'free'));
    $status = strtolower(trim((string) ($body['status'] ?? 'active')));
    if (!in_array($status, ['active', 'past_due', 'paused', 'canceled'], true)) {
        json_response(['error' => 'Invalid billing status'], 422);
    }
    $now = time();
    $stmt = $pdo->prepare('UPDATE workspace_billing SET plan_key = ?, status = ?, updated_at = ? WHERE workspace_id = ?');
    $stmt->execute([$planKey, $status, $now, $workspaceId]);
    platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'billing.updated', 'workspace', (string) $workspaceId, ['plan_key' => $planKey, 'status' => $status]);
}

$ent = billing_workspace_entitlements($pdo, $workspaceId);
$catalog = billing_plan_catalog();

json_response([
    'entitlements' => $ent,
    'plans' => $catalog,
]);

