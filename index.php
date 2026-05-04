<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
auth_start_session();
auth_require_login_redirect();
// Touch DB so fresh installs have data dir
db();

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$dir = dirname($script);
if ($dir === '/' || $dir === '\\' || $dir === '.') {
    $appPath = '';
} else {
    $appPath = rtrim($dir, '/');
}
$timerPath = ($appPath === '' ? '' : $appPath) . '/timer.php';
if ($timerPath === '' || $timerPath[0] !== '/') {
    $timerPath = '/' . ltrim($timerPath, '/');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
$origin = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$timerQuery = '?id=';
$timerPreviewPrefix = $timerPath . $timerQuery;
$timerEmbedPrefix = $origin . $timerPath . $timerQuery;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email countdown timers</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0f1117;
      --surface: #181c27;
      --border: #2a3142;
      --text: #e8eaef;
      --muted: #8b95a8;
      --accent: #f15bb5;
      --accent-dim: #c44a92;
      --ok: #7ae582;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: "DM Sans", system-ui, sans-serif;
      background: radial-gradient(1200px 600px at 10% -10%, #1a1530 0%, transparent 50%), var(--bg);
      color: var(--text);
      line-height: 1.5;
    }
    .wrap { max-width: 920px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    h1 {
      font-size: 1.75rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin: 0 0 0.5rem;
    }
    .lede { color: var(--muted); max-width: 52ch; margin-bottom: 2rem; }
    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 1.5rem;
    }
    .panel h2 { font-size: 1rem; margin: 0 0 1rem; font-weight: 600; }
    label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 0.35rem; }
    input[type="text"], input[type="datetime-local"], input[type="number"] {
      width: 100%;
      padding: 0.55rem 0.65rem;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--bg);
      color: var(--text);
      font-family: inherit;
      font-size: 0.95rem;
    }
    input[type="color"] {
      width: 100%;
      height: 40px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--bg);
      cursor: pointer;
    }
    .grid { display: grid; gap: 1rem; }
    @media (min-width: 640px) {
      .grid-2 { grid-template-columns: 1fr 1fr; }
      .grid-3 { grid-template-columns: repeat(3, 1fr); }
    }
    button {
      font-family: inherit;
      font-weight: 600;
      font-size: 0.9rem;
      padding: 0.65rem 1.1rem;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      background: linear-gradient(135deg, var(--accent), var(--accent-dim));
      color: #0f1117;
    }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    button.secondary {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--border);
    }
    button.danger { background: #3d1f28; color: #ffb3b8; border: 1px solid #5c2a35; }
    .row-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
    .timer-card {
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1rem;
      background: rgba(0,0,0,0.2);
    }
    .timer-card h3 { margin: 0 0 0.25rem; font-size: 1rem; }
    .timer-card .meta { font-size: 0.8rem; color: var(--muted); font-family: "JetBrains Mono", monospace; }
    .embed {
      font-family: "JetBrains Mono", monospace;
      font-size: 0.72rem;
      line-height: 1.4;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.75rem;
      overflow-x: auto;
      white-space: pre-wrap;
      word-break: break-all;
      margin-top: 0.75rem;
    }
    .preview { margin-top: 0.75rem; }
    .preview img { max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--border); }
    .note { font-size: 0.85rem; color: var(--muted); margin-top: 1rem; }
    .toast { position: fixed; bottom: 1.25rem; right: 1.25rem; background: var(--surface); border: 1px solid var(--border); padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; display: none; }
    .toast.show { display: block; }
    .empty { color: var(--muted); font-size: 0.95rem; }
    .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.25rem; }
    .topbar h1 { margin-bottom: 0; }
    a.logout { color: var(--muted); font-size: 0.9rem; text-decoration: none; padding: 0.35rem 0; }
    a.logout:hover { color: var(--text); text-decoration: underline; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1>Email countdown timers</h1>
      <a class="logout" href="logout.php">Log out</a>
    </div>
    <p class="lede">Create timers that render as animated GIFs (about 20 one-second frames from load time)—safe for Braze and other ESPs (no JavaScript in email). Add <code>?format=png</code> to the image URL for a single static PNG instead. Paste the HTML into the Braze email editor.</p>

    <div class="panel">
      <h2>New timer</h2>
      <form id="create-form" class="grid grid-2">
        <div>
          <label for="name">Internal name</label>
          <input type="text" id="name" name="name" required placeholder="Spring sale ends">
        </div>
        <div>
          <label for="ends">End date &amp; time (your browser timezone)</label>
          <input type="datetime-local" id="ends" name="ends" required>
        </div>
        <div>
          <label for="label">Line under countdown (optional)</label>
          <input type="text" id="label" name="label" placeholder="Use code SAVE20">
        </div>
        <div class="grid grid-3">
          <div><label for="bg">Background</label><input type="color" id="bg" value="#1a1a2e"></div>
          <div><label for="fg">Text</label><input type="color" id="fg" value="#eaeaea"></div>
          <div><label for="ac">Countdown</label><input type="color" id="ac" value="#e94560"></div>
        </div>
        <div>
          <label for="width">Width (px)</label>
          <input type="number" id="width" value="560" min="200" max="900" step="10">
        </div>
        <div>
          <label for="height">Height (px)</label>
          <input type="number" id="height" value="140" min="80" max="400" step="10">
        </div>
      </form>
      <div class="row-actions">
        <button type="submit" form="create-form" id="btn-create">Create timer</button>
      </div>
    </div>

    <div class="panel">
      <h2>Your timers</h2>
      <div id="list"><p class="empty">Loading…</p></div>
    </div>

    <div class="panel">
      <h2>Braze notes</h2>
      <p class="note" style="margin:0;">
        Use <strong>Custom HTML</strong> or the HTML block and paste the <code>&lt;img&gt;</code> snippet. The default URL serves an <strong>animated GIF</strong> that steps the countdown once per second for up to 20 seconds after each load (then may stop or behave per the client). Each open can refresh the asset; caching is normal.
        For a <strong>per-recipient</strong> end time, append <code>&amp;end=</code> with a Unix timestamp from Liquid, for example
        <code class="embed" style="margin-top:0.5rem;display:block;">&amp;end={{event_properties.end_ts}}</code>
        (your property must be an integer Unix time in seconds). The query override takes precedence over the saved end time.
      </p>
    </div>
  </div>
  <div id="toast" class="toast" role="status"></div>

  <script>
    const API = 'api/timers.php';
    /** Same origin; avoids mixed content if the dashboard is HTTPS */
    const TIMER_PREVIEW_PREFIX = <?= json_encode($timerPreviewPrefix, JSON_THROW_ON_ERROR) ?>;
    /** Absolute URL for pasted email HTML */
    const TIMER_EMBED_PREFIX = <?= json_encode($timerEmbedPrefix, JSON_THROW_ON_ERROR) ?>;

    function toast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t._h);
      t._h = setTimeout(() => t.classList.remove('show'), 2200);
    }

    function toUnix(s) {
      const d = new Date(s);
      return Math.floor(d.getTime() / 1000);
    }

    function embedHtml(id, width) {
      const w = width || 560;
      const src = TIMER_EMBED_PREFIX + encodeURIComponent(id);
      return '<a href="https://example.com" style="text-decoration:none;">\n' +
        '  <img src="' + src + '" width="' + w + '" alt="Countdown" style="display:block;border:0;max-width:100%;height:auto;" />\n' +
        '</a>';
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
        if (!j.timers || !j.timers.length) {
          list.innerHTML = '<p class="empty">No timers yet. Create one above.</p>';
          return;
        }
        list.innerHTML = '';
        for (const t of j.timers) {
          const card = document.createElement('div');
          card.className = 'timer-card';
          const ends = new Date(t.ends_at * 1000);
          card.innerHTML =
            '<h3>' + escapeHtml(t.name) + '</h3>' +
            '<div class="meta">Ends (UTC): ' + ends.toISOString().replace('T', ' ').slice(0, 19) + 'Z · id ' + escapeHtml(t.id.slice(0, 8)) + '…</div>' +
            '<div class="preview"></div>' +
            '<div class="embed" tabindex="0">' + escapeHtml(embedHtml(t.id, t.width)) + '</div>' +
            '<div class="row-actions">' +
            '<button type="button" class="secondary btn-copy" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '">Copy HTML</button>' +
            '<button type="button" class="danger btn-del" data-id="' + escapeHtml(t.id) + '">Delete</button></div>';
          const img = document.createElement('img');
          img.alt = 'Preview';
          img.loading = 'lazy';
          img.src = TIMER_PREVIEW_PREFIX + encodeURIComponent(t.id) + '&_=' + Date.now();
          card.querySelector('.preview').appendChild(img);
          list.appendChild(card);
        }
        list.querySelectorAll('.btn-copy').forEach(btn => {
          btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const width = parseInt(btn.getAttribute('data-width') || '560', 10);
            navigator.clipboard.writeText(embedHtml(id, width)).then(() => toast('Copied HTML'));
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
        name,
        ends_at,
        label: document.getElementById('label').value.trim(),
        bg_color: document.getElementById('bg').value,
        text_color: document.getElementById('fg').value,
        accent_color: document.getElementById('ac').value,
        width: parseInt(document.getElementById('width').value, 10) || 560,
        height: parseInt(document.getElementById('height').value, 10) || 140,
      };
      const btn = document.getElementById('btn-create');
      btn.disabled = true;
      try {
        const r = await fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
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
        toast('Timer created');
        document.getElementById('create-form').reset();
        document.getElementById('bg').value = '#1a1a2e';
        document.getElementById('fg').value = '#eaeaea';
        document.getElementById('ac').value = '#e94560';
        document.getElementById('width').value = '560';
        document.getElementById('height').value = '140';
        loadList();
      } catch (err) {
        toast(err.message || 'Error');
      }
      btn.disabled = false;
    });

    loadList();
  </script>
</body>
</html>
