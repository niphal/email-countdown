<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/lib/monetization.php';
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

$appNav = 'dashboard';
$appTitle = 'Dashboard';
$appSubtitle = 'Create countdown timers, preview them, then copy Gmail-safe HTML.';
$appTopActions = '<a class="btn btn-outline btn-sm" href="templates.php">Templates</a>';
require __DIR__ . '/include/app_shell_start.php';
?>
    <ul class="steps steps-horizontal w-full max-w-xl mb-2" aria-label="How to use">
      <li class="step step-primary">Create</li>
      <li class="step step-primary">Preview</li>
      <li class="step">Copy for email</li>
    </ul>

    <?php if ($embedBlockedReason === 'root-relative'): ?>
    <div role="alert" class="alert alert-error shadow-sm">
      <span><strong>Gmail will not load these images yet.</strong> Embed URLs are still root-relative. Add <code class="bg-base-100 px-1 rounded">public_base_url</code> as an absolute HTTPS origin in <code class="bg-base-100 px-1 rounded">data/secrets.php</code>.</span>
    </div>
    <?php elseif ($embedBlockedReason === 'not-https'): ?>
    <div role="alert" class="alert alert-error shadow-sm">
      <span><strong>Gmail requires HTTPS image URLs.</strong> Set <code class="bg-base-100 px-1 rounded">public_base_url</code> to <code class="bg-base-100 px-1 rounded">https://…</code> in secrets.</span>
    </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-sm border border-base-300" id="billing-box">
      <div class="card-body flex-row flex-wrap items-center justify-between gap-4 py-5">
        <div>
          <div id="billing-title" class="font-bold text-base">Plan: Loading…</div>
          <div id="billing-kpis" class="billing-kpis"></div>
        </div>
        <button type="button" id="btn-upgrade" class="btn btn-outline btn-sm">Upgrade</button>
      </div>
    </div>

    <div class="grid-dash grid-dash-2">
      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-5">
          <div>
            <h2 class="card-title text-lg">Create timer</h2>
            <p class="text-sm text-base-content/60 mt-1">Name it, set when it ends, then create. Open Appearance for colors, size, or layout.</p>
          </div>
          <form id="create-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <fieldset class="fieldset p-0">
                <label class="label" for="template_id"><span class="label-text font-semibold">Brand template</span></label>
                <select id="template_id" name="template_id" class="select select-bordered w-full">
                  <option value="">Custom appearance</option>
                </select>
                <p class="label"><span class="label-text-alt"><a class="link link-primary" href="templates.php">Manage templates</a></span></p>
              </fieldset>
              <fieldset class="fieldset p-0">
                <label class="label" for="name"><span class="label-text font-semibold">Internal name</span></label>
                <input type="text" id="name" name="name" required placeholder="Spring sale ends" autocomplete="off" class="input input-bordered w-full">
                <p class="label"><span class="label-text-alt">Only shown in this dashboard</span></p>
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
                    <p class="label"><span class="label-text-alt">480–560 for mobile Gmail</span></p>
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
          <div class="card-actions justify-start gap-2 pt-1">
            <button type="submit" form="create-form" id="btn-create" class="btn btn-primary">Create timer</button>
            <button type="button" id="btn-cancel-edit" class="btn btn-ghost" style="display:none;">Cancel edit</button>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-4">
          <div>
            <h2 class="card-title text-lg">Tips &amp; activity</h2>
            <p class="text-sm text-base-content/60 mt-1">Gmail notes and recent workspace changes.</p>
          </div>
          <div class="collapse collapse-arrow border border-base-300 bg-base-200/40 rounded-box">
            <input type="checkbox" checked />
            <div class="collapse-title font-semibold text-sm">Gmail &amp; ESP tips</div>
            <div class="collapse-content text-sm text-base-content/70 leading-relaxed">
              Use <strong>Copy for Gmail</strong>. Image URLs need absolute HTTPS via <code class="bg-base-100 px-1 rounded">public_base_url</code>.
              Connect Braze under <strong>Integrations</strong>, then <strong>Push to Braze</strong> or <strong>Copy Braze Liquid</strong>.
            </div>
          </div>
          <div class="collapse collapse-arrow border border-base-300 bg-base-200/40 rounded-box">
            <input type="checkbox" />
            <div class="collapse-title font-semibold text-sm">Workspace activity</div>
            <div class="collapse-content">
              <p class="text-sm text-base-content/60 mb-2">Recent changes in this workspace.</p>
              <div id="audit" class="audit-lines"><span class="text-base-content/50">Loading…</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-4">
        <div>
          <h2 class="card-title text-lg">Your timers</h2>
          <p class="text-sm text-base-content/60 mt-1">Preview first, then copy HTML for your ESP.</p>
        </div>
        <div id="list"><p class="text-base-content/50">Loading…</p></div>
      </div>
    </div>

  <div id="toast" class="toast-app" role="status"></div>

  <script>
    const API = 'api/timers.php';
    const BILLING_API = 'api/billing.php';
    const AUDIT_API = 'api/audit.php';
    const BRAZE_API = 'api/braze.php';
    const TEMPLATES_API = 'api/templates.php';
    /** Root-relative: dashboard preview only */
    const TIMER_PREVIEW_PREFIX = <?= json_encode($timerPreviewPrefix, JSON_THROW_ON_ERROR) ?>;
    /** Absolute https? URL for pasted email HTML (from request host or public_base_url in secrets) */
    const TIMER_EMBED_PREFIX = <?= json_encode($timerEmbedPrefix, JSON_THROW_ON_ERROR) ?>;
    const EMBED_HTTPS_OK = <?= $embedIsHttpsAbsolute ? 'true' : 'false' ?>;
    let editingId = null;
    let currentTimers = [];
    let entitlements = null;
    let brazeConnected = false;
    let templateCatalog = [];

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

    function setAppearanceOpen(open) {
      const toggle = document.getElementById('appearance-toggle');
      if (toggle) toggle.checked = !!open;
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
        if (!current && def && !editingId) {
          sel.value = def.id;
          applyTemplateToForm(def.id);
        }
      } catch (e) {}
    }

    document.getElementById('template_id').addEventListener('change', () => {
      const id = document.getElementById('template_id').value;
      if (id) applyTemplateToForm(id);
    });

    function toast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t._h);
      t._h = setTimeout(() => t.classList.remove('show'), 2200);
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
      setAppearanceOpen(false);
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
      setAppearanceOpen(true);
      document.getElementById('btn-create').textContent = 'Save changes';
      document.getElementById('btn-cancel-edit').style.display = '';
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
          el.innerHTML = '<span class="text-base-content/50">Audit unavailable (sign in again or upgrade database).</span>';
          return;
        }
        const j = await r.json();
        const rows = j.entries || [];
        if (!rows.length) {
          el.innerHTML = '<span class="text-base-content/50">No activity yet.</span>';
          return;
        }
        el.innerHTML = rows.map(row => {
          const t = new Date((row.created_at || 0) * 1000);
          const ts = t.toISOString().replace('T', ' ').slice(0, 19) + 'Z';
          return '<div>' + escapeHtml(ts) + ' · ' + escapeHtml(row.action || '') + ' · ' + escapeHtml(row.entity_type || '') + ' ' + escapeHtml(String(row.entity_id || '').slice(0, 12)) + '...</div>';
        }).join('');
      } catch (e) {
        el.innerHTML = '<span class="text-base-content/50">Could not load audit log.</span>';
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
          list.innerHTML = '<div class="empty-panel"><p class="empty-title">No timers yet</p><p class="empty-sub">Create one above, then copy the HTML into your ESP.</p></div>';
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
            '<div class="meta"><span>Ends ' + endsLabel + '</span><span>' + Number(t.width) + '\u00d7' + Number(t.height) + '</span><span>' + escapeHtml(t.layout_key || 'segmented_pills') + '</span></div></div>' +
            '</div>' +
            '<div class="preview"></div>' +
            '<div class="actions-primary">' +
            '<button type="button" class="btn btn-primary btn-sm btn-copy" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '" data-height="' + Number(t.height) + '" data-ends="' + Number(t.ends_at) + '"' + httpsAttrs + '>Copy for Gmail</button>' +
            '<button type="button" class="btn btn-outline btn-sm btn-edit" data-id="' + escapeHtml(t.id) + '">Edit</button>' +
            '</div>' +
            '<div class="actions-secondary">' +
            '<button type="button" class="btn btn-ghost btn-sm btn-copy-dynamic" data-id="' + escapeHtml(t.id) + '" data-width="' + Number(t.width) + '" data-height="' + Number(t.height) + '" data-ends="' + Number(t.ends_at) + '" data-sig="' + escapeHtml(t.dynamic_sig || '') + '"' + httpsAttrs + '>Copy Dynamic HTML</button>' +
            '<button type="button" class="btn btn-ghost btn-sm btn-copy-png" data-id="' + escapeHtml(t.id) + '"' + httpsAttrs + '>Copy PNG countdown</button>' +
            '<button type="button" class="btn btn-ghost btn-sm btn-braze-push" data-id="' + escapeHtml(t.id) + '"' + (brazeConnected && EMBED_HTTPS_OK ? '' : ' disabled title="' + (brazeConnected ? 'Requires https public_base_url' : 'Connect Braze in Integrations') + '"') + '>Push to Braze</button>' +
            '<button type="button" class="btn btn-ghost btn-sm btn-braze-cc" data-id="' + escapeHtml(t.id) + '"' + (brazeConnected ? '' : ' disabled title="Connect Braze in Integrations"') + '>Copy Braze Liquid</button>' +
            '<button type="button" class="btn btn-ghost btn-sm btn-toggle-embed" data-id="' + escapeHtml(t.id) + '">Show HTML</button>' +
            '<button type="button" class="btn btn-error btn-outline btn-sm btn-del" data-id="' + escapeHtml(t.id) + '">Delete</button>' +
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
            navigator.clipboard.writeText(pngFallbackUrl(id)).then(() => toast('Copied animated PNG URL'));
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
        list.querySelectorAll('.btn-braze-push').forEach(btn => {
          btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            btn.disabled = true;
            try {
              const r = await fetch(BRAZE_API, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'push_content_block', timer_id: id })
              });
              const j = await r.json();
              if (!r.ok || !j.ok) {
                toast(j.error || 'Braze push failed');
                return;
              }
              toast('Pushed to Braze — ' + (j.liquid_tag || j.block_name || 'content block'));
              if (j.liquid_tag) {
                navigator.clipboard.writeText(j.liquid_tag).catch(() => {});
              }
            } catch (e) {
              toast('Braze push failed');
            } finally {
              btn.disabled = !brazeConnected || !EMBED_HTTPS_OK;
            }
          });
        });
        list.querySelectorAll('.btn-braze-cc').forEach(btn => {
          btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            try {
              const r = await fetch(BRAZE_API, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'snippets', timer_id: id })
              });
              const j = await r.json();
              if (!r.ok) {
                toast(j.error || 'Could not load Braze snippet');
                return;
              }
              const text = j.content_block_liquid
                ? j.content_block_liquid
                : (j.connected_content_liquid || '');
              if (!text) {
                toast('No Braze snippet available');
                return;
              }
              await navigator.clipboard.writeText(text);
              toast(j.content_block_liquid ? 'Copied Content Block Liquid' : 'Copied Connected Content Liquid');
            } catch (e) {
              toast('Could not copy Braze Liquid');
            }
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
        template_id: document.getElementById('template_id').value.trim() || undefined,
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

    async function loadBrazeStatus() {
      try {
        const r = await fetch(BRAZE_API, { credentials: 'same-origin' });
        if (!r.ok) return;
        const j = await r.json();
        brazeConnected = !!j.connected;
      } catch (e) {}
    }

    resetCreateForm();
    loadBrazeStatus().then(() => {
      loadTemplatesForForm();
      loadBilling();
      loadList();
      loadAudit();
    });
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
