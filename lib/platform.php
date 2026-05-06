<?php

declare(strict_types=1);

function platform_seed_owner_email(): string
{
    return 'owner@local.invalid';
}

/**
 * Workspace + users + audit schema; timers.workspace_id.
 */
function platform_schema_migrate(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys=ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS workspaces (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        created_at INTEGER NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        display_name TEXT NOT NULL DEFAULT "",
        password_hash TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS workspace_members (
        workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        role TEXT NOT NULL CHECK(role IN (\'owner\',\'admin\',\'editor\',\'viewer\')),
        created_at INTEGER NOT NULL,
        PRIMARY KEY (workspace_id, user_id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
        actor_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL DEFAULT "",
        details TEXT NOT NULL DEFAULT "{}",
        ip TEXT NOT NULL DEFAULT "",
        created_at INTEGER NOT NULL
    )');

    $tcols = [];
    foreach ($pdo->query('PRAGMA table_info(timers)') as $row) {
        $tcols[(string) $row['name']] = true;
    }
    if (!isset($tcols['workspace_id'])) {
        $pdo->exec('ALTER TABLE timers ADD COLUMN workspace_id INTEGER NOT NULL DEFAULT 1');
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_timers_workspace ON timers(workspace_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_workspace_created ON audit_log(workspace_id, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_email_lower ON users(email)');

    $wsCount = (int) $pdo->query('SELECT COUNT(*) FROM workspaces')->fetchColumn();
    if ($wsCount === 0) {
        $now = time();
        $stmt = $pdo->prepare('INSERT INTO workspaces (name, slug, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['Default workspace', 'default', $now]);
    }
}

/**
 * Copy/install owner user from bcrypt in data/secrets.php (single-tenant bootstrap).
 */
function platform_seed_owner_user_from_secrets(PDO $pdo): void
{
    $cfg = app_secrets_array();
    if (!is_array($cfg)) {
        return;
    }
    $hash = (string) ($cfg['password_hash'] ?? '');
    if ($hash === '' || !str_starts_with($hash, '$2')) {
        return;
    }

    platform_schema_migrate($pdo);

    $wsId = (int) $pdo->query('SELECT id FROM workspaces ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($wsId < 1) {
        return;
    }

    $email = platform_seed_owner_email();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $uid = $stmt->fetchColumn();
    $now = time();
    if ($uid === false) {
        $ins = $pdo->prepare('INSERT INTO users (email, display_name, password_hash, is_active, created_at) VALUES (?, ?, ?, 1, ?)');
        $ins->execute([$email, 'Owner', $hash, $now]);
        $uid = (int) $pdo->lastInsertId();
    } else {
        $uid = (int) $uid;
        $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?')->execute([$hash, $uid]);
    }

    $mem = $pdo->prepare('SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ?');
    $mem->execute([$wsId, $uid]);
    if ($mem->fetchColumn() === false) {
        $pdo->prepare('INSERT INTO workspace_members (workspace_id, user_id, role, created_at) VALUES (?, ?, \'owner\', ?)')
            ->execute([$wsId, $uid, $now]);
    }

    $pdo->prepare('UPDATE timers SET workspace_id = ? WHERE workspace_id IS NULL OR workspace_id = 0')->execute([$wsId]);
}

function platform_audit_log(
    PDO $pdo,
    int $workspaceId,
    ?int $actorUserId,
    string $action,
    string $entityType,
    string $entityId,
    array $details = []
): void {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (strlen($ip) > 64) {
        $ip = substr($ip, 0, 64);
    }
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('INSERT INTO audit_log (workspace_id, actor_user_id, action, entity_type, entity_id, details, ip, created_at) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $workspaceId,
        $actorUserId,
        $action,
        $entityType,
        $entityId,
        $detailsJson,
        $ip,
        time(),
    ]);
}
