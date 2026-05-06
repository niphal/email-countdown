<?php

declare(strict_types=1);

require_once __DIR__ . '/timer_fonts.php';
require_once __DIR__ . '/timer_layouts.php';

/** @return array<string, array{name:string, monthly_usd:int, max_timers:int, allow_premium_layouts:bool, allow_premium_fonts:bool}> */
function billing_plan_catalog(): array
{
    return [
        'free' => [
            'name' => 'Free',
            'monthly_usd' => 0,
            'max_timers' => 5,
            'allow_premium_layouts' => false,
            'allow_premium_fonts' => false,
        ],
        'pro' => [
            'name' => 'Pro',
            'monthly_usd' => 49,
            'max_timers' => 100,
            'allow_premium_layouts' => true,
            'allow_premium_fonts' => true,
        ],
        'business' => [
            'name' => 'Business',
            'monthly_usd' => 199,
            'max_timers' => 1000,
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
    $remaining = max(0, (int) $plan['max_timers'] - $count);

    return [
        'plan_key' => $planKey,
        'plan_name' => (string) $plan['name'],
        'monthly_usd' => (int) $plan['monthly_usd'],
        'max_timers' => (int) $plan['max_timers'],
        'timer_count' => $count,
        'remaining_timers' => $remaining,
        'allow_premium_layouts' => (bool) $plan['allow_premium_layouts'],
        'allow_premium_fonts' => (bool) $plan['allow_premium_fonts'],
        'status' => $row !== null ? (string) ($row['status'] ?? 'active') : 'active',
    ];
}

/** @return list<string> */
function billing_allowed_layouts(array $ent): array
{
    if (!empty($ent['allow_premium_layouts'])) {
        return timer_layout_keys();
    }

    return ['segmented_pills', 'split_emphasis', 'minimal_editorial'];
}

/** @return list<string> */
function billing_allowed_fonts(array $ent): array
{
    if (!empty($ent['allow_premium_fonts'])) {
        return timer_font_keys();
    }

    return ['noto_sans_bold', 'noto_sans'];
}

function billing_validate_timer_features(array $ent, string $layoutKey, string $fontKey): void
{
    if (!in_array($layoutKey, billing_allowed_layouts($ent), true)) {
        throw new RuntimeException('Selected layout requires a paid plan.');
    }
    if (!in_array($fontKey, billing_allowed_fonts($ent), true)) {
        throw new RuntimeException('Selected font requires a paid plan.');
    }
}

function billing_assert_timer_create_allowed(PDO $pdo, int $workspaceId, string $layoutKey, string $fontKey): array
{
    $ent = billing_workspace_entitlements($pdo, $workspaceId);
    if ((int) $ent['timer_count'] >= (int) $ent['max_timers']) {
        throw new RuntimeException('Plan limit reached. Upgrade to create more timers.');
    }
    billing_validate_timer_features($ent, $layoutKey, $fontKey);

    return $ent;
}

function billing_assert_timer_update_allowed(PDO $pdo, int $workspaceId, string $layoutKey, string $fontKey): array
{
    $ent = billing_workspace_entitlements($pdo, $workspaceId);
    billing_validate_timer_features($ent, $layoutKey, $fontKey);

    return $ent;
}

