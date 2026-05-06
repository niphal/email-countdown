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
    :root { --bg:#0f1117; --surface:#181c27; --border:#2a3142; --text:#e8eaef; --muted:#8b95a8; --accent:#f15bb5; --bad:#f87171; --ok:#7ae582; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:var(--font-body); background:var(--bg); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:420px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
    h1 { margin:0 0 0.75rem; font-family:var(--font-display); font-size:1.25rem; }
    p { color:var(--muted); font-size:0.9rem; }
    label { display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.35rem; margin-top:0.7rem; }
    input { width:100%; padding:0.6rem 0.65rem; border-radius:8px; border:1px solid var(--border); background:#0f1117; color:var(--text); }
    button { margin-top:1rem; width:100%; padding:0.65rem; border:0; border-radius:8px; font-weight:600; background:linear-gradient(135deg,var(--accent),#c44a92); color:#0f1117; cursor:pointer; }
    .err { color:var(--bad); font-size:0.88rem; margin-top:0.5rem; }
    .ok { color:var(--ok); font-size:0.88rem; margin-top:0.5rem; }
    a { color:#a5b4fc; text-decoration:none; }
  </style>
</head>
<body>
  <div class="card">
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
    <p style="margin-top:0.8rem;"><a href="login.php">Back to sign in</a></p>
  </div>
</body>
</html>

