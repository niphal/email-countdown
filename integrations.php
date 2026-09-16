<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_admin_page_redirect();

$cu = auth_current_user();
$workspaceName = 'Workspace';
if ($cu !== null) {
    $stmt = db()->prepare('SELECT name FROM workspaces WHERE id = ?');
    $stmt->execute([(int) $cu['workspace_id']]);
    $name = $stmt->fetchColumn();
    if ($name !== false) {
        $workspaceName = (string) $name;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Integrations — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --ring:rgba(0,66,37,.18); }
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%);color:var(--text);font-family:var(--font-body)}
    .wrap{max-width:880px;margin:0 auto;padding:2.4rem 1.35rem 3rem}
    .top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
    h1{margin:.1rem 0 .25rem;font-family:var(--font-display);font-size:1.95rem;letter-spacing:-.02em}
    .pill{font-size:.82rem;color:var(--muted);font-family:var(--font-mono)}
    .menu{display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 1.15rem}
    .menu a{color:var(--text);text-decoration:none;border:1px solid var(--border);padding:.4rem .78rem;border-radius:999px;font-size:.82rem;font-weight:600}
    .menu a.active{border-color:var(--accent);color:var(--accent);background:#f5fbf7}
    .panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:1.15rem 1.3rem;margin-bottom:1rem;box-shadow:0 6px 18px rgba(17,24,39,.06)}
    .panel h2{margin:.1rem 0 .35rem;font-size:1.05rem}
    .lead{color:var(--muted);font-size:.88rem;margin:0 0 1rem;line-height:1.45}
    label{display:block;font-size:.78rem;color:var(--muted);font-weight:600;margin:0 0 .35rem}
    input,select,textarea{width:100%;padding:.55rem .6rem;border:1px solid var(--border);border-radius:8px;background:#ffffff;color:var(--text);font:inherit}
    input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--ring);outline:none}
    textarea{font-family:var(--font-mono);font-size:.78rem;line-height:1.45;min-height:110px}
    .grid{display:grid;gap:.85rem}
    @media (min-width:640px){.grid-2{grid-template-columns:1fr 1fr}}
    .row{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:.9rem}
    button{padding:.58rem .85rem;border:none;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent-dim));color:#ffffff;font-weight:600;cursor:pointer}
    button.secondary{background:#ffffff;color:var(--text);border:1px solid var(--border)}
    button.danger{background:#ffffff;color:#9b1c1c;border:1px solid #efcaca}
    .muted{color:var(--muted);font-size:.82rem}
    .status{display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:600;padding:.3rem .65rem;border-radius:999px;border:1px solid var(--border);background:#fbfcfb}
    .status.on{border-color:#b7d7c4;background:#f5fbf7;color:var(--accent)}
    .status.off{color:var(--muted)}
    .alert{margin:0 0 1rem;padding:.75rem .9rem;border-radius:10px;font-size:.88rem;background:rgba(185,28,28,.08);border:1px solid #efcaca}
    .ok{margin:0 0 1rem;padding:.75rem .9rem;border-radius:10px;font-size:.88rem;background:#f5fbf7;border:1px solid #b7d7c4}
    .token-box{font-family:var(--font-mono);font-size:.8rem;word-break:break-all;background:#f8faf9;border:1px dashed var(--border);border-radius:8px;padding:.75rem;margin:.5rem 0}
    table{width:100%;border-collapse:collapse;font-size:.86rem}
    th{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
    th,td{padding:.55rem .3rem;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
    code{font-size:.85em;background:#f0f4f1;padding:.08em .3em;border-radius:4px}
    .steps{margin:.4rem 0 0;padding-left:1.15rem;color:var(--muted);font-size:.86rem;line-height:1.55}
    @media (max-width:620px){.wrap{padding:1.2rem .9rem 2rem}h1{font-size:1.55rem}.row > *{width:100%}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1>Integrations</h1>
        <div class="pill">Workspace: <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <a href="logout.php" class="pill">Log out</a>
    </div>
    <div class="menu">
      <a href="index.php">Dashboard</a>
      <a href="admin.php">Admin</a>
      <a href="templates.php">Templates</a>
      <a href="integrations.php" class="active">Integrations</a>
    </div>

    <div class="panel">
      <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center">
        <h2 style="margin:0">Braze</h2>
        <span id="braze-status" class="status off">Not connected</span>
      </div>
      <p class="lead">Connect your Braze REST API so you can push countdown timers into Braze as <strong>Content Blocks</strong> (insert from the Braze editor) and use <strong>Connected Content</strong> for live HTML at send time.</p>

      <div id="https-warning" class="alert" style="display:none">Set <code>public_base_url</code> to an absolute <code>https://</code> origin in <code>data/secrets.php</code> before pushing image URLs to Braze / Gmail.</div>
      <div id="msg" style="display:none"></div>

      <div class="grid grid-2">
        <div>
          <label for="rest_endpoint">Braze REST endpoint</label>
          <select id="rest_endpoint"></select>
        </div>
        <div>
          <label for="api_key">REST API key</label>
          <input id="api_key" type="password" autocomplete="off" placeholder="Paste key (needs content_blocks.create + update + list)">
          <p class="muted" id="api-hint" style="margin:.35rem 0 0"></p>
        </div>
      </div>
      <div class="row">
        <button type="button" id="btn-save">Connect Braze</button>
        <button type="button" id="btn-test" class="secondary" style="display:none">Test connection</button>
        <button type="button" id="btn-rotate" class="secondary" style="display:none">Rotate Connected Content token</button>
        <button type="button" id="btn-disconnect" class="danger" style="display:none">Disconnect</button>
      </div>
      <p class="muted" style="margin-top:.85rem">Create the key in Braze → Settings → APIs and Identifiers. Permissions: <code>content_blocks.create</code>, <code>content_blocks.update</code>, <code>content_blocks.list</code>.</p>
    </div>

    <div class="panel" id="cc-panel" style="display:none">
      <h2>Connected Content</h2>
      <p class="lead">Braze calls this URL at send time and inserts the returned HTML. Keep the token secret.</p>
      <label>Endpoint</label>
      <div class="token-box" id="cc-url"></div>
      <label>Token hint</label>
      <p class="muted" id="cc-hint" style="margin:.2rem 0 .8rem"></p>
      <div id="cc-token-once" style="display:none">
        <div class="ok"><strong>New token (copy now):</strong><div class="token-box" id="cc-token-value"></div></div>
      </div>
      <label>Example Liquid</label>
      <textarea id="cc-example" readonly></textarea>
      <div class="row">
        <button type="button" class="secondary" id="btn-copy-cc">Copy example Liquid</button>
      </div>
    </div>

    <div class="panel">
      <h2>Use in Braze</h2>
      <ol class="steps">
        <li>Create a timer on the Dashboard.</li>
        <li>Click <strong>Push to Braze</strong> on the timer card (or use Connected Content Liquid).</li>
        <li>In Braze email editor, insert the Content Block, or paste the Connected Content snippet into an HTML block.</li>
        <li>Replace <code>https://example.com/cta</code> with your landing URL.</li>
      </ol>
    </div>

    <div class="panel">
      <h2>Pushed Content Blocks</h2>
      <p class="lead">Blocks created or updated in your Braze workspace from this app.</p>
      <div id="blocks"><p class="muted">Loading…</p></div>
    </div>
  </div>

  <script>
    const API = 'api/braze.php';
    const statusEl = document.getElementById('braze-status');
    const msgEl = document.getElementById('msg');
    const endpointSel = document.getElementById('rest_endpoint');
    const apiKeyInput = document.getElementById('api_key');

    function showMsg(text, ok) {
      if (!text) { msgEl.style.display = 'none'; return; }
      msgEl.style.display = 'block';
      msgEl.className = ok ? 'ok' : 'alert';
      msgEl.textContent = text;
    }

    async function parseJson(resp) {
      const txt = await resp.text();
      try { return JSON.parse(txt); } catch { return { error: txt.slice(0, 180) || 'Unexpected response' }; }
    }

    function render(status) {
      const connected = !!status.connected;
      statusEl.textContent = connected ? ('Connected · ' + (status.api_key_hint || '')) : 'Not connected';
      statusEl.className = 'status ' + (connected ? 'on' : 'off');
      document.getElementById('btn-test').style.display = connected ? '' : 'none';
      document.getElementById('btn-rotate').style.display = connected ? '' : 'none';
      document.getElementById('btn-disconnect').style.display = connected ? '' : 'none';
      document.getElementById('btn-save').textContent = connected ? 'Update connection' : 'Connect Braze';
      document.getElementById('https-warning').style.display = status.embed_https_ok ? 'none' : 'block';
      document.getElementById('api-hint').textContent = status.api_key_hint ? ('Saved key: ' + status.api_key_hint + ' — leave blank to keep it') : '';

      endpointSel.innerHTML = '';
      const endpoints = status.endpoints || {};
      Object.keys(endpoints).forEach(label => {
        const opt = document.createElement('option');
        opt.value = endpoints[label];
        opt.textContent = label;
        if (status.rest_endpoint && endpoints[label] === status.rest_endpoint) opt.selected = true;
        endpointSel.appendChild(opt);
      });
      if (status.rest_endpoint && !Array.from(endpointSel.options).some(o => o.value === status.rest_endpoint)) {
        const opt = document.createElement('option');
        opt.value = status.rest_endpoint;
        opt.textContent = status.rest_endpoint;
        opt.selected = true;
        endpointSel.appendChild(opt);
      }

      const ccPanel = document.getElementById('cc-panel');
      if (connected) {
        ccPanel.style.display = '';
        document.getElementById('cc-url').textContent = status.connected_content_url || '';
        document.getElementById('cc-hint').textContent = status.connected_content_token_hint || 'No token yet — rotate to create one.';
        const example = '{% connected_content ' + (status.connected_content_url || '') + '?timer_id=TIMER_ID :method get :headers {"Authorization": "Bearer YOUR_CONNECTED_CONTENT_TOKEN"} :save countdown :retry %}\n{{countdown.html}}';
        document.getElementById('cc-example').value = example;
      } else {
        ccPanel.style.display = 'none';
      }

      if (status.connected_content_token) {
        document.getElementById('cc-token-once').style.display = '';
        document.getElementById('cc-token-value').textContent = status.connected_content_token;
      }

      const blocks = status.blocks || [];
      const blocksEl = document.getElementById('blocks');
      if (!blocks.length) {
        blocksEl.innerHTML = '<p class="muted">No Content Blocks pushed yet. Use <strong>Push to Braze</strong> on the dashboard.</p>';
      } else {
        blocksEl.innerHTML = '<table><thead><tr><th>Timer</th><th>Block</th><th>Liquid</th><th>Pushed</th></tr></thead><tbody>' +
          blocks.map(b => {
            const when = b.last_pushed_at ? new Date(Number(b.last_pushed_at) * 1000).toISOString().replace('T', ' ').slice(0, 19) + 'Z' : '—';
            const err = b.last_error ? '<div class="muted" style="color:#9b1c1c">' + esc(b.last_error) + '</div>' : '';
            return '<tr><td>' + esc(b.timer_name || b.timer_id) + err + '</td><td><code>' + esc(b.block_name || '') + '</code></td><td><code>' + esc(b.liquid_tag || '') + '</code></td><td>' + esc(when) + '</td></tr>';
          }).join('') + '</tbody></table>';
      }
    }

    function esc(s) {
      return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    async function load() {
      const r = await fetch(API, { credentials: 'same-origin' });
      const j = await parseJson(r);
      if (!r.ok) { showMsg(j.error || 'Could not load Braze status', false); return; }
      render(j);
      if (j.last_error) showMsg(j.last_error, false);
    }

    document.getElementById('btn-save').addEventListener('click', async () => {
      showMsg('', true);
      const payload = {
        rest_endpoint: endpointSel.value,
        api_key: apiKeyInput.value.trim(),
        rotate_connected_content_token: false,
      };
      const r = await fetch(API, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const j = await parseJson(r);
      if (!r.ok) { showMsg(j.error || 'Could not save', false); return; }
      apiKeyInput.value = '';
      render(j);
      if (j.test_ok) showMsg('Connected. Braze API test succeeded.', true);
      else showMsg('Saved, but test failed: ' + (j.test_error || 'unknown'), false);
    });

    document.getElementById('btn-test').addEventListener('click', async () => {
      const r = await fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'test' }) });
      const j = await parseJson(r);
      render(j);
      showMsg(j.test_ok ? 'Connection OK' : (j.test_error || j.error || 'Test failed'), !!j.test_ok);
    });

    document.getElementById('btn-rotate').addEventListener('click', async () => {
      if (!confirm('Rotate Connected Content token? Update Braze Liquid headers afterward.')) return;
      const r = await fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'rotate_token' }) });
      const j = await parseJson(r);
      if (!r.ok) { showMsg(j.error || 'Rotate failed', false); return; }
      render(j);
      showMsg('Token rotated — copy it now.', true);
    });

    document.getElementById('btn-disconnect').addEventListener('click', async () => {
      if (!confirm('Disconnect Braze from this workspace?')) return;
      const r = await fetch(API, { method: 'DELETE', credentials: 'same-origin' });
      const j = await parseJson(r);
      if (!r.ok) { showMsg(j.error || 'Disconnect failed', false); return; }
      document.getElementById('cc-token-once').style.display = 'none';
      await load();
      showMsg('Disconnected', true);
    });

    document.getElementById('btn-copy-cc').addEventListener('click', () => {
      const t = document.getElementById('cc-example').value;
      navigator.clipboard.writeText(t).then(() => showMsg('Copied Connected Content Liquid', true));
    });

    load();
  </script>
</body>
</html>
