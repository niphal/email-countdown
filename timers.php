<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/auth.php';
auth_start_session();
auth_require_login_redirect();
db();

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

$appNav = 'timers';
$appTitle = 'Timers';
$appSubtitle = 'Select a timer to preview, edit, or copy embed HTML.';
$appContentWide = true;
$appTopActions = '<a class="btn btn-outline btn-sm" href="gallery.php">Gallery</a>'
    . '<button type="button" class="btn btn-primary btn-sm" id="btn-new-top">+ New timer</button>';
require __DIR__ . '/include/app_shell_start.php';
?>
    <?php if ($embedBlockedReason === 'root-relative'): ?>
    <div role="alert" class="alert alert-error shadow-sm">
      <span><strong>Gmail will not load these images yet.</strong> Add <code class="bg-base-100 px-1 rounded">public_base_url</code> as an absolute HTTPS origin in secrets.</span>
    </div>
    <?php elseif ($embedBlockedReason === 'not-https'): ?>
    <div role="alert" class="alert alert-error shadow-sm">
      <span><strong>Gmail requires HTTPS image URLs.</strong> Set <code class="bg-base-100 px-1 rounded">public_base_url</code> to <code class="bg-base-100 px-1 rounded">https://…</code>.</span>
    </div>
    <?php endif; ?>

    <div class="timers-layout">
      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-3 py-5">
          <div class="flex items-center justify-between gap-2">
            <h2 class="card-title text-lg">Your timers</h2>
            <button type="button" id="btn-new" class="btn btn-primary btn-sm">+ New</button>
          </div>
          <input id="timer-search" type="search" class="input input-bordered input-sm w-full" placeholder="Filter by name…" autocomplete="off">
          <div id="timer-list" class="timer-list"><p class="text-sm text-base-content/50 px-1 py-3">Loading…</p></div>
        </div>
      </div>

      <div class="space-y-4">
        <div id="detail-empty" class="card bg-base-100 shadow-sm border border-base-300">
          <div class="card-body items-center text-center py-16 gap-3">
            <h2 class="card-title text-lg">Select a timer</h2>
            <p class="text-sm text-base-content/60 max-w-md">Pick one from the list to preview and manage, or create a new countdown.</p>
            <button type="button" class="btn btn-primary" id="btn-new-empty">Create timer</button>
          </div>
        </div>

        <div id="detail-panel" class="space-y-4" hidden>
          <div class="card bg-base-100 shadow-sm border border-base-300">
            <div class="card-body gap-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 class="card-title text-lg" id="detail-title">Timer</h2>
                  <p class="text-sm text-base-content/60 mt-1" id="detail-meta"></p>
                </div>
                <div class="flex flex-wrap gap-2" id="detail-actions-top"></div>
              </div>
              <div class="preview-stage">
                <img id="detail-preview" alt="Timer preview" style="display:none">
                <p id="detail-preview-empty" class="preview-empty-msg">Save to see a live preview</p>
              </div>
              <div class="actions-primary" id="detail-actions"></div>
              <div class="actions-secondary" id="detail-actions-more"></div>
              <div class="embed" id="detail-embed" tabindex="0"></div>
            </div>
          </div>

          <div class="card bg-base-100 shadow-sm border border-base-300">
            <div class="card-body gap-5">
              <div>
                <h2 class="card-title text-lg" id="editor-heading">Edit timer</h2>
                <p class="text-sm text-base-content/60 mt-1">Update the end time, copy, or appearance, then save.</p>
              </div>
              <form id="create-form" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                  <fieldset class="fieldset p-0">
                    <label class="label" for="template_id"><span class="label-text font-semibold">Brand template</span></label>
                    <select id="template_id" name="template_id" class="select select-bordered w-full">
                      <option value="">Custom appearance</option>
                    </select>
                    <p class="label"><span class="label-text-alt"><a class="link link-primary" href="gallery.php">Browse gallery</a> · <a class="link link-primary" href="templates.php">My templates</a></span></p>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="name"><span class="label-text font-semibold">Internal name</span></label>
                    <input type="text" id="name" name="name" required placeholder="Spring sale ends" autocomplete="off" class="input input-bordered w-full">
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="ends"><span class="label-text font-semibold">Ends at (your local time)</span></label>
                    <input type="datetime-local" id="ends" name="ends" required class="input input-bordered w-full">
                    <p class="label"><span class="label-text-alt">Stored and shown in email as UTC</span></p>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="label"><span class="label-text font-semibold">Optional line under countdown</span></label>
                    <input type="text" id="label" name="label" placeholder="Use code SAVE20" autocomplete="off" class="input input-bordered w-full">
                  </fieldset>
                </div>

                <div class="collapse collapse-arrow bg-base-200/60 border border-base-300 rounded-box" id="appearance-wrap">
                  <input type="checkbox" id="appearance-toggle" />
                  <div class="collapse-title font-semibold text-sm">Appearance</div>
                  <div class="collapse-content">
                    <div id="appearance" class="grid gap-4 sm:grid-cols-2 pt-1">
                      <div class="grid grid-cols-3 gap-3 sm:col-span-2">
                        <fieldset class="fieldset p-0">
                          <label class="label" for="bg"><span class="label-text">Background</span></label>
                          <input type="color" id="bg" value="#1a1a2e" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1">
                        </fieldset>
                        <fieldset class="fieldset p-0">
                          <label class="label" for="fg"><span class="label-text">Text</span></label>
                          <input type="color" id="fg" value="#eaeaea" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1">
                        </fieldset>
                        <fieldset class="fieldset p-0">
                          <label class="label" for="ac"><span class="label-text">Countdown</span></label>
                          <input type="color" id="ac" value="#e94560" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1">
                        </fieldset>
                      </div>
                      <fieldset class="fieldset p-0">
                        <label class="label" for="width"><span class="label-text font-semibold">Width (px)</span></label>
                        <input type="number" id="width" value="480" min="200" max="600" step="10" class="input input-bordered w-full">
                      </fieldset>
                      <fieldset class="fieldset p-0">
                        <label class="label" for="height"><span class="label-text font-semibold">Height (px)</span></label>
                        <input type="number" id="height" value="120" min="80" max="300" step="10" class="input input-bordered w-full">
                      </fieldset>
                      <fieldset class="fieldset p-0">
                        <label class="label" for="layout_key"><span class="label-text font-semibold">Layout</span></label>
                        <select id="layout_key" name="layout_key" class="select select-bordered w-full">
                          <?php foreach (timer_layout_labels() as $val => $lab): ?>
                          <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                          <?php endforeach; ?>
                        </select>
                      </fieldset>
                      <fieldset class="fieldset p-0">
                        <label class="label" for="font_key"><span class="label-text font-semibold">Font</span></label>
                        <select id="font_key" name="font_key" class="select select-bordered w-full">
                          <?php foreach (timer_font_labels() as $val => $lab): ?>
                          <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                          <?php endforeach; ?>
                        </select>
                      </fieldset>
                      <fieldset class="fieldset p-0">
                        <label class="label" for="font_size_main"><span class="label-text font-semibold">Main size (px)</span></label>
                        <input type="number" id="font_size_main" value="32" min="14" max="72" step="1" class="input input-bordered w-full">
                      </fieldset>
                    </div>
                  </div>
                </div>
              </form>
              <div class="flex flex-wrap gap-2">
                <button type="submit" form="create-form" id="btn-create" class="btn btn-primary">Save timer</button>
                <button type="button" id="btn-cancel-edit" class="btn btn-ghost">Cancel</button>
                <button type="button" id="btn-delete" class="btn btn-error btn-outline" disabled>Delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  <div id="toast" class="toast-app" role="status"></div>

  <script>
    const API = 'api/timers.php';
    const BRAZE_API = 'api/braze.php';
    const TEMPLATES_API = 'api/templates.php';
    const TIMER_PREVIEW_PREFIX = <?= json_encode($timerPreviewPrefix, JSON_THROW_ON_ERROR) ?>;
    const TIMER_EMBED_PREFIX = <?= json_encode($timerEmbedPrefix, JSON_THROW_ON_ERROR) ?>;
    const EMBED_HTTPS_OK = <?= $embedIsHttpsAbsolute ? 'true' : 'false' ?>;
    const INITIAL_ID = <?= json_encode(isset($_GET['id']) ? (string) $_GET['id'] : '', JSON_THROW_ON_ERROR) ?>;
    const START_NEW = <?= isset($_GET['new']) ? 'true' : 'false' ?>;

    let editingId = null;
    let selectedId = null;
    let creating = false;
    let currentTimers = [];
    let brazeConnected = false;
    let templateCatalog = [];

    function toast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t._h);
      t._h = setTimeout(() => t.classList.remove('show'), 2200);
    }
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function setAppearanceOpen(open) {
      const toggle = document.getElementById('appearance-toggle');
      if (toggle) toggle.checked = !!open;
    }
    function requireHttpsEmbed() {
      if (EMBED_HTTPS_OK) return true;
      toast('Set public_base_url to https://... before copying for Gmail');
      return false;
    }
    function deadlineAlt(endsAt) {
      const d = new Date(Number(endsAt || 0) * 1000);
      if (Number.isNaN(d.getTime())) return 'Countdown timer';
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return 'Countdown — Ends ' + d.getUTCDate() + ' ' + months[d.getUTCMonth()] + ' ' + d.getUTCFullYear() + ' ' +
        String(d.getUTCHours()).padStart(2, '0') + ':' + String(d.getUTCMinutes()).padStart(2, '0') + ' UTC';
    }
    function timerSrc(id, opts) {
      opts = opts || {};
      let src = TIMER_EMBED_PREFIX + encodeURIComponent(id);
      if (opts.format === 'png') src += (src.includes('?') ? '&' : '?') + 'format=png';
      if (opts.v) src += '&v=' + encodeURIComponent(String(opts.v));
      if (opts.endLiquid) {
        src += '&end={{event_properties.end_ts}}';
        if (opts.sig) src += '&sig=' + encodeURIComponent(opts.sig);
      }
      return src;
    }
    function embedHtml(id, width, height, endsAt) {
      const w = width || 480, h = height || 120, src = timerSrc(id), alt = deadlineAlt(endsAt);
      return '<!-- Email countdown: replace CTA href. -->\n<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="' + w + '" style="border-collapse:collapse;max-width:100%;">\n  <tr>\n    <td align="center" style="padding:0;">\n      <a href="https://example.com/cta" target="_blank" style="text-decoration:none;border:0;">\n        <img src="' + src + '" width="' + w + '" height="' + h + '" alt="' + alt.replace(/"/g, '&quot;') + '" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;" />\n      </a>\n    </td>\n  </tr>\n</table>';
    }
    function embedHtmlDynamic(id, width, height, endsAt, sig) {
      const w = width || 480, h = height || 120, src = timerSrc(id, { endLiquid: true, sig: sig || '' }), alt = deadlineAlt(endsAt);
      return '<!-- Dynamic countdown: requires signed end override. -->\n<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="' + w + '" style="border-collapse:collapse;max-width:100%;">\n  <tr>\n    <td align="center" style="padding:0;">\n      <a href="https://example.com/cta" target="_blank" style="text-decoration:none;border:0;">\n        <img src="' + src + '" width="' + w + '" height="' + h + '" alt="' + alt.replace(/"/g, '&quot;') + '" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;" />\n      </a>\n    </td>\n  </tr>\n</table>';
    }
    function pngFallbackUrl(id) { return timerSrc(id, { format: 'png' }); }
    function toUnix(s) { return Math.floor(new Date(s).getTime() / 1000); }
    function toLocalDateTimeValue(unixTs) {
      const d = new Date(unixTs * 1000);
      const pad = n => String(n).padStart(2, '0');
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function applyTemplateToForm(templateId) {
      const t = templateCatalog.find(x => x.id === templateId);
      if (!t) return;
      document.getElementById('bg').value = t.bg_color || '#1a1a2e';
      document.getElementById('fg').value = t.text_color || '#eaeaea';
      document.getElementById('ac').value = t.accent_color || '#e94560';
      document.getElementById('width').value = String(Number(t.width || 480));
      document.getElementById('height').value = String(Number(t.height || 120));
      document.getElementById('font_key').value = t.font_key || 'noto_sans_bold';
      document.getElementById('font_size_main').value = String(Number(t.font_size_main || 32));
      document.getElementById('layout_key').value = t.layout_key || 'segmented_pills';
      if (!document.getElementById('label').value.trim() && t.default_label) {
        document.getElementById('label').value = t.default_label;
      }
      setAppearanceOpen(true);
    }

    async function loadTemplatesForForm() {
      try {
        const r = await fetch(TEMPLATES_API, { credentials: 'same-origin' });
        if (!r.ok) return;
        const j = await r.json();
        templateCatalog = j.templates || [];
        const sel = document.getElementById('template_id');
        const current = sel.value;
        sel.innerHTML = '<option value="">Custom appearance</option>';
        templateCatalog.forEach(t => {
          const opt = document.createElement('option');
          opt.value = t.id;
          opt.textContent = t.name + (Number(t.is_default) ? ' (default)' : '');
          if (t.id === current) opt.selected = true;
          sel.appendChild(opt);
        });
        const def = templateCatalog.find(t => Number(t.is_default) === 1);
        if (!current && def && creating) {
          sel.value = def.id;
          applyTemplateToForm(def.id);
        }
      } catch (e) {}
    }

    function showEmpty() {
      document.getElementById('detail-empty').hidden = false;
      document.getElementById('detail-panel').hidden = true;
      selectedId = null;
      creating = false;
      editingId = null;
      renderList();
    }

    function showDetail() {
      document.getElementById('detail-empty').hidden = true;
      document.getElementById('detail-panel').hidden = false;
    }

    function resetCreateForm(applyDefaultTemplate) {
      document.getElementById('create-form').reset();
      document.getElementById('bg').value = '#1a1a2e';
      document.getElementById('fg').value = '#eaeaea';
      document.getElementById('ac').value = '#e94560';
      document.getElementById('width').value = '480';
      document.getElementById('height').value = '120';
      document.getElementById('font_key').value = 'noto_sans_bold';
      document.getElementById('font_size_main').value = '32';
      document.getElementById('layout_key').value = 'segmented_pills';
      document.getElementById('template_id').value = '';
      setAppearanceOpen(false);
      editingId = null;
      if (applyDefaultTemplate) {
        const def = templateCatalog.find(t => Number(t.is_default) === 1);
        if (def) {
          document.getElementById('template_id').value = def.id;
          applyTemplateToForm(def.id);
        }
      }
    }

    function startCreate() {
      creating = true;
      selectedId = null;
      editingId = null;
      showDetail();
      resetCreateForm(true);
      document.getElementById('editor-heading').textContent = 'New timer';
      document.getElementById('btn-create').textContent = 'Create timer';
      document.getElementById('btn-delete').disabled = true;
      document.getElementById('detail-title').textContent = 'New timer';
      document.getElementById('detail-meta').textContent = 'Fill in the form, then create to get embed HTML.';
      document.getElementById('detail-preview').style.display = 'none';
      document.getElementById('detail-preview-empty').style.display = 'block';
      document.getElementById('detail-actions').innerHTML = '';
      document.getElementById('detail-actions-more').innerHTML = '';
      document.getElementById('detail-actions-top').innerHTML = '';
      document.getElementById('detail-embed').classList.remove('show');
      document.getElementById('detail-embed').textContent = '';
      renderList();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fillFormFromTimer(t) {
      editingId = t.id;
      creating = false;
      selectedId = t.id;
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
      document.getElementById('template_id').value = t.template_id || '';
      document.getElementById('editor-heading').textContent = 'Edit timer';
      document.getElementById('btn-create').textContent = 'Save changes';
      document.getElementById('btn-delete').disabled = false;
      setAppearanceOpen(false);
    }

    function bindDetailActions(t) {
      const httpsAttrs = EMBED_HTTPS_OK ? '' : ' disabled title="Requires https public_base_url"';
      const endsLabel = new Date(t.ends_at * 1000).toISOString().replace('T', ' ').slice(0, 19) + 'Z';
      document.getElementById('detail-title').textContent = t.name || 'Timer';
      document.getElementById('detail-meta').textContent = 'Ends ' + endsLabel + ' · ' + Number(t.width) + '\u00d7' + Number(t.height) + ' · ' + (t.layout_key || 'segmented_pills');
      document.getElementById('detail-actions-top').innerHTML =
        '<button type="button" class="btn btn-primary btn-sm" id="act-copy"' + httpsAttrs + '>Copy for Gmail</button>';
      document.getElementById('detail-actions').innerHTML =
        '<button type="button" class="btn btn-outline btn-sm" id="act-copy-dyn"' + httpsAttrs + '>Copy Dynamic HTML</button>' +
        '<button type="button" class="btn btn-outline btn-sm" id="act-copy-png"' + httpsAttrs + '>Copy PNG countdown</button>' +
        '<button type="button" class="btn btn-outline btn-sm" id="act-toggle">Show HTML</button>';
      document.getElementById('detail-actions-more').innerHTML =
        '<button type="button" class="btn btn-ghost btn-sm" id="act-braze-push"' + (brazeConnected && EMBED_HTTPS_OK ? '' : ' disabled title="' + (brazeConnected ? 'Requires https public_base_url' : 'Connect Braze in Integrations') + '"') + '>Push to Braze</button>' +
        '<button type="button" class="btn btn-ghost btn-sm" id="act-braze-cc"' + (brazeConnected ? '' : ' disabled title="Connect Braze in Integrations"') + '>Copy Braze Liquid</button>';
      const embed = document.getElementById('detail-embed');
      embed.classList.remove('show');
      embed.textContent = embedHtml(t.id, t.width, t.height, t.ends_at);
      const img = document.getElementById('detail-preview');
      img.src = TIMER_PREVIEW_PREFIX + encodeURIComponent(t.id) + '&_=' + Date.now();
      img.style.display = 'block';
      document.getElementById('detail-preview-empty').style.display = 'none';

      document.getElementById('act-copy').onclick = () => {
        if (!requireHttpsEmbed()) return;
        navigator.clipboard.writeText(embedHtml(t.id, t.width, t.height, t.ends_at)).then(() => toast('Copied — paste into your ESP'));
      };
      document.getElementById('act-copy-dyn').onclick = () => {
        if (!requireHttpsEmbed()) return;
        navigator.clipboard.writeText(embedHtmlDynamic(t.id, t.width, t.height, t.ends_at, t.dynamic_sig || '')).then(() => toast('Copied dynamic HTML'));
      };
      document.getElementById('act-copy-png').onclick = () => {
        if (!requireHttpsEmbed()) return;
        navigator.clipboard.writeText(pngFallbackUrl(t.id)).then(() => toast('Copied animated PNG URL'));
      };
      document.getElementById('act-toggle').onclick = (e) => {
        const open = embed.classList.toggle('show');
        e.target.textContent = open ? 'Hide HTML' : 'Show HTML';
      };
      document.getElementById('act-braze-push').onclick = async () => {
        const btn = document.getElementById('act-braze-push');
        btn.disabled = true;
        try {
          const r = await fetch(BRAZE_API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'push_content_block', timer_id: t.id }) });
          const j = await r.json();
          if (!r.ok || !j.ok) { toast(j.error || 'Braze push failed'); return; }
          toast('Pushed to Braze — ' + (j.liquid_tag || j.block_name || 'content block'));
          if (j.liquid_tag) navigator.clipboard.writeText(j.liquid_tag).catch(() => {});
        } catch (e) { toast('Braze push failed'); }
        finally { btn.disabled = !brazeConnected || !EMBED_HTTPS_OK; }
      };
      document.getElementById('act-braze-cc').onclick = async () => {
        try {
          const r = await fetch(BRAZE_API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'snippets', timer_id: t.id }) });
          const j = await r.json();
          if (!r.ok) { toast(j.error || 'Could not load Braze snippet'); return; }
          const text = j.content_block_liquid || j.connected_content_liquid || '';
          if (!text) { toast('No Braze snippet available'); return; }
          await navigator.clipboard.writeText(text);
          toast(j.content_block_liquid ? 'Copied Content Block Liquid' : 'Copied Connected Content Liquid');
        } catch (e) { toast('Could not copy Braze Liquid'); }
      };
    }

    function selectTimer(id) {
      const t = currentTimers.find(x => x.id === id);
      if (!t) return;
      creating = false;
      selectedId = id;
      showDetail();
      fillFormFromTimer(t);
      bindDetailActions(t);
      renderList();
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('id', id);
        url.searchParams.delete('new');
        history.replaceState({}, '', url);
      } catch (e) {}
    }

    function renderList() {
      const el = document.getElementById('timer-list');
      const q = document.getElementById('timer-search').value.trim().toLowerCase();
      const rows = currentTimers.filter(t => !q || String(t.name || '').toLowerCase().includes(q));
      if (!currentTimers.length) {
        el.innerHTML = '<div class="empty-panel"><p class="empty-title">No timers yet</p><p class="empty-sub">Create one to get Gmail-safe HTML.</p></div>';
        return;
      }
      if (!rows.length) {
        el.innerHTML = '<p class="text-sm text-base-content/50 px-1 py-3">No matches.</p>';
        return;
      }
      el.innerHTML = rows.map(t => {
        const ends = new Date(t.ends_at * 1000).toISOString().replace('T', ' ').slice(0, 16) + 'Z';
        const active = (!creating && t.id === selectedId) ? ' is-active' : '';
        return '<button type="button" class="timer-list-item' + active + '" data-id="' + escapeHtml(t.id) + '">' +
          '<strong>' + escapeHtml(t.name || 'Untitled') + '</strong>' +
          '<span>Ends ' + ends + ' · ' + Number(t.width) + '\u00d7' + Number(t.height) + '</span></button>';
      }).join('');
      el.querySelectorAll('.timer-list-item').forEach(btn => {
        btn.addEventListener('click', () => selectTimer(btn.getAttribute('data-id')));
      });
    }

    async function loadList(preferId) {
      try {
        const r = await fetch(API, { credentials: 'same-origin' });
        if (r.status === 503) { window.location.href = 'install.php'; return; }
        if (r.status === 401) {
          window.location.href = 'login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
          return;
        }
        const j = await r.json();
        currentTimers = j.timers || [];
        renderList();
        const want = preferId || selectedId || INITIAL_ID;
        if (creating) return;
        if (want && currentTimers.some(t => t.id === want)) {
          selectTimer(want);
        } else if (currentTimers.length) {
          selectTimer(currentTimers[0].id);
        } else {
          showEmpty();
        }
      } catch (e) {
        document.getElementById('timer-list').innerHTML = '<p class="text-sm text-error">Could not load timers.</p>';
      }
    }

    document.getElementById('template_id').addEventListener('change', () => {
      const id = document.getElementById('template_id').value;
      if (id) applyTemplateToForm(id);
    });
    document.getElementById('timer-search').addEventListener('input', renderList);
    document.getElementById('btn-new').addEventListener('click', startCreate);
    document.getElementById('btn-new-top').addEventListener('click', startCreate);
    document.getElementById('btn-new-empty').addEventListener('click', startCreate);
    document.getElementById('btn-cancel-edit').addEventListener('click', () => {
      if (selectedId) selectTimer(selectedId);
      else if (currentTimers.length) selectTimer(currentTimers[0].id);
      else showEmpty();
    });
    document.getElementById('btn-delete').addEventListener('click', async () => {
      if (!editingId || !confirm('Delete this timer?')) return;
      const id = editingId;
      const dr = await fetch(API + '?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
      if (!dr.ok) { toast('Delete failed'); return; }
      toast('Deleted');
      selectedId = null;
      creating = false;
      await loadList();
    });

    document.getElementById('create-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('name').value.trim();
      const ends = document.getElementById('ends').value;
      if (!name || !ends) return;
      const body = {
        id: editingId || undefined,
        name,
        ends_at: toUnix(ends),
        label: document.getElementById('label').value.trim(),
        bg_color: document.getElementById('bg').value,
        text_color: document.getElementById('fg').value,
        accent_color: document.getElementById('ac').value,
        width: parseInt(document.getElementById('width').value, 10) || 560,
        height: parseInt(document.getElementById('height').value, 10) || 140,
        font_key: document.getElementById('font_key').value,
        font_size_main: parseInt(document.getElementById('font_size_main').value, 10) || 32,
        layout_key: document.getElementById('layout_key').value,
        template_id: document.getElementById('template_id').value.trim() || undefined,
      };
      const btn = document.getElementById('btn-create');
      btn.disabled = true;
      try {
        const method = editingId ? 'PUT' : 'POST';
        const r = await fetch(API, { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const j = await r.json();
        if (!r.ok) throw new Error(j.error || 'Save failed');
        toast(editingId ? 'Timer updated' : 'Timer created');
        creating = false;
        const newId = j.id || editingId;
        await loadList(newId);
      } catch (err) {
        toast(err.message || 'Error');
      }
      btn.disabled = false;
    });

    async function loadBrazeStatus() {
      try {
        const r = await fetch(BRAZE_API, { credentials: 'same-origin' });
        if (!r.ok) return;
        const j = await r.json();
        brazeConnected = !!j.connected;
      } catch (e) {}
    }

    loadBrazeStatus().then(async () => {
      await loadTemplatesForForm();
      if (START_NEW) startCreate();
      else await loadList(INITIAL_ID || undefined);
    });
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
