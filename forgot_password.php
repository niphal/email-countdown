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
<html lang="en" data-theme="bitview">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot password — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="include/app.css">
  <style>
    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 1.25rem;
      background:
        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(37, 99, 235, 0.18), transparent),
        oklch(96% 0.005 250);
    }
  </style>
</head>
<body class="text-base-content">
  <div class="card w-full max-w-md bg-base-100 shadow-md border border-base-300">
    <div class="card-body gap-5">
      <div class="flex flex-wrap gap-2">
        <?php if ($isLoggedIn): ?>
          <a href="index.php" class="btn btn-sm btn-ghost">Dashboard</a>
          <?php if ($canSeeAdmin): ?><a href="admin.php" class="btn btn-sm btn-ghost">Admin</a><?php endif; ?>
          <a href="logout.php" class="btn btn-sm btn-ghost">Log out</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-sm btn-ghost">Sign in</a>
          <a href="signup.php" class="btn btn-sm btn-ghost">Sign up</a>
          <a href="forgot_password.php" class="btn btn-sm btn-primary">Forgot password</a>
        <?php endif; ?>
      </div>
      <div>
        <h1 class="text-2xl font-bold tracking-tight" style="font-family:var(--font-display)">Forgot password</h1>
        <p class="text-sm text-base-content/60 mt-1">Enter your account email and we will generate a reset link.</p>
      </div>
      <?php if ($error !== ''): ?><div role="alert" class="alert alert-error text-sm py-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($ok): ?><div role="alert" class="alert alert-success text-sm py-3">If an account exists, a reset link has been sent. Check your inbox or (for local <code class="bg-base-100 px-1 rounded">mail_transport=log</code>) <code class="bg-base-100 px-1 rounded">data/mail-out.log</code> plus <code class="bg-base-100 px-1 rounded">data/reset-links.log</code>.</div><?php endif; ?>
      <form method="post" action="forgot_password.php" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <fieldset class="fieldset p-0">
          <label class="label" for="email"><span class="label-text font-semibold">Email</span></label>
          <input type="email" id="email" name="email" required autocomplete="username" class="input input-bordered w-full">
        </fieldset>
        <button type="submit" class="btn btn-primary w-full">Send reset link</button>
      </form>
      <p class="text-sm text-base-content/60 m-0">
        <a class="link link-primary" href="<?= $isLoggedIn ? 'index.php' : 'login.php' ?>"><?= $isLoggedIn ? 'Back to dashboard' : 'Back to sign in' ?></a>
      </p>
    </div>
  </div>
</body>
</html>
