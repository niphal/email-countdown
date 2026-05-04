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
    } elseif (auth_attempt_login((string) ($_POST['password'] ?? ''))) {
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
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#0f1117; --surface:#181c27; --border:#2a3142; --text:#e8eaef; --muted:#8b95a8; --accent:#f15bb5; --bad:#f87171; }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
      font-family:"DM Sans",system-ui,sans-serif; background:var(--bg); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:380px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
    h1 { font-size:1.2rem; margin:0 0 1rem; font-weight:600; }
    label { display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.35rem; }
    input[type="password"] { width:100%; padding:0.6rem 0.65rem; border-radius:8px; border:1px solid var(--border);
      background:#0f1117; color:var(--text); font-size:1rem; margin-bottom:1rem; }
    button { width:100%; padding:0.65rem; border:0; border-radius:8px; font-weight:600; font-size:0.95rem; cursor:pointer;
      background:linear-gradient(135deg,var(--accent),#c44a92); color:#0f1117; }
    .err { color:var(--bad); font-size:0.88rem; margin-bottom:0.75rem; }
    .hint { font-size:0.82rem; color:var(--muted); margin-top:1rem; line-height:1.45; }
    .ok { color:#7ae582; font-size:0.88rem; margin-bottom:0.75rem; }
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
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password" autofocus>
      <button type="submit">Continue</button>
    </form>
  </div>
</body>
</html>
