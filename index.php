<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/lib/monetization.php';
require_once __DIR__ . '/auth.php';
auth_start_session();
auth_require_login_redirect();
// Touch DB so fresh installs have data dir
db();

$workspaceBanner = '';
$cu = auth_current_user();
if ($cu !== null) {
    $wst = db()->prepare('SELECT name FROM workspaces WHERE id = ?');
    $wst->execute([(int) $cu['workspace_id']]);
    $wn = $wst->fetchColumn();
    if ($wn !== false) {
        $workspaceBanner = (string) $wn;
    }
}

$timerPreviewPrefix = app_timer_url_prefix();
$timerEmbedPrefix = app_timer_embed_src_prefix();
$embedNeedsPublicBase = str_starts_with($timerEmbedPrefix, '/');
$embedIsHttpsAbsolute = app_embed_is_https_absolute();
$embedBlockedReason = '';
if ($embedNeedsPublicBase) {
    $embedBlockedReason = 'root-relative';
} elseif (!$embedIsHttpsAbsolute) {
    $embedBlockedReason = 'not-https';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email countdown timers</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root {
      --bg: #f3f5f4;
      --surface: #ffffff;
      --border: #d9e2dc;
      --text: #0f1720;
      --muted: #5c6b62;
      --accent: #004225;
      --accent-dim: #0a5a36;
      --ok: #2e7d32;
      --ring: rgba(0, 66, 37, 0.18);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: var(--font-body);
      background: linear-gradient(180deg, #f8faf9 0%, var(--bg) 100%);
      color: var(--text);
      line-height: 1.5;
    }
    .wrap { max-width: 1040px; margin: 0 auto; padding: 2.5rem 1.35rem 4rem; }
    h1 {
      font-family: var(--font-display);
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin: 0 0 0.35rem;
    }
    .lede { color: var(--muted); max-width: 48ch; margin: 0.55rem 0 1.1rem; font-size: 0.98rem; }
    .steps {
      display: flex; flex-wrap: wrap; gap: 0.55rem; margin: 0 0 1.35rem; padding: 0; list-style: none;
    }
    .steps li {
      font-size: 0.82rem; font-weight: 600; color: var(--muted);
      background: #ffffff; border: 1px solid var(--border); border-radius: 999px; padding: 0.35rem 0.75rem;
    }
    .steps li strong { color: var(--accent); margin-right: 0.25rem; }
    .status-bar {
      display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;
      background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
      padding: 0.85rem 1.1rem; margin-bottom: 1.2rem; box-shadow: 0 4px 14px rgba(17,24,39,0.04);
    }
    .status-bar .plan-name { font-weight: 700; }
    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.35rem 1.55rem;
      margin-bottom: 1.2rem;
      box-shadow: 0 6px 18px rgba(17, 24, 39, 0.06);
    }
    .panel h2 { font-family: var(--font-ui); font-size: 1.02rem; margin: 0 0 0.35rem; font-weight: 700; letter-spacing: 0.01em; }
    .panel-lead { color: var(--muted); font-size: 0.88rem; margin: 0 0 1rem; }
    .appearance {
      margin-top: 0.25rem; border: 1px solid var(--border); border-radius: 10px; padding: 0.75rem 1rem 1rem;
      background: #fbfcfb;
    }
    .appearance > summary {
      cursor: pointer; font-weight: 700; font-size: 0.88rem; color: var(--text); list-style: none;
    }
    .appearance > summary::-webkit-details-marker { display: none; }
    .appearance > summary::after { content: "Show"; float: right; color: var(--accent); font-weight: 600; font-size: 0.8rem; }
    .appearance[open] > summary::after { content: "Hide"; }
    .appearance .grid { margin-top: 0.9rem; }
    .alert {
      margin: 0 0 1.2rem; padding: 0.85rem 1rem; border-radius: 10px; font-size: 0.9rem;
      background: rgba(185,28,28,0.08); border: 1px solid #efcaca; color: var(--text);
    }
    .help-block { margin: 0; }
    .help-block summary { cursor: pointer; font-weight: 700; color: var(--text); }
    .help-block .note { margin-top: 0.75rem; }
    .timer-card {
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.1rem;
      margin-bottom: 1rem;
      background: #ffffff;
    }
    .timer-card-head { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:flex-start; }
    .timer-card h3 { font-family: var(--font-accent); margin: 0 0 0.3rem; font-size: 1.05rem; font-weight: 700; }
    .timer-card .meta { font-size: 0.8rem; color: var(--muted); }
    .timer-card .meta span { display:inline-block; margin-right: 0.65rem; }
    .preview { margin-top: 0.85rem; }
    .preview img { max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--border); background:#0f1720; }
    .embed {
      display: none;
      font-family: var(--font-mono);
      font-size: 0.72rem;
      line-height: 1.4;
      background: #f8faf9;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.75rem;
      overflow-x: auto;
      white-space: pre-wrap;
      word-break: break-all;
      margin-top: 0.75rem;
    }
    .embed.show { display: block; }
    .actions-primary { display:flex; flex-wrap:wrap; gap:0.55rem; margin-top: 0.9rem; }
    .actions-secondary { display:flex; flex-wrap:wrap; gap:0.45rem; margin-top: 0.55rem; }
    .empty-state {
      text-align: left; padding: 1.1rem 0 0.3rem; color: var(--muted);
    }
    .empty-state strong { display:block; color: var(--text); margin-bottom: 0.25rem; }
    .field-hint { font-size: 0.75rem; color: var(--muted); margin-top: 0.3rem; }
    label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 0.42rem; font-weight: 600; }
    input[type="text"], input[type="datetime-local"], input[type="number"], select {
      width: 100%;
      padding: 0.55rem 0.65rem;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: #ffffff;
      color: var(--text);
      font-family: inherit;
      font-size: 0.95rem;
    }
    input[type="text"]:focus, input[type="datetime-local"]:focus, input[type="number"]:focus, input[type="color"]:focus, select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--ring);
      outline: none;
    }
    input[type="color"] {
      width: 100%;
      height: 40px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: #ffffff;
      cursor: pointer;
    }
    .grid { display: grid; gap: 1rem; }
    @media (min-width: 640px) {
      .grid-2 { grid-template-columns: 1fr 1fr; }
      .grid-3 { grid-template-columns: repeat(3, 1fr); }
    }
    button {
      font-family: var(--font-ui);
      font-weight: 600;
      font-size: 0.9rem;
      padding: 0.65rem 1.1rem;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      background: linear-gradient(135deg, var(--accent), var(--accent-dim));
      color: #ffffff;
      transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    button:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(0, 66, 37, 0.2); }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    button.secondary {
      background: #ffffff;
      color: var(--text);
      border: 1px solid var(--border);
    }
    button.danger { background: #ffffff; color: #9b1c1c; border: 1px solid #efcaca; }
    .row-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 1rem; }
    .note { font-size: 0.85rem; color: var(--muted); margin-top: 1rem; }
    .toast { font-family: var(--font-ui); position: fixed; bottom: 1.25rem; right: 1.25rem; background: #ffffff; border: 1px solid var(--border); box-shadow: 0 8px 24px rgba(17,24,39,0.12); padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.9rem; display: none; }
    .toast.show { display: block; }
    .empty { color: var(--muted); font-size: 0.95rem; }
    .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e6ece8; }
    .topbar h1 { margin-bottom: 0; }
    .ws-pill { font-size: 0.82rem; color: var(--muted); font-family: var(--font-mono); margin-top: 0.35rem; }
    .audit-lines { font-family: var(--font-mono); font-size: 0.75rem; color: var(--muted); line-height: 1.55; max-height: 180px; overflow-y: auto; }
    .audit-lines div { padding: 0.2rem 0; border-bottom: 1px solid var(--border); }
    .billing-kpis { display:flex; gap:1rem; flex-wrap:wrap; font-size:0.82rem; color:var(--muted); }
    .billing-kpis span strong { color: var(--text); }
    .menu { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .55rem; }
    .menu a { color: var(--text); text-decoration: none; border: 1px solid var(--border); border-radius: 999px; padding: .38rem .75rem; font-size: .8rem; font-weight: 600; }
    .menu a.active { border-color: var(--accent); color: var(--accent); background: #f5fbf7; }
    a.logout { color: var(--muted); font-size: 0.88rem; text-decoration: none; padding: 0.45rem 0; font-weight: 600; }
    a.logout:hover { color: var(--text); text-decoration: underline; }
    @media (max-width: 640px) {
      .wrap { padding: 1.2rem 0.9rem 2.6rem; }
      h1 { font-size: 1.55rem; }
      .panel { padding: 1rem; }
      .row-actions button, .actions-primary button, .actions-secondary button { width: 100%; }
      .menu { margin-top: 0.7rem; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h1>Email countdown timers</h1>
        <?php if ($workspaceBanner !== ''): ?><div class="ws-pill">Workspace: <?= htmlspecialchars($workspaceBanner, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="menu">
          <a href="index.php" class="active">Dashboard</a>
          <?php if (auth_has_min_role(AUTH_ROLE_ADMIN)): ?><a href="admin.php">Admin</a><?php endif; ?>
        </div>
      </div>
      <a class="logout" href="logout.php">Log out</a>
    </div>
    <p class="lede">Build a countdown, preview it, then copy Gmail-safe HTML into your ESP. Timers are animated GIFs (no JavaScript in email).</p>
    <ol class="steps" aria-label="How to use">
      <li><strong>1</strong> Create</li>
      <li><strong>2</strong> Preview</li>
      <li><strong>3</strong> Copy for email</li>
    </ol>

    <?php if ($embedBlockedReason === 'root-relative'): ?>
    <div class="alert" role="alert"><strong>Gmail will not load these images yet.</strong> Embed URLs are still root-relative. Add <code>'public_base_url' =&gt; 'https://your-public-site'</code> to <code>data/secrets.php</code> (no trailing slash), or open this dashboard on your public HTTPS URL.</div>
    <?php elseif ($embedBlockedReason === 'not-https'): ?>
    <div class="alert" role="alert"><strong>Gmail requires HTTPS image URLs.</strong> Set <code>'public_base_url' =&gt; 'https://…'</code> in <code>data/secrets.php</code>. Copy is disabled until that is fixed.</div>
    <?php endif; ?>

    <div class="status-bar" id="billing-box">
      <div>
        <div id="billing-title" class="plan-name">Plan: Loading…</div>
        <div id="billing-kpis" class="billing-kpis"></div>
      </div>
      <button type="button" id="btn-upgrade" class="secondary">Upgrade</button>
    </div>

    <div class="panel">
      <h2>Create timer</h2>
      <p class="panel-lead">Name it, set when it ends, then create. Open Appearance only if you need colors, size, or layout.</p>
      <form id="create-form">
        <div class="grid grid-2">
          <div>
            <label for="name">Internal name</label>
            <input type="text" id="name" name="name" required placeholder="Spring sale ends" autocomplete="off">
            <p class="field-hint">Only shown in this dashboard</p>
          </div>
          <div>
            <label for="ends">Ends at (your local time)</label>
            <input type="datetime-local" id="ends" name="ends" required>
            <p class="field-hint">Stored and shown in email as UTC</p>
          </div>
          <div style="grid-column:1 / -1;">
            <label for="label">Optional line under countdown</label>
            <input type="text" id="label" name="label" placeholder="Use code SAVE20" autocomplete="off">
          </div>
        </div>
        <details class="appearance" id="appearance">
          <summary>Appearance</summary>
          <div class="grid grid-2">
            <div class="grid grid-3" style="grid-column:1 / -1;">
              <div><label for="bg">Background</label><input type="color" id="bg" value="#1a1a2e"></div>
              <div><label for="fg">Text</label><input type="color" id="fg" value="#eaeaea"></div>
              <div><label for="ac">Countdown</label><input type="color" id="ac" value="#e94560"></div>
            </div>
            <div>
              <label for="width">Width (px)</label>
              <input type="number" id="width" value="480" min="200" max="600" step="10">
              <p class="field-hint">480–560 works best in mobile Gmail</p>
            </div>
            <div>
              <label for="height">Height (px)</label>
              <input type="number" id="height" value="120" min="80" max="300" step="10">
            </div>
            <div>
              <label for="layout_key">Layout</label>
              <select id="layout_key" name="layout_key">
                <?php foreach (timer_layout_labels() as $val => $lab): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="font_key">Font</label>
              <select id="font_key" name="font_key">
                <?php foreach (timer_font_labels() as $val => $lab): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="font_size_main">Main size (px)</label>
              <input type="number" id="font_size_main" value="32" min="14" max="72" step="1">
            </div>
          </div>
        </details>
      </form>
      <div class="row-actions">
        <button type="submit" form="create-form" id="btn-create">Create timer</button>
        <button type="button" id="btn-cancel-edit" class="secondary" style="display:none;">Cancel edit</button>
      </div>
    </div>

    <div class="panel">
      <h2>Your timers</h2>
      <p class="panel-lead">Preview first, then copy HTML for your ESP. Advanced options stay secondary.</p>
      <div id="list"><p class="empty">Loading…</p></div>
    </div>

    <div class="panel">
      <details class="help-block">
        <summary>Gmail &amp; ESP tips</summary>
        <p class="note">
          Use <strong>Copy for Gmail</strong> (table wrapper, width/height, deadline in <code>alt</code>). Image URLs must be absolute HTTPS via <code>public_base_url</code>.
          The GIF plays ~15 seconds once; Gmail’s proxy caches it—first open is accurate, re-opens may look slightly stale.
          Replace <code>https://example.com/cta</code> with your landing URL. Optional <code>&amp;v=CAMPAIGN_ID</code> isolates caches between sends.
          <strong>Copy PNG URL</strong> is for static fallbacks. <strong>Copy Dynamic HTML</strong> adds Braze Liquid <code>&amp;end={{event_properties.end_ts}}</code> with a signed <code>sig</code>.
          QA: Litmus/Email on Acid → Gmail web, iOS, Android, Outlook desktop (first frame), Apple Mail.
        </p>
      </details>
    </div>

    <div class="panel">
      <details class="help-block">
        <summary>Workspace activity</summary>
        <p class="note" style="margin-top:0.5rem;">Recent changes in this workspace (owner / admin / editor).</p>
        <div id="audit" class="audit-lines"><span class="empty">Loading…</span></div>
      </details>
    </div>
  </div>
  <div id="toast" class="toast" role="status"></div>

  <script>
    const API = 'api/timers.php';
    const BILLING_API = 'api/billing.php';
    const AUDIT_API = 'api/audit.php';
    /** Root-relative: dashboard preview only */
    const TIMER_PREVIEW_PREFIX = <?= json_encode($timerPreviewPrefix, JSON_THROW_ON_ERROR) ?>;
    /** Absolute https? URL for pasted email HTML (from request host or public_base_url in secrets) */
    const TIMER_EMBED_PREFIX = <?= json_encode($timerEmbedPrefix, JSON_THROW_ON_ERROR) ?>;
    const EMBED_HTTPS_OK = <?= $embedIsHttpsAbsolute ? 'true' : 'false' ?>;
    let editingId = null;
    let currentTimers = [];
    let entitlements = null;

    function toast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t._h);
      t._h = setTimeout(() => t.classList.remove('show'), 2200);
    }

    function requireHttpsEmbed() {
      if (EMBED_HTTPS_OK) return true;
      toast('Set public_base_url to https://… before copying for Gmail');
      return false;
    }

    function deadlineAlt(endsAt) {
      const d = new Date(Number(endsAt || 0) * 1000);
      if (Number.isNaN(d.getTime())) return 'Countdown timer';
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const day = d.getUTCDate();
      const mon = months[d.getUTCMonth()];
      const year = d.getUTCFullYear();
      const hh = String(d.getUTCHours()).padStart(2, '0');
      const mm = String(d.getUTCMinutes()).padStart(2, '0');
      return 'Countdown — Ends ' + day + ' ' + mon + ' ' + year + ' ' + hh + ':' + mm + ' UTC';
    }

    function timerSrc(id, opts) {
      opts = opts || {};
      let src = TIMER_EMBED_PREFIX + encodeURIComponent(id);
      if (opts.format === 'png') src += (src.includes('?') ? '&' : '?') + 'format=png';
      // Optional campaign cache-bust token (ignored by renderer; helps isolate sends in some proxies).
      if (opts.v) src += '&v=' + encodeURIComponent(String(opts.v));
      if (opts.endLiquid) {
        src += '&end={{event_properties.end_ts}}';
        if (opts.sig) src += '&sig=' + encodeURIComponent(opts.sig);
      }
      return src;
    }

    function embedHtml(id, width, height, endsAt) {
      const w = width || 480;
      const h = height || 120;
      const src = timerSrc(id);
      const alt = deadlineAlt(endsAt);
      return '<!-- Email countdown: replace CTA href. Optional &v=campaign_id for cache isolation. -->\n' +
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="' + w + '" style="border-collapse:collapse;max-width:100%;">\n' +
        '  <tr>\n' +
        '    <td align="center" style="padding:0;">\n' +
        '      <a href="https://example.com/cta" target="_blank" style="text-decoration:none;border:0;">\n' +
        '        <img src="' + src + '" width="' + w + '" height="' + h + '" alt="' + alt.replace(/"/g, '&quot;') + '" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;" />\n' +
        '      </a>\n' +
        '    </td>\n' +
        '  </tr>\n' +
        '</table>';
    }

    function embedHtmlDynamic(id, width, height, endsAt, sig) {
      const w = width || 480;
      const h = height || 120;
      const src = timerSrc(id, { endLiquid: true, sig: sig || '' });
      const alt = deadlineAlt(endsAt);
      return '<!-- Dynamic countdown: requires signed end override. Replace CTA href. -->\n' +
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="' + w + '" style="border-collapse:collapse;max-width:100%;">\n' +
        '  <tr>\n' +
        '    <td align="center" style="padding:0;">\n' +
        '      <a href="https://example.com/cta" target="_blank" style="text-decoration:none;border:0;">\n' +
        '        <img src="' + src + '" width="' + w + '" height="' + h + '" alt="' + alt.replace(/"/g, '&quot;') + '" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;" />\n' +
        '      </a>\n' +
        '    </td>\n' +
        '  </tr>\n' +
        '</table>';
    }

    function pngFallbackUrl(id) {
      return timerSrc(id, { format: 'png' });
    }

    function toUnix(s) {
      const d = new Date(s);
      return Math.floor(d.getTime() / 1000);
    }

    function toLocalDateTimeValue(unixTs) {
      const d = new Date(unixTs * 1000);
      const pad = n => String(n).padStart(2, '0');
      const y = d.getFullYear();
      const m = pad(d.getMonth() + 1);
      const day = pad(d.getDate());
      const hh = pad(d.getHours());
      const mm = pad(d.getMinutes());
      return `${y}-${m}-${day}T${hh}:${mm}`;
    }

    function resetCreateForm() {
      document.getElementById('create-form').reset();
      document.getElementById('bg').value = '#1a1a2e';
      document.getElementById('fg').value = '#eaeaea';
      document.getElementById('ac').value = '#e94560';
      document.getElementById('width').value = '480';
      document.getElementById('height').value = '120';
      document.getElementById('font_key').value = 'noto_sans_bold';
      document.getElementById('font_size_main').value = '32';
      document.getElementById('layout_key').value = 'segmented_pills';
      document.getElementById('btn-create').textContent = 'Create timer';
      document.getElementById('btn-cancel-edit').style.display = 'none';
      document.getElementById('appearance').open = false;
      editingId = null;
      applyPlanGates();
    }

    function startEdit(t) {
      editingId = t.id;
      document.getElementById('name').value = t.name || '';
      document.getElementById('ends').value = toLocalDateTimeValue(Number(t.ends_at || 0));
      document.getElementById('label').value = t.label || '';
      document.getElementById('bg').value = t.bg_color || '#1a1a2e';
      document.getElementById('fg').value = t.text_color || '#eaeaea';
      document.getElementById('ac').value = t.accent_color || '#e94560';
      document.getElementById('width').value = String(Number(t.width || 480));
      document.getElementById('height').value = String(Number(t.height || 120));
      document.getElementById('font_key').value = t.font_key || 'noto_sans_bold';
      document.getElementById('font_size_main').value = String(Number(t.font_size_main || 32));
      document.getElementById('layout_key').value = t.layout_key || 'segmented_pills';
      document.getElementById('appearance').open = true;
      document.getElementById('btn-create').textContent = 'Save changes';
      document.getElementById('btn-cancel-edit').style.display = 'inline-block';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      applyPlanGates();
    }

    function applyPlanGates() {
      if (!entitlements) return;
      const maxed = Number(entitlements.timer_count || 0) >= Number(entitlements.max_timers || 0);
      const premiumLayouts = !!entitlements.allow_premium_layouts;
      const premiumFonts = !!entitlements.allow_premium_fonts;
      const allowedLayouts = premiumLayouts
        ? ['segmented_pills','split_emphasis','minimal_editorial','progress_hybrid','badge_countdown']
        : ['segmented_pills','split_emphasis','minimal_editorial'];
      const allowedFonts = premiumFonts
        ? ['noto_sans_bold','noto_sans','roboto_bold','roboto','open_sans_bold','open_sans']
        : ['noto_sans_bold','noto_sans'];

      const layoutSel = document.getElementById('layout_key');
      Array.from(layoutSel.options).forEach(o => { o.disabled = !allowedLayouts.includes(o.value); });
      if (!allowedLayouts.includes(layoutSel.value)) layoutSel.value = allowedLayouts[0];

      const fontSel = document.getElementById('font_key');
      Array.from(fontSel.options).forEach(o => { o.disabled = !allowedFonts.includes(o.value); });
      if (!allowedFonts.includes(fontSel.value)) fontSel.value = allowedFonts[0];

      const createBtn = document.getElementById('btn-create');
      if (maxed && !editingId) {
        createBtn.disabled = true;
        createBtn.title = 'Plan limit reached. Upgrade to create more timers.';
      } else {
        createBtn.disabled = false;
        createBtn.title = '';
      }
    }

    async function loadBilling() {
      try {
        const r = await fetch(BILLING_API, { credentials: 'same-origin' });
        if (!r.ok) return;
        const j = await r.json();
        entitlements = j.entitlements || null;
        if (!entitlements) return;
        const title = document.getElementById('billing-title');
        const kpis = document.getElementById('billing-kpis');
        title.textContent = entitlements.plan_name || entitlements.plan_key || 'Unknown plan';
        kpis.innerHTML =
          '<span>Timers <strong>' + Number(entitlements.timer_count || 0) + ' / ' + Number(entitlements.max_timers || 0) + '</strong></span>' +
          '<span>Layouts <strong>' + (entitlements.allow_premium_layouts ? 'All' : 'Core') + '</strong></span>' +
          '<span>Fonts <strong>' + (entitlements.allow_premium_fonts ? 'All' : 'Core') + '</strong></span>';
        applyPlanGates();
      } catch (e) {}
    }

    async function loadAudit() {
      const el = document.getElementById('audit');
      try {
        const r = await fetch(AUDIT_API + '?limit=40', { credentials: 'same-origin' });
        if (!r.ok) {
          el.innerHTML = '<span class="empty">Audit unavailable (sign in again or upgrade database).</span>';
          return;
        }
        const j = await r.json();
        const rows = j.entries || [];
        if (!rows.length) {
          el.innerHTML = '<span class="empty">No activity yet.</span>';
          return;
        }
        el.innerHTML = rows.map(row => {
          const t = new Date((row.created_at || 0) * 1000);
          const ts = t.toISOString().replace('T', ' ').slice(0, 19) + 'Z';
          return '<div>' + escapeHtml(ts) + ' · ' + escapeHtml(row.action || '') + ' · ' + escapeHtml(row.entity_type || '') + ' ' + escapeHtml(String(row.entity_id || '').slice(0, 12)) + '…</div>';
        }).join('');
      } catch (e) {
        el.innerHTML = '<span class="empty">Could not load audit log.</span>';
      }
    }

    async function loadList() {
      const list = document.getElementById('list');
      try {
        const r = await fetch(API, { credentials: 'same-origin' });
        if (r.status === 503) {
          window.location.href = 'install.php';
          return;
        }
        if (r.status === 401) {
          window.location.href = 'login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
          return;
        }
        const j = await r.json();
        entitlements = j.entitlements || entitlements;
        applyPlanGates();
        if (!j.timers || !j.timers.length) {
          currentTimers = [];
          list.innerHTML = '<div class="empty-state"><strong>No timers yet</strong>Create one above, then copy the HTML into your ESP.</div>';
          return;
        }
        currentTimers = j.timers;
        list.innerHTML = '';
        for (const t of j.timers) {
          const card = document.createElement('div');
          card.className = 'timer-card';
          const ends = new Date(t.ends_at * 1000);
          const endsLabel = ends.toISOString().replace('T', ' ').slice(0, 19) + 'Z';
          const httpsAttrs = EMBED_HTTPS_OK ? '' : ' disabled title="Requires https public_base_url"';
          card.innerHTML =
            '<div class="timer-card-head">' +
            '<div><h3>' + escapeHtml(t.name) + '</h3>' +
            '<div class="meta"><span>Ends ' + endsLabel + '</span><span>' + Number(t.width) + '×' + Number(t.height) + '</span><span>' + escapeHtml(t.layout_key || 'segmented_pills') + '</span></div></div>' +
            '</div>' +
            '<div class="preview"></div>' +
            '<div class="actions-primary">' +
            '<button type="button" class="btn-copy" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '" data-height="' + Number(t.height) + '" data-ends="' + Number(t.ends_at) + '"' + httpsAttrs + '>Copy for Gmail</button>' +
            '<button type="button" class="secondary btn-edit" data-id="' + escapeHtml(t.id) + '">Edit</button>' +
            '</div>' +
            '<div class="actions-secondary">' +
            '<button type="button" class="secondary btn-copy-dynamic" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '" data-height="' + Number(t.height) + '" data-ends="' + Number(t.ends_at) + '" data-sig="' + escapeHtml(t.dynamic_sig || '') + '"' + httpsAttrs + '>Copy Dynamic HTML</button>' +
            '<button type="button" class="secondary btn-copy-png" data-id="' + escapeHtml(t.id) + '"' + httpsAttrs + '>Copy PNG URL</button>' +
            '<button type="button" class="secondary btn-toggle-embed" data-id="' + escapeHtml(t.id) + '">Show HTML</button>' +
            '<button type="button" class="danger btn-del" data-id="' + escapeHtml(t.id) + '">Delete</button>' +
            '</div>' +
            '<div class="embed" id="embed-' + escapeHtml(t.id) + '" tabindex="0">' + escapeHtml(embedHtml(t.id, t.width, t.height, t.ends_at)) + '</div>';
          const img = document.createElement('img');
          img.alt = 'Preview of ' + (t.name || 'timer');
          img.loading = 'lazy';
          img.src = TIMER_PREVIEW_PREFIX + encodeURIComponent(t.id) + '&_=' + Date.now();
          card.querySelector('.preview').appendChild(img);
          list.appendChild(card);
        }
        list.querySelectorAll('.btn-copy').forEach(btn => {
          btn.addEventListener('click', () => {
            if (!requireHttpsEmbed()) return;
            const id = btn.getAttribute('data-id');
            const width = parseInt(btn.getAttribute('data-width') || '480', 10);
            const height = parseInt(btn.getAttribute('data-height') || '120', 10);
            const ends = parseInt(btn.getAttribute('data-ends') || '0', 10);
            navigator.clipboard.writeText(embedHtml(id, width, height, ends)).then(() => toast('Copied — paste into your ESP'));
          });
        });
        list.querySelectorAll('.btn-copy-dynamic').forEach(btn => {
          btn.addEventListener('click', () => {
            if (!requireHttpsEmbed()) return;
            const id = btn.getAttribute('data-id');
            const width = parseInt(btn.getAttribute('data-width') || '480', 10);
            const height = parseInt(btn.getAttribute('data-height') || '120', 10);
            const ends = parseInt(btn.getAttribute('data-ends') || '0', 10);
            const sig = btn.getAttribute('data-sig') || '';
            navigator.clipboard.writeText(embedHtmlDynamic(id, width, height, ends, sig)).then(() => toast('Copied dynamic HTML'));
          });
        });
        list.querySelectorAll('.btn-copy-png').forEach(btn => {
          btn.addEventListener('click', () => {
            if (!requireHttpsEmbed()) return;
            const id = btn.getAttribute('data-id');
            navigator.clipboard.writeText(pngFallbackUrl(id)).then(() => toast('Copied PNG URL'));
          });
        });
        list.querySelectorAll('.btn-toggle-embed').forEach(btn => {
          btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const el = document.getElementById('embed-' + id);
            if (!el) return;
            const open = el.classList.toggle('show');
            btn.textContent = open ? 'Hide HTML' : 'Show HTML';
          });
        });
        list.querySelectorAll('.btn-del').forEach(btn => {
          btn.addEventListener('click', async () => {
            if (!confirm('Delete this timer?')) return;
            const id = btn.getAttribute('data-id');
            const dr = await fetch(API + '?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
            if (dr.status === 503) {
              window.location.href = 'install.php';
              return;
            }
            if (dr.status === 401) {
              window.location.href = 'login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
              return;
            }
            if (!dr.ok) {
              toast('Delete failed');
              return;
            }
            toast('Deleted');
            loadList();
            loadAudit();
            loadBilling();
          });
        });
        list.querySelectorAll('.btn-edit').forEach(btn => {
          btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const t = currentTimers.find(x => x.id === id);
            if (!t) return;
            startEdit(t);
          });
        });
      } catch (e) {
        list.innerHTML = '<p class="empty">Could not load timers.</p>';
      }
    }

    function escapeHtml(s) {
      return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    document.getElementById('create-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('name').value.trim();
      const ends = document.getElementById('ends').value;
      if (!name || !ends) return;
      const ends_at = toUnix(ends);
      const body = {
        id: editingId || undefined,
        name,
        ends_at,
        label: document.getElementById('label').value.trim(),
        bg_color: document.getElementById('bg').value,
        text_color: document.getElementById('fg').value,
        accent_color: document.getElementById('ac').value,
        width: parseInt(document.getElementById('width').value, 10) || 560,
        height: parseInt(document.getElementById('height').value, 10) || 140,
        font_key: document.getElementById('font_key').value,
        font_size_main: parseInt(document.getElementById('font_size_main').value, 10) || 32,
        layout_key: document.getElementById('layout_key').value,
      };
      const btn = document.getElementById('btn-create');
      btn.disabled = true;
      try {
        const method = editingId ? 'PUT' : 'POST';
        const r = await fetch(API, { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (r.status === 503) {
          window.location.href = 'install.php';
          return;
        }
        if (r.status === 401) {
          window.location.href = 'login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
          return;
        }
        const j = await r.json();
        if (!r.ok) throw new Error(j.error || 'Save failed');
        toast(editingId ? 'Timer updated' : 'Timer created — copy HTML below');
        resetCreateForm();
        loadList();
        loadAudit();
        loadBilling();
        document.getElementById('list').scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (err) {
        toast(err.message || 'Error');
      }
      btn.disabled = false;
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', () => {
      resetCreateForm();
    });
    document.getElementById('btn-upgrade').addEventListener('click', () => {
      toast('Upgrade flow placeholder: connect Stripe checkout to workspace_billing.');
    });

    resetCreateForm();
    loadBilling();
    loadList();
    loadAudit();
  </script>
</body>
</html>
