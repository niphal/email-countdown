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
                auth_send_email_verification(db(), (int) $created['user_id']);
                $_SESSION['flash_verify_email'] = $email;
                header('Location: verify_notice.php', true, 302);
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
<html lang="en" data-theme="bitview">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign up — Email countdown</title>
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
  <div class="card w-full max-w-xl bg-base-100 shadow-md border border-base-300">
    <div class="card-body gap-5">
      <div class="flex flex-wrap gap-2">
        <a href="login.php" class="btn btn-sm btn-ghost">Sign in</a>
        <a href="signup.php" class="btn btn-sm btn-primary">Sign up</a>
        <a href="forgot_password.php" class="btn btn-sm btn-ghost">Forgot password</a>
      </div>
      <div>
        <h1 class="text-2xl font-bold tracking-tight" style="font-family:var(--font-display)">Create account</h1>
        <p class="text-sm text-base-content/60 mt-1">Start a workspace, verify your email, then build countdown timers for campaigns.</p>
      </div>
      <?php if ($error !== ''): ?><div role="alert" class="alert alert-error text-sm py-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" action="signup.php" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <fieldset class="fieldset p-0">
          <label class="label" for="workspace_name"><span class="label-text font-semibold">Workspace name</span></label>
          <input type="text" id="workspace_name" name="workspace_name" required placeholder="Acme Marketing" class="input input-bordered w-full">
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
          <fieldset class="fieldset p-0">
            <label class="label" for="display_name"><span class="label-text font-semibold">Your name</span></label>
            <input type="text" id="display_name" name="display_name" required placeholder="Jane Doe" class="input input-bordered w-full">
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="email"><span class="label-text font-semibold">Email</span></label>
            <input type="email" id="email" name="email" required autocomplete="username" placeholder="jane@company.com" class="input input-bordered w-full">
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="password"><span class="label-text font-semibold">Password</span></label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" class="input input-bordered w-full">
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="password2"><span class="label-text font-semibold">Repeat password</span></label>
            <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password" class="input input-bordered w-full">
          </fieldset>
        </div>
        <button type="submit" class="btn btn-primary w-full">Create workspace</button>
      </form>
      <p class="text-sm text-base-content/60 m-0"><a class="link link-primary" href="login.php">Already have an account? Sign in</a></p>
    </div>
  </div>
</body>
</html>
