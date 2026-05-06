<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';

auth_start_session();
auth_require_api_members_manage();

$workspaceId = auth_workspace_id();
if ($workspaceId < 1) {
    json_response(['error' => 'No active workspace'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.display_name, u.is_active, wm.role, u.created_at
            FROM workspace_members wm
            INNER JOIN users u ON u.id = wm.user_id
            WHERE wm.workspace_id = ?
            ORDER BY CASE wm.role WHEN "owner" THEN 0 WHEN "admin" THEN 1 WHEN "editor" THEN 2 ELSE 3 END, u.id ASC');
        $stmt->execute([$workspaceId]);
        json_response(['members' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $email = trim((string) ($body['email'] ?? ''));
        $name = trim((string) ($body['display_name'] ?? ''));
        $role = (string) ($body['role'] ?? AUTH_ROLE_VIEWER);
        $password = (string) ($body['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Valid email required'], 422);
        }
        if (!auth_is_valid_role($role)) {
            json_response(['error' => 'Invalid role'], 422);
        }
        if ($role === AUTH_ROLE_OWNER && !auth_has_min_role(AUTH_ROLE_OWNER)) {
            json_response(['error' => 'Only owner can assign owner role'], 403);
        }

        $pdo->beginTransaction();
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $uStmt->execute([$email]);
        $uid = $uStmt->fetchColumn();
        $now = time();
        if ($uid === false) {
            if (strlen($password) < 8) {
                $pdo->rollBack();
                json_response(['error' => 'Password (min 8 chars) required for new user'], 422);
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                $pdo->rollBack();
                json_response(['error' => 'Could not hash password'], 500);
            }
            $ins = $pdo->prepare('INSERT INTO users (email, display_name, password_hash, is_active, created_at) VALUES (?, ?, ?, 1, ?)');
            $ins->execute([$email, $name !== '' ? $name : $email, $hash, $now]);
            $uid = (int) $pdo->lastInsertId();
        } else {
            $uid = (int) $uid;
            if ($name !== '') {
                $pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([$name, $uid]);
            }
            if ($password !== '') {
                if (strlen($password) < 8) {
                    $pdo->rollBack();
                    json_response(['error' => 'Password must be at least 8 characters'], 422);
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($hash === false) {
                    $pdo->rollBack();
                    json_response(['error' => 'Could not hash password'], 500);
                }
                $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?')->execute([$hash, $uid]);
            }
        }

        $mStmt = $pdo->prepare('SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?');
        $mStmt->execute([$workspaceId, $uid]);
        $existingRole = $mStmt->fetchColumn();
        if ($existingRole === false) {
            $pdo->prepare('INSERT INTO workspace_members (workspace_id, user_id, role, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$workspaceId, $uid, $role, $now]);
            $action = 'member.added';
        } else {
            if ((string) $existingRole === AUTH_ROLE_OWNER && $role !== AUTH_ROLE_OWNER && !auth_has_min_role(AUTH_ROLE_OWNER)) {
                $pdo->rollBack();
                json_response(['error' => 'Only owner can downgrade owner'], 403);
            }
            $pdo->prepare('UPDATE workspace_members SET role = ? WHERE workspace_id = ? AND user_id = ?')
                ->execute([$role, $workspaceId, $uid]);
            $action = 'member.role_updated';
        }

        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, $action, 'member', (string) $uid, ['email' => $email, 'role' => $role]);
        $pdo->commit();
        json_response(['ok' => true, 'user_id' => $uid]);
    }

    if ($method === 'PATCH') {
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $userId = (int) ($body['user_id'] ?? 0);
        $role = (string) ($body['role'] ?? '');
        $active = array_key_exists('is_active', $body) ? (int) ((bool) $body['is_active']) : null;
        if ($userId <= 0) {
            json_response(['error' => 'user_id required'], 422);
        }
        if ($role !== '' && !auth_is_valid_role($role)) {
            json_response(['error' => 'Invalid role'], 422);
        }

        $curStmt = $pdo->prepare('SELECT wm.role, u.email FROM workspace_members wm INNER JOIN users u ON u.id = wm.user_id WHERE wm.workspace_id = ? AND wm.user_id = ?');
        $curStmt->execute([$workspaceId, $userId]);
        $current = $curStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current)) {
            json_response(['error' => 'Member not found'], 404);
        }

        if ($role !== '') {
            if (($current['role'] ?? '') === AUTH_ROLE_OWNER && $role !== AUTH_ROLE_OWNER && !auth_has_min_role(AUTH_ROLE_OWNER)) {
                json_response(['error' => 'Only owner can downgrade owner'], 403);
            }
            if ($role === AUTH_ROLE_OWNER && !auth_has_min_role(AUTH_ROLE_OWNER)) {
                json_response(['error' => 'Only owner can assign owner'], 403);
            }
            $pdo->prepare('UPDATE workspace_members SET role = ? WHERE workspace_id = ? AND user_id = ?')->execute([$role, $workspaceId, $userId]);
        }
        if ($active !== null) {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active, $userId]);
        }

        platform_audit_log($pdo, $workspaceId, auth_user_id() ?: null, 'member.updated', 'member', (string) $userId, [
            'email' => (string) ($current['email'] ?? ''),
            'role' => $role !== '' ? $role : (string) ($current['role'] ?? ''),
            'is_active' => $active,
        ]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'method not allowed'], 405);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['error' => $e->getMessage()], 500);
}

