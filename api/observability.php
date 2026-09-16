<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/lib/mail_transport.php';

auth_start_session();
auth_require_api_members_manage();

$limit = (int) ($_GET['limit'] ?? 50);
$level = strtolower(trim((string) ($_GET['level'] ?? '')));

json_response([
    'health' => observability_health(),
    'events' => observability_recent_events($limit, $level),
]);
