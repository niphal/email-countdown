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

$key = 'flash_verify_email';
$email = isset($_SESSION[$key]) ? (string) $_SESSION[$key] : '';
unset($_SESSION[$key]);
if ($email === '') {
    header('Location: signup.php', true, 302);
    exit;
}

$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Check your email — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:var(--font-body); background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%); color:var(--text); padding:1rem; }
    .card { width:100%; max-width:520px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.65rem; box-shadow:0 10px 28px rgba(17,24,39,.08); }
    h1 { font-family:var(--font-display); font-size:1.35rem; margin:0 0 0.75rem; font-weight:700; letter-spacing:-.01em; }
    p { color:var(--muted); font-size:0.95rem; line-height:1.55; margin:0 0 1rem; }
    .menu { display:flex; gap:.45rem; flex-wrap:wrap; margin:0 0 1rem; }
    .menu a { color:var(--text); text-decoration:none; border:1px solid var(--border); border-radius:999px; padding:.34rem .68rem; font-size:.78rem; font-weight:600; }
    a { color:var(--accent); font-weight:600; text-decoration:none; }
    a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <div class="card">
    <div class="menu">
      <a href="login.php">Sign in</a>
      <a href="signup.php">Sign up</a>
      <a href="resend_verification.php">Resend link</a>
    </div>
    <h1>Confirm your email</h1>
    <p>We sent a verification link to <strong style="color:var(--text);"><?= $safeEmail ?></strong>. Open it to activate your workspace account, then sign in.</p>
    <p>If you do not see it within a few minutes, check spam folders or <a href="resend_verification.php">resend the link</a>.</p>
    <p><a href="login.php">Continue to sign in</a></p>
  </div>
</body>
</html>
