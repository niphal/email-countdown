<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_admin_page_redirect();

$appNav = 'integrations';
$appTitle = 'Integrations';
$appSubtitle = 'Connect Braze to push Content Blocks and use Connected Content.';
require __DIR__ . '/include/app_shell_start.php';
?>
    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-5">
        <div class="flex justify-between gap-4 flex-wrap items-center">
          <div>
            <h2 class="card-title text-lg">Braze</h2>
            <p class="text-sm text-base-content/60 mt-1 max-w-2xl">Connect your Braze REST API so you can push countdown timers as <strong>Content Blocks</strong> and use <strong>Connected Content</strong> for live HTML at send time.</p>
          </div>
          <span id="braze-status" class="badge badge-ghost badge-lg border border-base-300">Not connected</span>
        </div>

        <div id="https-warning" role="alert" class="alert alert-warning shadow-sm" style="display:none">
          <span>Set <code class="bg-base-100 px-1 rounded">public_base_url</code> to an absolute <code class="bg-base-100 px-1 rounded">https://</code> origin in <code class="bg-base-100 px-1 rounded">data/secrets.php</code> before pushing image URLs to Braze / Gmail.</span>
        </div>
        <div id="msg" style="display:none" role="status"></div>

        <div class="grid gap-4 sm:grid-cols-2">
          <fieldset class="fieldset p-0">
            <label class="label" for="rest_endpoint"><span class="label-text font-semibold">Braze REST endpoint</span></label>
            <select id="rest_endpoint" class="select select-bordered w-full"></select>
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="api_key"><span class="label-text font-semibold">REST API key</span></label>
            <input id="api_key" type="password" autocomplete="off" placeholder="Paste key (needs content_blocks.create + update + list)" class="input input-bordered w-full">
            <p class="label"><span class="label-text-alt" id="api-hint"></span></p>
          </fieldset>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" id="btn-save" class="btn btn-primary">Connect Braze</button>
          <button type="button" id="btn-test" class="btn btn-outline" style="display:none">Test connection</button>
          <button type="button" id="btn-rotate" class="btn btn-ghost" style="display:none">Rotate Connected Content token</button>
          <button type="button" id="btn-disconnect" class="btn btn-error btn-outline" style="display:none">Disconnect</button>
        </div>

        <p class="text-sm text-base-content/60 leading-relaxed m-0">Create the key in Braze → Settings → APIs and Identifiers. Permissions: <code class="bg-base-200 px-1 rounded">content_blocks.create</code>, <code class="bg-base-200 px-1 rounded">content_blocks.update</code>, <code class="bg-base-200 px-1 rounded">content_blocks.list</code>.</p>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300" id="cc-panel" style="display:none">
      <div class="card-body gap-4">
        <div>
          <h2 class="card-title text-lg">Connected Content</h2>
          <p class="text-sm text-base-content/60 mt-1">Braze calls this URL at send time and inserts the returned HTML. Keep the token secret.</p>
        </div>
        <fieldset class="fieldset p-0">
          <label class="label"><span class="label-text font-semibold">Endpoint</span></label>
          <div class="token-box" id="cc-url"></div>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label"><span class="label-text font-semibold">Token hint</span></label>
          <p class="text-sm text-base-content/60 m-0" id="cc-hint"></p>
        </fieldset>
        <div id="cc-token-once" style="display:none">
          <div role="alert" class="alert alert-success shadow-sm">
            <div>
              <strong>New token (copy now):</strong>
              <div class="token-box mt-2 mb-0" id="cc-token-value"></div>
            </div>
          </div>
        </div>
        <fieldset class="fieldset p-0">
          <label class="label" for="cc-example"><span class="label-text font-semibold">Example Liquid</span></label>
          <textarea id="cc-example" readonly class="textarea textarea-bordered w-full font-mono text-xs min-h-28"></textarea>
        </fieldset>
        <div>
          <button type="button" class="btn btn-outline btn-sm" id="btn-copy-cc">Copy example Liquid</button>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-3">
        <h2 class="card-title text-lg">Use in Braze</h2>
        <ol class="list-decimal pl-5 text-sm text-base-content/70 leading-relaxed space-y-2 m-0">
          <li>Create a timer on the Dashboard.</li>
          <li>Click <strong>Push to Braze</strong> on the timer card (or use Connected Content Liquid).</li>
          <li>In Braze email editor, insert the Content Block, or paste the Connected Content snippet into an HTML block.</li>
          <li>Replace <code class="bg-base-200 px-1 rounded">https://example.com/cta</code> with your landing URL.</li>
        </ol>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-4">
        <div>
          <h2 class="card-title text-lg">Pushed Content Blocks</h2>
          <p class="text-sm text-base-content/60 mt-1">Blocks created or updated in your Braze workspace from this app.</p>
        </div>
        <div id="blocks"><p class="text-sm text-base-content/50">Loading…</p></div>
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
      msgEl.className = 'alert shadow-sm ' + (ok ? 'alert-success' : 'alert-error');
      msgEl.textContent = text;
    }

    async function parseJson(resp) {
      const txt = await resp.text();
      try { return JSON.parse(txt); } catch { return { error: txt.slice(0, 180) || 'Unexpected response' }; }
    }

    function render(status) {
      const connected = !!status.connected;
      statusEl.textContent = connected ? ('Connected · ' + (status.api_key_hint || '')) : 'Not connected';
      statusEl.className = 'badge badge-lg border ' + (connected ? 'badge-success' : 'badge-ghost border-base-300');
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
        blocksEl.innerHTML = '<p class="text-sm text-base-content/50">No Content Blocks pushed yet. Use <strong>Push to Braze</strong> on the dashboard.</p>';
      } else {
        blocksEl.innerHTML = '<div class="overflow-x-auto rounded-box border border-base-300"><table class="table table-sm"><thead><tr><th>Timer</th><th>Block</th><th>Liquid</th><th>Pushed</th></tr></thead><tbody>' +
          blocks.map(b => {
            const when = b.last_pushed_at ? new Date(Number(b.last_pushed_at) * 1000).toISOString().replace('T', ' ').slice(0, 19) + 'Z' : '—';
            const err = b.last_error ? '<div class="text-error text-xs mt-1">' + esc(b.last_error) + '</div>' : '';
            return '<tr><td>' + esc(b.timer_name || b.timer_id) + err + '</td><td><code>' + esc(b.block_name || '') + '</code></td><td><code class="text-xs">' + esc(b.liquid_tag || '') + '</code></td><td>' + esc(when) + '</td></tr>';
          }).join('') + '</tbody></table></div>';
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

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
