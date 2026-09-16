<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/timer_template_presets.php';

auth_start_session();
auth_require_login_redirect();

$canEdit = auth_current_user() !== null;
$presets = timer_template_presets_list();
$categories = [];
foreach ($presets as $p) {
    $cat = (string) ($p['category'] ?? 'Style');
    $categories[$cat] = true;
}
ksort($categories);

$appNav = 'gallery';
$appTitle = 'Template gallery';
$appSubtitle = 'Browse ' . count($presets) . ' countdown concepts. Add any style to your library, then customize.';
$appContentWide = true;
$appTopActions = '<a class="btn btn-outline btn-sm" href="templates.php">My templates</a>'
    . ($canEdit ? '<button type="button" class="btn btn-primary btn-sm" id="btn-add-all">Add all to library</button>' : '');
require __DIR__ . '/include/app_shell_start.php';
?>
    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-4 py-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="card-title text-lg">Concepts</h2>
            <p class="text-sm text-base-content/60 mt-1">Filter by category, preview live, then add to your workspace library.</p>
          </div>
          <div class="flex flex-wrap gap-2 items-center">
            <label class="label py-0" for="filter-cat"><span class="label-text font-semibold text-xs uppercase tracking-wide text-base-content/50">Category</span></label>
            <select id="filter-cat" class="select select-bordered select-sm">
              <option value="">All</option>
              <?php foreach (array_keys($categories) as $cat): ?>
              <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <input id="filter-q" type="search" placeholder="Search styles…" class="input input-bordered input-sm w-44" autocomplete="off">
          </div>
        </div>
        <div id="msg" class="alert py-3 text-sm" style="display:none" role="status"></div>
        <div id="gallery" class="tpl-gallery"></div>
      </div>
    </div>

  <div id="toast" class="toast-app" role="status"></div>

  <script>
    const API = 'api/templates.php';
    const PREVIEW = 'api/template_preview.php';
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    const PRESETS = <?= json_encode($presets, JSON_THROW_ON_ERROR) ?>;

    function esc(s) {
      return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function toast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t._h);
      t._h = setTimeout(() => t.classList.remove('show'), 2400);
    }
    function showMsg(text, ok) {
      const el = document.getElementById('msg');
      if (!text) { el.style.display = 'none'; return; }
      el.style.display = '';
      el.className = 'alert py-3 text-sm ' + (ok ? 'alert-success' : 'alert-error');
      el.textContent = text;
      toast(text);
    }
    function swatches(bg, fg, ac) {
      return '<div class="tpl-swatches">' +
        '<span class="tpl-swatch" style="background:' + esc(bg) + '"></span>' +
        '<span class="tpl-swatch" style="background:' + esc(fg) + '"></span>' +
        '<span class="tpl-swatch" style="background:' + esc(ac) + '"></span></div>';
    }

    function filtered() {
      const cat = document.getElementById('filter-cat').value;
      const q = document.getElementById('filter-q').value.trim().toLowerCase();
      return PRESETS.filter(p => {
        if (cat && (p.category || '') !== cat) return false;
        if (!q) return true;
        const hay = ((p.name || '') + ' ' + (p.description || '') + ' ' + (p.category || '') + ' ' + (p.layout_label || '')).toLowerCase();
        return hay.includes(q);
      });
    }

    function render() {
      const el = document.getElementById('gallery');
      const rows = filtered();
      if (!rows.length) {
        el.innerHTML = '<div class="empty-panel sm:col-span-full"><p class="empty-title">No matching styles</p><p class="empty-sub">Try another category or clear the search.</p></div>';
        return;
      }
      el.innerHTML = rows.map(p => {
        const thumb = PREVIEW + '?preset=' + encodeURIComponent(p.preset_key) + '&_=' + Date.now();
        return '<article class="tpl-gallery-card" data-preset="' + esc(p.preset_key) + '">' +
          '<div class="tpl-gallery-preview"><img src="' + thumb + '" alt="" loading="lazy"></div>' +
          '<div class="tpl-gallery-body">' +
          '<div class="flex items-start justify-between gap-2 flex-wrap">' +
          '<h3>' + esc(p.name) + '</h3>' +
          '<span class="badge badge-ghost badge-sm border border-base-300">' + esc(p.category || 'Style') + '</span></div>' +
          '<p>' + esc(p.description || '') + '</p>' +
          swatches(p.bg_color, p.text_color, p.accent_color) +
          '<div class="text-xs text-base-content/50">' + esc(p.layout_label || p.layout_key) + ' · ' + Number(p.width) + '\u00d7' + Number(p.height) + '</div>' +
          (CAN_EDIT
            ? '<div class="flex flex-wrap gap-2 mt-auto pt-1"><button type="button" class="btn btn-primary btn-sm btn-add">Add to library</button><a class="btn btn-ghost btn-sm" href="templates.php#starters">Customize later</a></div>'
            : '') +
          '</div></article>';
      }).join('');
      el.querySelectorAll('.btn-add').forEach(btn => {
        btn.addEventListener('click', async () => {
          const key = btn.closest('[data-preset]').getAttribute('data-preset');
          btn.disabled = true;
          try {
            const r = await fetch(API, {
              method: 'POST', credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'add_preset', preset_key: key }),
            });
            const j = await r.json();
            if (!r.ok) { showMsg(j.error || 'Could not add', false); return; }
            showMsg('Added “' + (j.template.name || 'style') + '” to My templates', true);
          } finally {
            btn.disabled = false;
          }
        });
      });
    }

    document.getElementById('filter-cat').addEventListener('change', render);
    document.getElementById('filter-q').addEventListener('input', render);
    const addAll = document.getElementById('btn-add-all');
    if (addAll) {
      addAll.addEventListener('click', async () => {
        if (!confirm('Add every gallery concept that is not already in your library?')) return;
        addAll.disabled = true;
        try {
          const r = await fetch(API, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'seed_presets', mode: 'all' }),
          });
          const j = await r.json();
          if (!r.ok) { showMsg(j.error || 'Could not add styles', false); return; }
          showMsg(j.added ? ('Added ' + j.added + ' style' + (j.added === 1 ? '' : 's')) : 'All concepts already in your library', true);
        } finally {
          addAll.disabled = false;
        }
      });
    }
    render();
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
