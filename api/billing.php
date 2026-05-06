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

$ent = billing_workspace_entitlements(db(), $workspaceId);
$catalog = billing_plan_catalog();

json_response([
    'entitlements' => $ent,
    'plans' => $catalog,
]);

