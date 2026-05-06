<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
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
      font-family: var(--font-body);
      background: radial-gradient(1200px 600px at 10% -10%, #1a1530 0%, transparent 50%), var(--bg);
      color: var(--text);
      line-height: 1.5;
    }
    .wrap { max-width: 920px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    h1 {
      font-family: var(--font-display);
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
    .panel h2 { font-family: var(--font-ui); font-size: 1rem; margin: 0 0 1rem; font-weight: 600; }
    label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 0.35rem; }
    input[type="text"], input[type="datetime-local"], input[type="number"], select {
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
      font-family: var(--font-ui);
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
    .timer-card h3 { font-family: var(--font-accent); margin: 0 0 0.25rem; font-size: 1rem; font-weight: 600; }
    .timer-card .meta { font-size: 0.8rem; color: var(--muted); font-family: var(--font-mono); }
    .embed {
      font-family: var(--font-mono);
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
    .toast { font-family: var(--font-ui); position: fixed; bottom: 1.25rem; right: 1.25rem; background: var(--surface); border: 1px solid var(--border); padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; display: none; }
    .toast.show { display: block; }
    .empty { color: var(--muted); font-size: 0.95rem; }
    .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.25rem; }
    .topbar h1 { margin-bottom: 0; }
    .ws-pill { font-size: 0.82rem; color: var(--muted); font-family: var(--font-mono); margin-top: 0.35rem; }
    .audit-lines { font-family: var(--font-mono); font-size: 0.75rem; color: var(--muted); line-height: 1.55; max-height: 220px; overflow-y: auto; }
    .audit-lines div { padding: 0.2rem 0; border-bottom: 1px solid var(--border); }
    a.logout { color: var(--muted); font-size: 0.9rem; text-decoration: none; padding: 0.35rem 0; }
    a.logout:hover { color: var(--text); text-decoration: underline; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h1>Email countdown timers</h1>
        <?php if ($workspaceBanner !== ''): ?><div class="ws-pill">Workspace: <?= htmlspecialchars($workspaceBanner, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <a class="logout" href="logout.php">Log out</a>
    </div>
    <p class="lede">Create timers that render as animated GIFs (about 20 one-second frames from load time)—safe for Braze and other ESPs (no JavaScript in email). Add <code>?format=png</code> to the image URL for a single static PNG instead. Paste the HTML into the Braze email editor.</p>
    <?php if ($embedNeedsPublicBase): ?>
    <p class="note" style="margin:-1rem 0 1.5rem;padding:0.75rem 1rem;background:rgba(248,113,113,0.12);border:1px solid #5c2a35;border-radius:8px;">Copied embed URLs are still <strong>root-relative</strong> (no host was available). Add <code>'public_base_url' =&gt; 'https://your-public-site'</code> to <code>data/secrets.php</code> (no trailing slash), or open the dashboard on your public <strong>https</strong> URL, then copy again.</p>
    <?php endif; ?>

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
        <div>
          <label for="font_key">Timer font (image)</label>
          <select id="font_key" name="font_key">
            <?php foreach (timer_font_labels() as $val => $lab): ?>
            <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="font_size_main">Main text size (px)</label>
          <input type="number" id="font_size_main" value="32" min="14" max="72" step="1">
        </div>
        <div>
          <label for="layout_key">Timer layout</label>
          <select id="layout_key" name="layout_key">
            <?php foreach (timer_layout_labels() as $val => $lab): ?>
            <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
      <p class="note" style="margin:0 0 1rem;">Image text uses open-licensed TrueType fonts from the official Google Fonts GitHub sources. Each file is downloaded once into <code>data/fonts/</code> on the server (PHP GD cannot use CSS webfonts).</p>
      <div class="row-actions">
        <button type="submit" form="create-form" id="btn-create">Create timer</button>
        <button type="button" id="btn-cancel-edit" class="secondary" style="display:none;">Cancel edit</button>
      </div>
    </div>

    <div class="panel">
      <h2>Your timers</h2>
      <div id="list"><p class="empty">Loading…</p></div>
    </div>

    <div class="panel">
      <h2>Audit log</h2>
      <p class="note" style="margin:0 0 0.75rem;">Recent changes in this workspace (owner / admin / editor).</p>
      <div id="audit" class="audit-lines"><span class="empty">Loading…</span></div>
    </div>

    <div class="panel">
      <h2>Braze notes</h2>
      <p class="note" style="margin:0;">
        Use <strong>Custom HTML</strong> or the HTML block and paste the <code>&lt;img&gt;</code> snippet. Copied HTML uses a <strong>full URL</strong> for <code>img src</code> (this site’s host, or <code>public_base_url</code> in <code>data/secrets.php</code>). Use <strong>HTTPS</strong> in production. The default URL serves an <strong>animated GIF</strong> that steps the countdown about once per second for up to 20 seconds after each load (client behavior varies). Each open can refresh the asset; caching is normal. Replace the <code>href=&quot;#&quot;</code> wrapper link with your real CTA URL.
        For a <strong>per-recipient</strong> end time, append <code>&amp;end=</code> with a Unix timestamp from Liquid, for example
        <code class="embed" style="margin-top:0.5rem;display:block;">&amp;end={{event_properties.end_ts}}</code>
        (your property must be an integer Unix time in seconds). The query override takes precedence over the saved end time.
        Use <strong>Copy Dynamic HTML</strong> to include the per-timer <code>sig</code> value for signed dynamic URLs.
      </p>
    </div>
  </div>
  <div id="toast" class="toast" role="status"></div>

  <script>
    const API = 'api/timers.php';
    const AUDIT_API = 'api/audit.php';
    /** Root-relative: dashboard preview only */
    const TIMER_PREVIEW_PREFIX = <?= json_encode($timerPreviewPrefix, JSON_THROW_ON_ERROR) ?>;
    /** Absolute https? URL for pasted email HTML (from request host or public_base_url in secrets) */
    const TIMER_EMBED_PREFIX = <?= json_encode($timerEmbedPrefix, JSON_THROW_ON_ERROR) ?>;
    let editingId = null;
    let currentTimers = [];

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
      document.getElementById('width').value = '560';
      document.getElementById('height').value = '140';
      document.getElementById('font_key').value = 'noto_sans_bold';
      document.getElementById('font_size_main').value = '32';
      document.getElementById('layout_key').value = 'segmented_pills';
      document.getElementById('btn-create').textContent = 'Create timer';
      document.getElementById('btn-cancel-edit').style.display = 'none';
      editingId = null;
    }

    function startEdit(t) {
      editingId = t.id;
      document.getElementById('name').value = t.name || '';
      document.getElementById('ends').value = toLocalDateTimeValue(Number(t.ends_at || 0));
      document.getElementById('label').value = t.label || '';
      document.getElementById('bg').value = t.bg_color || '#1a1a2e';
      document.getElementById('fg').value = t.text_color || '#eaeaea';
      document.getElementById('ac').value = t.accent_color || '#e94560';
      document.getElementById('width').value = String(Number(t.width || 560));
      document.getElementById('height').value = String(Number(t.height || 140));
      document.getElementById('font_key').value = t.font_key || 'noto_sans_bold';
      document.getElementById('font_size_main').value = String(Number(t.font_size_main || 32));
      document.getElementById('layout_key').value = t.layout_key || 'segmented_pills';
      document.getElementById('btn-create').textContent = 'Save changes';
      document.getElementById('btn-cancel-edit').style.display = 'inline-block';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function embedHtml(id, width) {
      const w = width || 560;
      const src = TIMER_EMBED_PREFIX + encodeURIComponent(id);
      return '<a href="#" style="text-decoration:none;">\n' +
        '  <img src="' + src + '" width="' + w + '" alt="Countdown" style="display:block;border:0;max-width:100%;height:auto;" />\n' +
        '</a>';
    }

    function embedHtmlDynamic(id, width, sig) {
      const w = width || 560;
      let src = TIMER_EMBED_PREFIX + encodeURIComponent(id) + '&end={{event_properties.end_ts}}';
      if (sig) {
        src += '&sig=' + encodeURIComponent(sig);
      }
      return '<a href="#" style="text-decoration:none;">\n' +
        '  <img src="' + src + '" width="' + w + '" alt="Countdown" style="display:block;border:0;max-width:100%;height:auto;" />\n' +
        '</a>';
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
        if (!j.timers || !j.timers.length) {
          currentTimers = [];
          list.innerHTML = '<p class="empty">No timers yet. Create one above.</p>';
          return;
        }
        currentTimers = j.timers;
        list.innerHTML = '';
        for (const t of j.timers) {
          const card = document.createElement('div');
          card.className = 'timer-card';
          const ends = new Date(t.ends_at * 1000);
          card.innerHTML =
            '<h3>' + escapeHtml(t.name) + '</h3>' +
            '<div class="meta">Ends (UTC): ' + ends.toISOString().replace('T', ' ').slice(0, 19) + 'Z · id ' + escapeHtml(t.id.slice(0, 8)) + '… · ' + escapeHtml(t.font_key || 'noto_sans_bold') + ' · ' + Number(t.font_size_main || 32) + 'px · ' + escapeHtml(t.layout_key || 'segmented_pills') + '</div>' +
            '<div class="preview"></div>' +
            '<div class="embed" tabindex="0">' + escapeHtml(embedHtml(t.id, t.width)) + '</div>' +
            '<div class="row-actions">' +
            '<button type="button" class="secondary btn-copy" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '">Copy HTML</button>' +
            '<button type="button" class="secondary btn-copy-dynamic" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '" data-sig="' + escapeHtml(t.dynamic_sig || '') + '">Copy Dynamic HTML</button>' +
            '<button type="button" class="secondary btn-edit" data-id="' + escapeHtml(t.id) + '">Edit</button>' +
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
        list.querySelectorAll('.btn-copy-dynamic').forEach(btn => {
          btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const width = parseInt(btn.getAttribute('data-width') || '560', 10);
            const sig = btn.getAttribute('data-sig') || '';
            navigator.clipboard.writeText(embedHtmlDynamic(id, width, sig)).then(() => toast('Copied dynamic HTML'));
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
        toast(editingId ? 'Timer updated' : 'Timer created');
        resetCreateForm();
        loadList();
        loadAudit();
      } catch (err) {
        toast(err.message || 'Error');
      }
      btn.disabled = false;
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', () => {
      resetCreateForm();
    });

    resetCreateForm();
    loadList();
    loadAudit();
  </script>
</body>
</html>
