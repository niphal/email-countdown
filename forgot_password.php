<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();

if (!auth_is_installed()) {
    header('Location: install.php', true, 302);
    exit;
}

$error = '';
$ok = false;
$isLoggedIn = auth_is_logged_in();
$canSeeAdmin = $isLoggedIn && auth_has_min_role(AUTH_ROLE_ADMIN);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } else {
        auth_request_password_reset((string) ($_POST['email'] ?? ''));
        $ok = true;
    }
}

$csrf = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot password — Email countdown</title>
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
        <a href="forgot_password.php" class="active">Forgot password</a>
      <?php endif; ?>
    </div>
    <h1>Forgot password</h1>
    <p>Enter your account email and we will generate a reset link.</p>
    <?php if ($error !== ''): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="ok">If an account exists, a reset link has been generated. Check your email server or `data/reset-links.log` on the host.</div><?php endif; ?>
    <form method="post" action="forgot_password.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="username">
      <button type="submit">Send reset link</button>
    </form>
    <p style="margin-top:0.8rem;"><a href="<?= $isLoggedIn ? 'index.php' : 'login.php' ?>"><?= $isLoggedIn ? 'Back to dashboard' : 'Back to sign in' ?></a></p>
  </div>
</body>
</html>

