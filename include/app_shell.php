<?php

declare(strict_types=1);

/**
 * Shared BitView-style app chrome: dark sidebar + light content cards.
 *
 * Usage:
 *   $appNav = 'dashboard'; // dashboard|templates|integrations|admin
 *   require __DIR__ . '/include/app_shell_start.php';
 *   ... page content ...
 *   require __DIR__ . '/include/app_shell_end.php';
 */

function app_shell_workspace_name(): string
{
    $cu = auth_current_user();
    if ($cu === null) {
        return 'Workspace';
    }
    $stmt = db()->prepare('SELECT name FROM workspaces WHERE id = ?');
    $stmt->execute([(int) $cu['workspace_id']]);
    $name = $stmt->fetchColumn();

    return $name !== false ? (string) $name : 'Workspace';
}

function app_shell_user_label(): string
{
    $cu = auth_current_user();
    if ($cu === null) {
        return 'User';
    }
    $name = trim((string) ($cu['name'] ?? ''));

    return $name !== '' ? $name : 'Account';
}

function app_shell_role(): string
{
    $cu = auth_current_user();

    return (string) ($cu['role'] ?? 'viewer');
}

/**
 * @return array{plan_name:string, timer_count:int, max_timers:int}
 */
function app_shell_plan_summary(): array
{
    require_once dirname(__DIR__) . '/lib/monetization.php';
    $ws = auth_workspace_id();
    if ($ws < 1) {
        return ['plan_name' => 'Free', 'timer_count' => 0, 'max_timers' => 0];
    }
    try {
        $ent = billing_workspace_entitlements(db(), $ws);

        return [
            'plan_name' => (string) ($ent['plan_name'] ?? $ent['plan_key'] ?? 'Free'),
            'timer_count' => (int) ($ent['timer_count'] ?? 0),
            'max_timers' => (int) ($ent['max_timers'] ?? 0),
        ];
    } catch (Throwable $e) {
        return ['plan_name' => 'Free', 'timer_count' => 0, 'max_timers' => 0];
    }
}
