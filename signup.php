<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();

if (!auth_is_installed()) {
    header('Location: install.php', true, 302);
    exit;
}

if (auth_is_logged_in()) {
    header('Location: index.php', true, 302);
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } else {
        $workspaceName = trim((string) ($_POST['workspace_name'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $created = platform_create_workspace_owner(db(), $workspaceName, $email, $pw, $displayName);
                auth_login_success((int) $created['user_id'], (int) $created['workspace_id'], (string) $created['role'], (string) $created['name']);
                header('Location: index.php', true, 302);
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$csrf = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign up — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#0f1117; --surface:#181c27; --border:#2a3142; --text:#e8eaef; --muted:#8b95a8; --accent:#f15bb5; --bad:#f87171; }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:var(--font-body); background:var(--bg); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:500px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
    h1 { font-family:var(--font-display); font-size:1.25rem; margin:0 0 1rem; font-weight:700; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
    label { display:block; font-size:.8rem; color:var(--muted); margin-bottom:.35rem; }
    input { width:100%; padding:.6rem .65rem; border-radius:8px; border:1px solid var(--border); background:#0f1117; color:var(--text); font-size:1rem; }
    button { margin-top:1rem; width:100%; padding:.65rem; border:0; border-radius:8px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,var(--accent),#c44a92); color:#0f1117; }
    .err { color:var(--bad); font-size:.88rem; margin-bottom:.75rem; }
    .hint { font-size:.82rem; color:var(--muted); margin-top:.8rem; }
    a { color:#a5b4fc; text-decoration:none; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Create account</h1>
    <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="signup.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <div class="grid">
        <div style="grid-column:1 / -1;">
          <label for="workspace_name">Workspace name</label>
          <input type="text" id="workspace_name" name="workspace_name" required placeholder="Acme Marketing">
        </div>
        <div>
          <label for="display_name">Your name</label>
          <input type="text" id="display_name" name="display_name" required placeholder="Jane Doe">
        </div>
        <div>
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="username" placeholder="jane@company.com">
        </div>
        <div>
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div>
          <label for="password2">Repeat password</label>
          <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      <button type="submit">Create workspace</button>
    </form>
    <p class="hint"><a href="login.php">Already have an account? Sign in</a></p>
  </div>
</body>
</html>

