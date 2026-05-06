<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();

if (!auth_is_installed()) {
    header('Location: install.php', true, 302);
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$ok = false;
$isLoggedIn = auth_is_logged_in();
$canSeeAdmin = $isLoggedIn && auth_has_min_role(AUTH_ROLE_ADMIN);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } else {
        $pw = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } elseif (strlen($pw) < 8) {
            $error = 'Use at least 8 characters.';
        } elseif (!auth_consume_password_reset_token($token, $pw)) {
            $error = 'Reset token is invalid or expired.';
        } else {
            $ok = true;
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
  <title>Reset password — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --bad:#b91c1c; --ok:#2e7d32; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:var(--font-body); background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:420px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.5rem; box-shadow:0 10px 28px rgba(17,24,39,.08); }
    h1 { margin:0 0 0.75rem; font-family:var(--font-display); font-size:1.25rem; }
    p { color:var(--muted); font-size:0.9rem; }
    label { display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.35rem; margin-top:0.7rem; }
    input { width:100%; padding:0.6rem 0.65rem; border-radius:8px; border:1px solid var(--border); background:#ffffff; color:var(--text); }
    button { margin-top:1rem; width:100%; padding:0.65rem; border:0; border-radius:8px; font-weight:600; background:linear-gradient(135deg,var(--accent),var(--accent-dim)); color:#ffffff; cursor:pointer; }
    .err { color:var(--bad); font-size:0.88rem; margin-top:0.5rem; }
    .ok { color:var(--ok); font-size:0.88rem; margin-top:0.5rem; }
    .menu { display:flex; gap:.45rem; flex-wrap:wrap; margin:0 0 1rem; }
    .menu a { color:var(--text); text-decoration:none; border:1px solid var(--border); border-radius:999px; padding:.34rem .68rem; font-size:.78rem; font-weight:600; }
    .menu a.active { border-color:var(--accent); color:var(--accent); background:#f5fbf7; }
    a { color:var(--accent); text-decoration:none; font-weight:600; }
    a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <div class="card">
    <div class="menu">
      <?php if ($isLoggedIn): ?>
        <a href="index.php">Dashboard</a>
        <?php if ($canSeeAdmin): ?><a href="admin.php">Admin</a><?php endif; ?>
        <a href="logout.php">Log out</a>
      <?php else: ?>
        <a href="login.php">Sign in</a>
        <a href="signup.php">Sign up</a>
        <a href="forgot_password.php">Forgot password</a>
        <a href="reset_password.php" class="active">Reset password</a>
      <?php endif; ?>
    </div>
    <h1>Reset password</h1>
    <?php if ($ok): ?>
      <div class="ok">Password changed. <a href="login.php">Sign in now</a>.</div>
    <?php else: ?>
      <p>Choose a new password for your account.</p>
      <?php if ($error !== ''): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" action="reset_password.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <label for="password2">Repeat new password</label>
        <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
        <button type="submit">Reset password</button>
      </form>
    <?php endif; ?>
    <p style="margin-top:0.8rem;"><a href="<?= $isLoggedIn ? 'index.php' : 'login.php' ?>"><?= $isLoggedIn ? 'Back to dashboard' : 'Back to sign in' ?></a></p>
  </div>
</body>
</html>

