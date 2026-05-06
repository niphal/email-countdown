<?php

declare(strict_types=1);

function platform_seed_owner_email(): string
{
    return 'owner@local.invalid';
}

function platform_slugify_workspace(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '');
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        $slug = 'workspace';
    }

    return substr($slug, 0, 48);
}

function platform_unique_workspace_slug(PDO $pdo, string $base): string
{
    $base = platform_slugify_workspace($base);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT 1 FROM workspaces WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() === false) {
            return $slug;
        }
        $slug = substr($base, 0, 44) . '-' . $i;
        $i++;
    }
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS workspace_billing (
        workspace_id INTEGER PRIMARY KEY REFERENCES workspaces(id) ON DELETE CASCADE,
        plan_key TEXT NOT NULL DEFAULT "free",
        status TEXT NOT NULL DEFAULT "active",
        stripe_customer_id TEXT NOT NULL DEFAULT "",
        current_period_end INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        used_at INTEGER,
        requested_ip TEXT NOT NULL DEFAULT "",
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
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_resets_exp ON password_resets(expires_at)');

    $wsCount = (int) $pdo->query('SELECT COUNT(*) FROM workspaces')->fetchColumn();
    if ($wsCount === 0) {
        $now = time();
        $stmt = $pdo->prepare('INSERT INTO workspaces (name, slug, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['Default workspace', 'default', $now]);
    }

    $seedBilling = $pdo->query('SELECT id FROM workspaces')->fetchAll(PDO::FETCH_COLUMN);
    $now = time();
    $insBilling = $pdo->prepare('INSERT OR IGNORE INTO workspace_billing (workspace_id, plan_key, status, stripe_customer_id, current_period_end, created_at, updated_at) VALUES (?, "free", "active", "", 0, ?, ?)');
    foreach ($seedBilling as $wsId) {
        $insBilling->execute([(int) $wsId, $now, $now]);
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

/**
 * @return array{user_id:int,workspace_id:int,role:string,name:string}
 */
function platform_create_workspace_owner(PDO $pdo, string $workspaceName, string $email, string $password, string $displayName = ''): array
{
    $workspaceName = trim($workspaceName);
    $email = trim($email);
    $displayName = trim($displayName);
    if ($workspaceName === '') {
        throw new RuntimeException('Workspace name is required');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Valid email is required');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters');
    }

    platform_schema_migrate($pdo);

    $check = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $check->execute([$email]);
    if ($check->fetchColumn() !== false) {
        throw new RuntimeException('An account with this email already exists');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Could not hash password');
    }

    $now = time();
    $slug = platform_unique_workspace_slug($pdo, $workspaceName);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO workspaces (name, slug, created_at) VALUES (?, ?, ?)')
            ->execute([$workspaceName, $slug, $now]);
        $workspaceId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO users (email, display_name, password_hash, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
            ->execute([$email, $displayName !== '' ? $displayName : $email, $hash, $now]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO workspace_members (workspace_id, user_id, role, created_at) VALUES (?, ?, "owner", ?)')
            ->execute([$workspaceId, $userId, $now]);

        $pdo->prepare('INSERT OR IGNORE INTO workspace_billing (workspace_id, plan_key, status, stripe_customer_id, current_period_end, created_at, updated_at) VALUES (?, "free", "active", "", 0, ?, ?)')
            ->execute([$workspaceId, $now, $now]);

        platform_audit_log($pdo, $workspaceId, $userId, 'workspace.created', 'workspace', (string) $workspaceId, [
            'workspace_name' => $workspaceName,
            'email' => $email,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'user_id' => $userId,
        'workspace_id' => $workspaceId,
        'role' => 'owner',
        'name' => $displayName !== '' ? $displayName : $email,
    ];
}
