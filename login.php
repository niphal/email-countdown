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
    header('Location: ' . auth_redirect_target($_GET['next'] ?? null));
    exit;
}

$error = '';
$installedBanner = isset($_GET['installed']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh the page and try again.';
    } elseif (auth_is_locked()) {
        $error = 'Too many attempts. Try again in ' . auth_lock_seconds_remaining() . ' seconds.';
    } elseif (auth_attempt_login((string) ($_POST['password'] ?? ''), (string) ($_POST['email'] ?? ''))) {
        header('Location: ' . auth_redirect_target($_POST['next'] ?? ($_GET['next'] ?? null)));
        exit;
    } else {
        $error = 'Incorrect password.';
    }
}

$csrf = auth_csrf_token();
$next = auth_redirect_target($_GET['next'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --bad:#b91c1c; --ok:#2e7d32; --ring:rgba(0,66,37,.18); }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
      font-family:var(--font-body); background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:420px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.65rem; box-shadow:0 10px 28px rgba(17,24,39,.08); }
    h1 { font-family:var(--font-display); font-size:1.35rem; margin:0 0 0.35rem; font-weight:700; letter-spacing:-.01em; }
    label { display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.4rem; font-weight:600; }
    input[type="email"], input[type="password"] { width:100%; padding:0.6rem 0.65rem; border-radius:8px; border:1px solid var(--border);
      background:#ffffff; color:var(--text); font-size:1rem; margin-bottom:1rem; }
    input[type="email"]:focus, input[type="password"]:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--ring); outline:none; }
    button { font-family:var(--font-ui); width:100%; padding:0.65rem; border:0; border-radius:8px; font-weight:600; font-size:0.95rem; cursor:pointer;
      background:linear-gradient(135deg,var(--accent),var(--accent-dim)); color:#ffffff; transition:transform .12s ease, box-shadow .12s ease; }
    button:hover { transform:translateY(-1px); box-shadow:0 8px 18px rgba(0,66,37,.2); }
    .err { color:var(--bad); font-size:0.88rem; margin-bottom:0.75rem; }
    .hint { font-size:0.82rem; color:var(--muted); margin-top:1rem; line-height:1.45; }
    .ok { color:var(--ok); font-size:0.88rem; margin-bottom:0.75rem; }
    .hint a { color: var(--accent); font-weight: 600; text-decoration: none; }
    .hint a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Sign in</h1>
    <?php if ($installedBanner): ?><p class="ok">Installation finished. Sign in with the password you chose.</p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="login.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
      <label for="email">Work email</label>
      <input type="email" id="email" name="email" required autocomplete="username" value="<?= htmlspecialchars(platform_seed_owner_email(), ENT_QUOTES, 'UTF-8') ?>">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password" autofocus>
      <button type="submit">Continue</button>
    </form>
    <p class="hint" style="margin-top:0.6rem;"><a href="forgot_password.php">Forgot password?</a></p>
    <p class="hint" style="margin-top:0.3rem;"><a href="signup.php">Need an account? Sign up</a></p>
    <p class="hint">Default installs use the seeded owner mailbox above (password from install). Invite additional workspace members via the DB or a future admin UI.</p>
  </div>
</body>
</html>
