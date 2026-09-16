<?php

declare(strict_types=1);

require_once __DIR__ . '/app_shell.php';

if (!isset($appNav)) {
    $appNav = 'dashboard';
}
if (!isset($appTitle)) {
    $appTitle = 'Dashboard';
}
if (!isset($appSubtitle)) {
    $appSubtitle = '';
}

$wsName = app_shell_workspace_name();
$userLabel = app_shell_user_label();
$role = app_shell_role();
$plan = app_shell_plan_summary();
$isAdmin = auth_has_min_role(AUTH_ROLE_ADMIN);
$cssHref = (app_web_path_prefix() === '' ? '' : app_web_path_prefix()) . '/include/app.css';
if ($cssHref === '' || $cssHref[0] !== '/') {
    $cssHref = '/' . ltrim($cssHref, '/');
}
// Prefer relative path so it works under /email_timer/
$cssRel = 'include/app.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8') ?> — Email countdown</title>
  <?php require __DIR__ . '/google-fonts.php'; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($cssRel, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
  <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="brand-mark">E</div>
        <div class="brand-meta">
          <strong><?= htmlspecialchars($wsName, ENT_QUOTES, 'UTF-8') ?></strong>
          <span><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <nav class="nav-group" aria-label="Workspace">
        <div class="nav-label">Workspace</div>
        <a class="nav-link<?= $appNav === 'dashboard' ? ' active' : '' ?>" href="index.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"/></svg>
          Dashboard
        </a>
        <a class="nav-link<?= $appNav === 'templates' ? ' active' : '' ?>" href="templates.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/></svg>
          Templates
        </a>
      </nav>

      <?php if ($isAdmin): ?>
      <nav class="nav-group" aria-label="Admin">
        <div class="nav-label">Admin</div>
        <a class="nav-link<?= $appNav === 'integrations' ? ' active' : '' ?>" href="integrations.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 12h8M12 8v8"/><circle cx="12" cy="12" r="9"/></svg>
          Integrations
        </a>
        <a class="nav-link<?= $appNav === 'admin' ? ' active' : '' ?>" href="admin.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9c.2.6.7 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
          Settings
        </a>
      </nav>
      <?php endif; ?>

      <div class="nav-spacer"></div>
      <nav class="nav-group">
        <a class="nav-link logout" href="logout.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2"/><path d="M15 12H3m0 0 3-3m-3 3 3 3"/></svg>
          Log out
        </a>
      </nav>
    </aside>

    <div class="main">
      <header class="topbar">
        <div style="display:flex;align-items:center;gap:.7rem;min-width:0">
          <button type="button" class="mobile-nav-toggle secondary" id="nav-toggle" aria-label="Open menu">Menu</button>
          <div class="topbar-title">
            <h1><?= htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($appSubtitle !== ''): ?>
            <p><?= htmlspecialchars($appSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div class="topbar-actions">
          <div class="plan-chip">
            <strong><?= htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= (int) $plan['timer_count'] ?> / <?= (int) $plan['max_timers'] ?> timers</span>
          </div>
          <?php if (!empty($appTopActions)): ?>
            <?= $appTopActions ?>
          <?php endif; ?>
        </div>
      </header>
      <div class="content">
