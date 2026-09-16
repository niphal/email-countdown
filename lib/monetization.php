<?php

declare(strict_types=1);

require_once __DIR__ . '/timer_fonts.php';
require_once __DIR__ . '/timer_layouts.php';

/** Soft open access: every workspace gets full product features. */
const BILLING_UNLIMITED_TIMERS = 100000;

/** @return array<string, array{name:string, monthly_usd:int, max_timers:int, allow_premium_layouts:bool, allow_premium_fonts:bool}> */
function billing_plan_catalog(): array
{
    return [
        'free' => [
            'name' => 'Full access',
            'monthly_usd' => 0,
            'max_timers' => BILLING_UNLIMITED_TIMERS,
            'allow_premium_layouts' => true,
            'allow_premium_fonts' => true,
        ],
        'pro' => [
            'name' => 'Full access',
            'monthly_usd' => 0,
            'max_timers' => BILLING_UNLIMITED_TIMERS,
            'allow_premium_layouts' => true,
            'allow_premium_fonts' => true,
        ],
        'business' => [
            'name' => 'Full access',
            'monthly_usd' => 0,
            'max_timers' => BILLING_UNLIMITED_TIMERS,
            'allow_premium_layouts' => true,
            'allow_premium_fonts' => true,
        ],
    ];
}

function billing_normalize_plan_key(string $key): string
{
    $k = strtolower(trim($key));
    if (array_key_exists($k, billing_plan_catalog())) {
        return $k;
    }

    return 'free';
}

/** @return array{workspace_id:int,plan_key:string,status:string,stripe_customer_id:string,current_period_end:int}|null */
function billing_workspace_row(PDO $pdo, int $workspaceId): ?array
{
    $stmt = $pdo->prepare('SELECT workspace_id, plan_key, status, stripe_customer_id, current_period_end FROM workspace_billing WHERE workspace_id = ? LIMIT 1');
    $stmt->execute([$workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $row['plan_key'] = billing_normalize_plan_key((string) ($row['plan_key'] ?? 'free'));

        return $row;
    }

    $now = time();
    $ins = $pdo->prepare('INSERT INTO workspace_billing (workspace_id, plan_key, status, stripe_customer_id, current_period_end, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([$workspaceId, 'free', 'active', '', 0, $now, $now]);

    return [
        'workspace_id' => $workspaceId,
        'plan_key' => 'free',
        'status' => 'active',
        'stripe_customer_id' => '',
        'current_period_end' => 0,
    ];
}

/** @return array{plan_key:string,plan_name:string,monthly_usd:int,max_timers:int,timer_count:int,remaining_timers:int,allow_premium_layouts:bool,allow_premium_fonts:bool,status:string} */
function billing_workspace_entitlements(PDO $pdo, int $workspaceId): array
{
    $row = billing_workspace_row($pdo, $workspaceId);
    $planKey = $row !== null ? billing_normalize_plan_key((string) $row['plan_key']) : 'free';
    $catalog = billing_plan_catalog();
    $plan = $catalog[$planKey];
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM timers WHERE workspace_id = ?');
    $countStmt->execute([$workspaceId]);
    $count = (int) $countStmt->fetchColumn();

    return [
        'plan_key' => $planKey,
        'plan_name' => (string) $plan['name'],
        'monthly_usd' => (int) $plan['monthly_usd'],
        'max_timers' => BILLING_UNLIMITED_TIMERS,
        'timer_count' => $count,
        'remaining_timers' => BILLING_UNLIMITED_TIMERS,
        'allow_premium_layouts' => true,
        'allow_premium_fonts' => true,
        'status' => $row !== null ? (string) ($row['status'] ?? 'active') : 'active',
    ];
}

/** @return list<string> */
function billing_allowed_layouts(array $ent): array
{
    return timer_layout_keys();
}

/** @return list<string> */
function billing_allowed_fonts(array $ent): array
{
    return timer_font_keys();
}

function billing_validate_timer_features(array $ent, string $layoutKey, string $fontKey): void
{
    if (!in_array($layoutKey, timer_layout_keys(), true)) {
        throw new RuntimeException('Unknown layout.');
    }
    if (!in_array($fontKey, timer_font_keys(), true)) {
        throw new RuntimeException('Unknown font.');
    }
}

function billing_assert_timer_create_allowed(PDO $pdo, int $workspaceId, string $layoutKey, string $fontKey): array
{
    $ent = billing_workspace_entitlements($pdo, $workspaceId);
    billing_validate_timer_features($ent, $layoutKey, $fontKey);

    return $ent;
}

function billing_assert_timer_update_allowed(PDO $pdo, int $workspaceId, string $layoutKey, string $fontKey): array
{
    $ent = billing_workspace_entitlements($pdo, $workspaceId);
    billing_validate_timer_features($ent, $layoutKey, $fontKey);

    return $ent;
}
