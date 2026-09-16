<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();

if (!auth_is_installed()) {
    header('Location: install.php', true, 302);
    exit;
}

$token = trim((string) ($_GET['token'] ?? ''));
$ok = auth_consume_email_verification_token($token);
if ($ok) {
    header('Location: login.php?email_verified=1', true, 302);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify email — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --bad:#b91c1c; --ok:#2e7d32; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:var(--font-body); background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:480px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.65rem; box-shadow:0 10px 28px rgba(17,24,39,.08); }
    h1 { font-family:var(--font-display); font-size:1.35rem; margin:0 0 0.75rem; font-weight:700; }
    p { color:var(--muted); font-size:0.95rem; line-height:1.55; margin:0 0 1rem; }
    .ok { color:var(--ok); font-weight:600; }
    .err { color:var(--bad); font-weight:600; }
    a { color:var(--accent); font-weight:600; text-decoration:none; }
    a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Verify email</h1>
    <p class="err">This verification link is invalid or has expired.</p>
    <p><a href="resend_verification.php">Request a new link</a> or <a href="login.php">return to sign in</a>.</p>
  </div>
</body>
</html>
