<?php

declare(strict_types=1);

/**
 * Web installer (WordPress-style): requirements check + admin password.
 * After success, open login.php. CLI: use scripts/setup_secrets.php instead.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/auth.php';

auth_start_session();

if (auth_is_installed()) {
    $isLoggedIn = auth_is_logged_in();
    $canSeeAdmin = $isLoggedIn && auth_has_min_role(AUTH_ROLE_ADMIN);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Already installed</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#0f1117; --surface:#181c27; --border:#2a3142; --text:#e8eaef; --muted:#8b95a8; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:var(--font-body); background:var(--bg); color:var(--text); padding:1rem; }
    .card { max-width:420px; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
    h1 { font-family:var(--font-display); font-size:1.2rem; margin:0 0 0.75rem; font-weight:700; }
    p { color:var(--muted); line-height:1.5; margin:0 0 1rem; }
    a { color:#a5b4fc; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Already installed</h1>
    <p>This app has a dashboard password configured. To reinstall, remove <code>data/secrets.php</code> on the server (and keep a backup if needed), then reload this page.</p>
    <p>
      <?php if ($isLoggedIn): ?>
        <a href="index.php">Dashboard</a>
        <?php if ($canSeeAdmin): ?> · <a href="admin.php">Admin</a><?php endif; ?>
        · <a href="logout.php">Log out</a>
      <?php else: ?>
        <a href="login.php">Sign in</a> · <a href="signup.php">Sign up</a> · <a href="forgot_password.php">Forgot password</a>
      <?php endif; ?>
    </p>
  </div>
</body>
</html>
    <?php
    exit;
}

function install_requirements(): array
{
    $dataDir = APP_ROOT . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }

    $fontDir = APP_ROOT . '/data/fonts';
    if (!is_dir($fontDir)) {
        @mkdir($fontDir, 0755, true);
    }

    $checks = [
        'php' => [
            'label' => 'PHP 8.0+',
            'ok' => PHP_VERSION_ID >= 80000,
            'detail' => PHP_VERSION,
        ],
        'gd' => [
            'label' => 'GD extension',
            'ok' => extension_loaded('gd') && function_exists('imagecreatetruecolor'),
            'detail' => extension_loaded('gd') ? 'loaded' : 'missing',
        ],
        'gd_freetype' => [
            'label' => 'GD + FreeType (TrueType in images)',
            'ok' => timer_gd_has_freetype(),
            'detail' => timer_gd_has_freetype() ? 'enabled' : 'missing — reinstall PHP with GD linked to FreeType',
        ],
        'font_http' => [
            'label' => 'HTTPS font download (curl or allow_url_fopen)',
            'ok' => timer_can_fetch_remote_fonts(),
            'detail' => timer_can_fetch_remote_fonts() ? 'ok' : 'enable curl extension or allow_url_fopen in php.ini',
        ],
        'fonts_dir' => [
            'label' => 'data/fonts writable',
            'ok' => is_dir($fontDir) && is_writable($fontDir),
            'detail' => is_dir($fontDir) ? (is_writable($fontDir) ? 'writable' : 'not writable') : 'missing',
        ],
        'pdo_sqlite' => [
            'label' => 'PDO SQLite',
            'ok' => extension_loaded('pdo_sqlite'),
            'detail' => extension_loaded('pdo_sqlite') ? 'loaded' : 'missing',
        ],
        'mbstring' => [
            'label' => 'mbstring',
            'ok' => extension_loaded('mbstring'),
            'detail' => extension_loaded('mbstring') ? 'loaded' : 'missing',
        ],
        'data_writable' => [
            'label' => 'data/ directory writable',
            'ok' => is_dir($dataDir) && is_writable($dataDir),
            'detail' => is_dir($dataDir) ? (is_writable($dataDir) ? 'writable' : 'not writable') : 'missing',
        ],
    ];

    return $checks;
}

function install_all_ok(array $checks): bool
{
    foreach ($checks as $c) {
        if (!$c['ok']) {
            return false;
        }
    }

    return true;
}

$error = '';
$checks = install_requirements();
$canInstall = install_all_ok($checks);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$canInstall) {
        $error = 'Fix the requirements below first.';
    } elseif (!auth_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } else {
        $pw = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if (strlen($pw) < 8) {
            $error = 'Use at least 8 characters for the password.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            $public = trim((string) ($_POST['public_base_url'] ?? ''));
            if ($public !== '') {
                if (!preg_match('#^https://#i', $public)) {
                    $error = 'Public URL for images must start with https://';
                } elseif (filter_var($public, FILTER_VALIDATE_URL) === false) {
                    $error = 'Invalid public URL for images.';
                }
            }
            if ($error !== '') {
                // leave POST handler; fall through to form
            } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            if ($hash === false) {
                $error = 'Could not hash password.';
            } else {
                $target = auth_secrets_path();
                $signingKey = bin2hex(random_bytes(32));
                $body = app_format_secrets_php($hash, $public !== '' ? $public : null, $signingKey);
                if (file_put_contents($target, $body) === false) {
                    $error = 'Could not write data/secrets.php. Check permissions on data/.';
                } else {
                    $pdo = db();
                    platform_seed_owner_user_from_secrets($pdo);
                    timer_font_warm_all();
                    header('Location: login.php?installed=1', true, 302);
                    exit;
                }
            }
            }
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
  <title>Install — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --bad:#b91c1c; --ok:#2e7d32; }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:var(--font-body); background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%); color:var(--text); padding:1.25rem; line-height:1.45; }
    .wrap { max-width:520px; margin:0 auto; }
    h1 { font-family:var(--font-display); font-size:1.35rem; margin:0 0 0.35rem; font-weight:700; }
    .sub { color:var(--muted); font-size:0.95rem; margin-bottom:1.5rem; }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1rem; box-shadow:0 8px 24px rgba(17,24,39,.06); }
    h2 { font-family:var(--font-ui); font-size:0.95rem; margin:0 0 0.75rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.04em; }
    ul.reqs { list-style:none; margin:0; padding:0; font-size:0.9rem; }
    ul.reqs li { padding:0.55rem 0; border-bottom:1px solid var(--border); display:grid; grid-template-columns:1fr auto; gap:0.35rem 1rem; align-items:start; }
    ul.reqs li:last-child { border-bottom:0; }
    ul.reqs .detail { grid-column:1 / -1; color:var(--muted); font-size:0.82rem; margin-top:-0.1rem; }
    .pass { color:var(--ok); font-weight:600; }
    .fail { color:var(--bad); font-weight:600; }
    label { display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.35rem; margin-top:0.75rem; }
    label:first-of-type { margin-top:0; }
    .menu { display:flex; gap:.5rem; flex-wrap:wrap; margin:0 0 1rem; }
    .menu a { color:var(--text); text-decoration:none; border:1px solid var(--border); border-radius:999px; padding:.35rem .72rem; font-size:.8rem; font-weight:600; }
    .menu a.active { border-color:var(--accent); color:var(--accent); background:#f5fbf7; }
    input[type="password"], input[type="url"] { width:100%; padding:0.6rem 0.65rem; border-radius:8px; border:1px solid var(--border); background:#ffffff; color:var(--text); font-size:1rem; }
    button { font-family:var(--font-ui); margin-top:1rem; width:100%; padding:0.65rem; border:0; border-radius:8px; font-weight:600; font-size:0.95rem; cursor:pointer;
      background:linear-gradient(135deg,var(--accent),var(--accent-dim)); color:#ffffff; }
    button:disabled { opacity:0.45; cursor:not-allowed; }
    .err { color:var(--bad); font-size:0.88rem; margin-bottom:0.75rem; }
    .foot { font-size:0.82rem; color:var(--muted); margin-top:1.25rem; }
    code { font-size:0.85em; background:#eef3ef; padding:0.1em 0.35em; border-radius:4px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="menu">
      <a href="install.php" class="active">Install</a>
      <a href="login.php">Sign in</a>
      <a href="signup.php">Sign up</a>
      <a href="forgot_password.php">Forgot password</a>
    </div>
    <h1>Install Email countdown</h1>
    <p class="sub">Like WordPress: check the server, set your dashboard password, then sign in. Timer images stay public for email clients.</p>

    <div class="card">
      <h2>Requirements</h2>
      <ul class="reqs">
        <?php foreach ($checks as $c): ?>
        <li>
          <span><?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="<?= $c['ok'] ? 'pass' : 'fail' ?>" style="text-align:right"><?= $c['ok'] ? 'OK' : 'Fix' ?></span>
          <span class="detail"><?= htmlspecialchars((string) $c['detail'], ENT_QUOTES, 'UTF-8') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h2>Administrator password</h2>
      <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post" action="install.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label for="password">Password (min 8 characters)</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" <?= $canInstall ? '' : 'disabled' ?>>
        <label for="password2">Repeat password</label>
        <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password" <?= $canInstall ? '' : 'disabled' ?>>
        <label for="public_base_url">Public site URL for email images (optional, <code>https://…</code>, no trailing slash)</label>
        <input type="url" id="public_base_url" name="public_base_url" placeholder="https://countdown.example.com" autocomplete="off" <?= $canInstall ? '' : 'disabled' ?>>
        <p class="foot" style="margin-top:0.5rem">If you skip this, the app uses the same host you use to open this installer (fine for production; use a CDN URL here if images are served from another domain).</p>
        <button type="submit" <?= $canInstall ? '' : 'disabled' ?>>Install</button>
      </form>
      <p class="foot" style="margin-top:1rem">Command line: <code>php scripts/setup_secrets.php &quot;…&quot;</code> (add <code>public_base_url</code> to <code>data/secrets.php</code> by hand if needed).</p>
    </div>
  </div>
</body>
</html>
