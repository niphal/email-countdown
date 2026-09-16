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
$emailVerifiedBanner = isset($_GET['email_verified']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh the page and try again.';
    } elseif (auth_is_locked()) {
        $error = 'Too many attempts. Try again in ' . auth_lock_seconds_remaining() . ' seconds.';
    } elseif (auth_attempt_login((string) ($_POST['password'] ?? ''), (string) ($_POST['email'] ?? ''))) {
        header('Location: ' . auth_redirect_target($_POST['next'] ?? ($_GET['next'] ?? null)));
        exit;
    } else {
        $block = auth_take_login_block();
        if ($block === AUTH_LOGIN_BLOCK_VERIFY_EMAIL) {
            $error = 'Confirm your email address before signing in. Check your inbox or resend the verification link.';
        } else {
            $error = 'Incorrect password.';
        }
    }
}

$csrf = auth_csrf_token();
$next = auth_redirect_target($_GET['next'] ?? null);
?>
<!DOCTYPE html>
<html lang="en" data-theme="bitview">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in — Email countdown</title>
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
        <a href="login.php" class="btn btn-sm btn-primary">Sign in</a>
        <a href="signup.php" class="btn btn-sm btn-ghost">Sign up</a>
        <a href="forgot_password.php" class="btn btn-sm btn-ghost">Forgot password</a>
      </div>
      <div>
        <h1 class="text-2xl font-bold tracking-tight" style="font-family:var(--font-display)">Sign in</h1>
        <p class="text-sm text-base-content/60 mt-1">Access your workspace to create countdown timers for email.</p>
      </div>
      <?php if ($installedBanner): ?><div role="alert" class="alert alert-success text-sm py-3">Installation finished. Sign in with the password you chose.</div><?php endif; ?>
      <?php if ($emailVerifiedBanner): ?><div role="alert" class="alert alert-success text-sm py-3">Email verified. You can sign in now.</div><?php endif; ?>
      <?php if ($error !== ''): ?><div role="alert" class="alert alert-error text-sm py-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" action="login.php" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
        <fieldset class="fieldset p-0">
          <label class="label" for="email"><span class="label-text font-semibold">Work email</span></label>
          <input type="email" id="email" name="email" required autocomplete="username" class="input input-bordered w-full" value="<?= htmlspecialchars(platform_seed_owner_email(), ENT_QUOTES, 'UTF-8') ?>">
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label" for="password"><span class="label-text font-semibold">Password</span></label>
          <input type="password" id="password" name="password" required autocomplete="current-password" autofocus class="input input-bordered w-full">
        </fieldset>
        <button type="submit" class="btn btn-primary w-full">Sign in</button>
      </form>
      <p class="text-sm text-base-content/60 m-0">
        <a class="link link-primary" href="forgot_password.php">Forgot password?</a>
        ·
        <a class="link link-primary" href="resend_verification.php">Resend verification</a>
      </p>
      <p class="text-sm text-base-content/60 m-0"><a class="link link-primary" href="signup.php">Need an account? Sign up</a></p>
    </div>
  </div>
</body>
</html>
