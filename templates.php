<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/lib/timer_template_presets.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_login_redirect();

$canEdit = auth_current_user() !== null;
$layoutLabels = timer_layout_labels();
$layoutBlurbs = timer_layout_blurbs();
$appNav = 'templates';
$appTitle = 'My templates';
$appSubtitle = 'Saved brand styles for this workspace. Browse the gallery for more concepts.';
$appContentWide = true;
$appTopActions = $canEdit
    ? '<a class="btn btn-outline btn-sm" href="gallery.php">Gallery</a>'
      . '<button type="button" class="btn btn-outline btn-sm" id="btn-seed-top">Add starter pack</button>'
      . '<button type="button" class="btn btn-primary btn-sm" id="btn-new-top">+ Custom template</button>'
    : '<a class="btn btn-outline btn-sm" href="gallery.php">Gallery</a>';
require __DIR__ . '/include/app_shell_start.php';
?>
    <div role="tablist" class="tabs tabs-boxed bg-base-100 border border-base-300 p-1 w-fit max-w-full flex-wrap gap-1">
      <a role="tab" class="tab tab-active" id="tab-library" href="#library">Your library</a>
      <a role="tab" class="tab" id="tab-starters" href="#starters">Starter pack</a>
      <a role="tab" class="tab" id="tab-editor" href="#editor">Editor</a>
    </div>

    <section id="panel-library" class="space-y-4">
      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-4 py-5">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 class="card-title text-lg">Your library</h2>
              <p class="text-sm text-base-content/60 mt-1">Full previews of templates saved to this workspace. Click a card to edit.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <?php if ($canEdit): ?>
              <button type="button" id="btn-seed" class="btn btn-outline btn-sm">Add starter pack</button>
              <button type="button" id="btn-new" class="btn btn-primary btn-sm">+ Custom</button>
              <?php endif; ?>
            </div>
          </div>
          <div id="library-empty" class="empty-panel" style="display:none">
            <p class="empty-title">No templates yet</p>
            <p class="empty-sub">Open the gallery for 25+ concepts, or add the core starter pack.</p>
            <?php if ($canEdit): ?>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
              <a class="btn btn-primary btn-sm" href="gallery.php">Browse gallery</a>
              <button type="button" class="btn btn-outline btn-sm" id="btn-seed-empty">Add starter pack</button>
              <button type="button" class="btn btn-ghost btn-sm" id="btn-new-empty">Start blank</button>
            </div>
            <?php endif; ?>
          </div>
          <div id="tpl-gallery" class="tpl-gallery"></div>
        </div>
      </div>
    </section>

    <section id="panel-starters" class="space-y-4" hidden>
      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-4 py-5">
          <div>
            <h2 class="card-title text-lg">Starter pack</h2>
            <p class="text-sm text-base-content/60 mt-1">Core styles for a quick start. For the full set of concepts, open the <a class="link link-primary" href="gallery.php">template gallery</a>.</p>
          </div>
          <div id="preset-gallery" class="tpl-gallery"><p class="text-sm text-base-content/50">Loading…</p></div>
        </div>
      </div>
    </section>

    <section id="panel-editor" class="space-y-4" hidden>
      <div class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr] items-start">
        <div class="card bg-base-100 shadow-sm border border-base-300">
          <div class="card-body gap-5">
            <div>
              <h2 class="card-title text-lg" id="editor-title">Edit template</h2>
              <p class="text-sm text-base-content/60 mt-1">Live preview updates as you change colors, size, and layout.</p>
            </div>
            <div class="preview-stage">
              <img id="preview" alt="Template preview" src="" style="display:none">
              <p id="preview-empty" class="preview-empty-msg">Select a template or start a custom draft</p>
            </div>
            <div id="msg" class="alert py-3 text-sm" style="display:none" role="status"></div>
          </div>
        </div>

        <div class="card bg-base-100 shadow-sm border border-base-300">
          <div class="card-body gap-5">
            <form id="editor" class="space-y-5">
              <input type="hidden" id="id" value="">

              <div class="space-y-4">
                <h3 class="font-semibold text-sm tracking-wide uppercase text-base-content/50">Basics</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                  <fieldset class="fieldset p-0 sm:col-span-2">
                    <label class="label" for="name"><span class="label-text font-semibold">Template name</span></label>
                    <input id="name" required placeholder="Holiday brand 2026" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                  <fieldset class="fieldset p-0 sm:col-span-2">
                    <label class="label" for="description"><span class="label-text font-semibold">Description</span></label>
                    <input id="description" placeholder="Used for spring campaigns" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                  <fieldset class="fieldset p-0 sm:col-span-2">
                    <label class="label" for="default_label"><span class="label-text font-semibold">Line under countdown</span></label>
                    <input id="default_label" placeholder="Shop now · Free shipping" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                </div>
              </div>

              <div class="space-y-3">
                <h3 class="font-semibold text-sm tracking-wide uppercase text-base-content/50">Layout</h3>
                <div class="layout-pick" id="layout-pick">
                  <?php foreach ($layoutLabels as $val => $lab): ?>
                  <label>
                    <input type="radio" name="layout_key_radio" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                    <strong><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars($layoutBlurbs[$val] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                  </label>
                  <?php endforeach; ?>
                </div>
                <select id="layout_key" class="hidden" <?= $canEdit ? '' : 'disabled' ?>>
                  <?php foreach ($layoutLabels as $val => $lab): ?>
                  <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="space-y-4">
                <h3 class="font-semibold text-sm tracking-wide uppercase text-base-content/50">Colors</h3>
                <div class="grid grid-cols-3 gap-3">
                  <fieldset class="fieldset p-0">
                    <label class="label" for="bg"><span class="label-text">Background</span></label>
                    <input type="color" id="bg" value="#1a1a2e" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="fg"><span class="label-text">Text</span></label>
                    <input type="color" id="fg" value="#eaeaea" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="ac"><span class="label-text">Countdown</span></label>
                    <input type="color" id="ac" value="#e94560" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="font-semibold text-sm tracking-wide uppercase text-base-content/50">Size &amp; type</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                  <fieldset class="fieldset p-0">
                    <label class="label" for="width"><span class="label-text font-semibold">Width (px)</span></label>
                    <input type="number" id="width" min="200" max="600" step="10" value="480" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="height"><span class="label-text font-semibold">Height (px)</span></label>
                    <input type="number" id="height" min="80" max="300" step="10" value="120" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="font_key"><span class="label-text font-semibold">Font</span></label>
                    <select id="font_key" class="select select-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                      <?php foreach (timer_font_labels() as $val => $lab): ?>
                      <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </fieldset>
                  <fieldset class="fieldset p-0">
                    <label class="label" for="font_size_main"><span class="label-text font-semibold">Main size (px)</span></label>
                    <input type="number" id="font_size_main" min="14" max="72" value="32" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  </fieldset>
                </div>
              </div>

              <div class="space-y-3">
                <h3 class="font-semibold text-sm tracking-wide uppercase text-base-content/50">Background image</h3>
                <div class="bg-upload space-y-3">
                  <p class="text-xs text-base-content/60 m-0">Optional photo behind the countdown. Overlay keeps digits readable.</p>
                  <?php if ($canEdit): ?>
                  <input type="file" id="bg_file" accept="image/jpeg,image/png,image/webp,image/gif" class="file-input file-input-bordered w-full">
                  <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline btn-sm" id="btn-upload-bg" disabled>Upload image</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="btn-remove-bg" disabled>Remove image</button>
                  </div>
                  <?php endif; ?>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <fieldset class="fieldset p-0">
                      <label class="label" for="overlay_color"><span class="label-text font-semibold">Overlay color</span></label>
                      <input type="color" id="overlay_color" value="#000000" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
                    </fieldset>
                    <fieldset class="fieldset p-0">
                      <label class="label" for="overlay_opacity"><span class="label-text font-semibold">Overlay (<span id="overlay_val">35</span>%)</span></label>
                      <input type="range" id="overlay_opacity" min="0" max="100" value="35" class="range range-primary range-sm mt-2" <?= $canEdit ? '' : 'disabled' ?>>
                    </fieldset>
                  </div>
                </div>
              </div>

              <label class="label cursor-pointer justify-start gap-3 py-0">
                <input type="checkbox" id="is_default" class="checkbox checkbox-primary" <?= $canEdit ? '' : 'disabled' ?>>
                <span class="label-text">Default template for new timers</span>
              </label>

              <?php if ($canEdit): ?>
              <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" id="btn-save" class="btn btn-primary">Save template</button>
                <button type="button" class="btn btn-error btn-outline" id="btn-delete" disabled>Delete</button>
              </div>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    </section>

  <div id="toast" class="toast-app" role="status"></div>

  <script>
    const API = 'api/templates.php';
    const PREVIEW = 'api/template_preview.php';
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    const LAYOUT_LABELS = <?= json_encode($layoutLabels, JSON_THROW_ON_ERROR) ?>;
    const CORE_PRESET_KEYS = <?= json_encode(timer_template_core_preset_keys(), JSON_THROW_ON_ERROR) ?>;
    let templates = [];
    let presets = [];
    let activeId = '';
    let previewTimer = null;

    function toast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(t._h);
      t._h = setTimeout(() => t.classList.remove('show'), 2400);
    }

    function showMsg(text, ok) {
      const el = document.getElementById('msg');
      if (!text) { el.style.display = 'none'; el.textContent = ''; return; }
      el.style.display = '';
      el.className = 'alert py-3 text-sm ' + (ok ? 'alert-success' : 'alert-error');
      el.textContent = text;
      toast(text);
    }

    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function setTab(name) {
      const map = { library: 'panel-library', starters: 'panel-starters', editor: 'panel-editor' };
      Object.keys(map).forEach(k => {
        document.getElementById(map[k]).hidden = k !== name;
        const tab = document.getElementById('tab-' + k);
        tab.classList.toggle('tab-active', k === name);
      });
      if (name === 'editor') schedulePreview();
    }

    function syncLayoutRadios(value) {
      document.getElementById('layout_key').value = value;
      document.querySelectorAll('input[name="layout_key_radio"]').forEach(r => {
        r.checked = r.value === value;
      });
    }

    function payloadFromForm() {
      return {
        id: document.getElementById('id').value || undefined,
        name: document.getElementById('name').value.trim(),
        description: document.getElementById('description').value.trim(),
        default_label: document.getElementById('default_label').value.trim(),
        bg_color: document.getElementById('bg').value,
        text_color: document.getElementById('fg').value,
        accent_color: document.getElementById('ac').value,
        width: parseInt(document.getElementById('width').value, 10) || 480,
        height: parseInt(document.getElementById('height').value, 10) || 120,
        font_key: document.getElementById('font_key').value,
        font_size_main: parseInt(document.getElementById('font_size_main').value, 10) || 32,
        layout_key: document.getElementById('layout_key').value,
        bg_overlay_color: document.getElementById('overlay_color').value,
        bg_overlay_opacity: parseInt(document.getElementById('overlay_opacity').value, 10) || 0,
        is_default: document.getElementById('is_default').checked,
      };
    }

    function fillForm(t) {
      activeId = t.id;
      document.getElementById('id').value = t.id;
      document.getElementById('name').value = t.name || '';
      document.getElementById('description').value = t.description || '';
      document.getElementById('default_label').value = t.default_label || '';
      document.getElementById('bg').value = t.bg_color || '#1a1a2e';
      document.getElementById('fg').value = t.text_color || '#eaeaea';
      document.getElementById('ac').value = t.accent_color || '#e94560';
      document.getElementById('width').value = String(t.width || 480);
      document.getElementById('height').value = String(t.height || 120);
      document.getElementById('font_key').value = t.font_key || 'noto_sans_bold';
      document.getElementById('font_size_main').value = String(t.font_size_main || 32);
      syncLayoutRadios(t.layout_key || 'segmented_pills');
      document.getElementById('overlay_color').value = t.bg_overlay_color || '#000000';
      document.getElementById('overlay_opacity').value = String(t.bg_overlay_opacity ?? 35);
      document.getElementById('overlay_val').textContent = String(t.bg_overlay_opacity ?? 35);
      document.getElementById('is_default').checked = Number(t.is_default) === 1;
      const del = document.getElementById('btn-delete');
      const rem = document.getElementById('btn-remove-bg');
      if (del) del.disabled = false;
      if (rem) rem.disabled = !(t.bg_image_file);
      document.getElementById('editor-title').textContent = 'Edit: ' + (t.name || 'Template');
      schedulePreview();
      renderLibrary();
    }

    function newTemplateDraft() {
      activeId = '';
      document.getElementById('id').value = '';
      document.getElementById('name').value = 'New brand template';
      document.getElementById('description').value = '';
      document.getElementById('default_label').value = '';
      document.getElementById('bg').value = '#0f172a';
      document.getElementById('fg').value = '#e2e8f0';
      document.getElementById('ac').value = '#3b82f6';
      document.getElementById('width').value = '520';
      document.getElementById('height').value = '128';
      document.getElementById('font_key').value = 'noto_sans_bold';
      document.getElementById('font_size_main').value = '34';
      syncLayoutRadios('segmented_pills');
      document.getElementById('overlay_color').value = '#000000';
      document.getElementById('overlay_opacity').value = '40';
      document.getElementById('overlay_val').textContent = '40';
      document.getElementById('is_default').checked = false;
      const del = document.getElementById('btn-delete');
      const rem = document.getElementById('btn-remove-bg');
      if (del) del.disabled = true;
      if (rem) rem.disabled = true;
      document.getElementById('editor-title').textContent = 'New template';
      document.getElementById('preview').style.display = 'none';
      document.getElementById('preview-empty').style.display = 'block';
      schedulePreview();
      renderLibrary();
      setTab('editor');
    }

    function swatches(bg, fg, ac) {
      return '<div class="tpl-swatches">' +
        '<span class="tpl-swatch" style="background:' + esc(bg) + '" title="Background"></span>' +
        '<span class="tpl-swatch" style="background:' + esc(fg) + '" title="Text"></span>' +
        '<span class="tpl-swatch" style="background:' + esc(ac) + '" title="Accent"></span>' +
        '</div>';
    }

    function renderLibrary() {
      const el = document.getElementById('tpl-gallery');
      const empty = document.getElementById('library-empty');
      if (!templates.length) {
        el.innerHTML = '';
        empty.style.display = '';
        return;
      }
      empty.style.display = 'none';
      el.innerHTML = templates.map(t => {
        const active = t.id === activeId ? ' is-active' : '';
        const thumb = PREVIEW + '?id=' + encodeURIComponent(t.id) + '&_=' + Date.now();
        const layout = LAYOUT_LABELS[t.layout_key] || t.layout_key;
        const def = Number(t.is_default) ? '<span class="badge badge-primary badge-sm">Default</span>' : '';
        const desc = t.description ? '<p>' + esc(t.description) + '</p>' : '<p>' + esc(layout) + ' · ' + Number(t.width) + '\u00d7' + Number(t.height) + '</p>';
        return '<article class="tpl-gallery-card' + active + '" data-id="' + esc(t.id) + '">' +
          '<div class="tpl-gallery-preview"><img src="' + thumb + '" alt="" loading="lazy"></div>' +
          '<div class="tpl-gallery-body">' +
          '<div class="flex items-start justify-between gap-2 flex-wrap">' +
          '<h3>' + esc(t.name) + '</h3>' + def +
          '</div>' + desc +
          swatches(t.bg_color || '#111', t.text_color || '#eee', t.accent_color || '#2563eb') +
          '<div class="flex flex-wrap gap-2 mt-auto pt-1">' +
          '<button type="button" class="btn btn-primary btn-sm btn-edit-card">Edit</button>' +
          '</div></div></article>';
      }).join('');
      el.querySelectorAll('.tpl-gallery-card').forEach(card => {
        const open = () => {
          const id = card.getAttribute('data-id');
          const t = templates.find(x => x.id === id);
          if (t) { fillForm(t); setTab('editor'); }
        };
        card.addEventListener('click', e => {
          if (e.target.closest('button')) return;
          open();
        });
        const btn = card.querySelector('.btn-edit-card');
        if (btn) btn.addEventListener('click', open);
      });
    }

    function renderPresets() {
      const el = document.getElementById('preset-gallery');
      const starterPresets = presets.filter(p => CORE_PRESET_KEYS.includes(p.preset_key));
      if (!starterPresets.length) {
        el.innerHTML = '<p class="text-sm text-base-content/50">No starter styles available. <a class="link link-primary" href="gallery.php">Browse the full gallery</a>.</p>';
        return;
      }
      el.innerHTML = starterPresets.map(p => {
        const thumb = PREVIEW + '?preset=' + encodeURIComponent(p.preset_key) + '&_=' + Date.now();
        const layout = p.layout_label || LAYOUT_LABELS[p.layout_key] || p.layout_key;
        return '<article class="tpl-gallery-card" data-preset="' + esc(p.preset_key) + '">' +
          '<div class="tpl-gallery-preview"><img src="' + thumb + '" alt="" loading="lazy"></div>' +
          '<div class="tpl-gallery-body">' +
          '<div class="flex items-start justify-between gap-2 flex-wrap">' +
          '<h3>' + esc(p.name) + '</h3>' +
          '<span class="badge badge-ghost badge-sm border border-base-300">' + esc(p.category || 'Style') + '</span>' +
          '</div>' +
          '<p>' + esc(p.description || layout) + '</p>' +
          swatches(p.bg_color, p.text_color, p.accent_color) +
          '<div class="text-xs text-base-content/50">' + esc(layout) + ' · ' + Number(p.width) + '\u00d7' + Number(p.height) + '</div>' +
          (CAN_EDIT
            ? '<div class="flex flex-wrap gap-2 mt-auto pt-1"><button type="button" class="btn btn-primary btn-sm btn-add-preset">Add to library</button><button type="button" class="btn btn-ghost btn-sm btn-try-preset">Customize</button></div>'
            : '') +
          '</div></article>';
      }).join('');
      if (!CAN_EDIT) return;
      el.querySelectorAll('.btn-add-preset').forEach(btn => {
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
            if (!r.ok) { showMsg(j.error || 'Could not add preset', false); setTab('editor'); return; }
            activeId = j.template.id;
            await load();
            fillForm(j.template);
            setTab('editor');
            showMsg('Added “' + (j.template.name || 'template') + '” to your library', true);
          } finally {
            btn.disabled = false;
          }
        });
      });
      el.querySelectorAll('.btn-try-preset').forEach(btn => {
        btn.addEventListener('click', () => {
          const key = btn.closest('[data-preset]').getAttribute('data-preset');
          const p = presets.find(x => x.preset_key === key);
          if (!p) return;
          activeId = '';
          document.getElementById('id').value = '';
          document.getElementById('name').value = p.name || 'Custom style';
          document.getElementById('description').value = p.description || '';
          document.getElementById('default_label').value = p.default_label || '';
          document.getElementById('bg').value = p.bg_color;
          document.getElementById('fg').value = p.text_color;
          document.getElementById('ac').value = p.accent_color;
          document.getElementById('width').value = String(p.width);
          document.getElementById('height').value = String(p.height);
          document.getElementById('font_key').value = p.font_key;
          document.getElementById('font_size_main').value = String(p.font_size_main);
          syncLayoutRadios(p.layout_key);
          document.getElementById('overlay_color').value = p.bg_overlay_color || '#000000';
          document.getElementById('overlay_opacity').value = String(p.bg_overlay_opacity ?? 35);
          document.getElementById('overlay_val').textContent = String(p.bg_overlay_opacity ?? 35);
          document.getElementById('is_default').checked = false;
          const del = document.getElementById('btn-delete');
          const rem = document.getElementById('btn-remove-bg');
          if (del) del.disabled = true;
          if (rem) rem.disabled = true;
          document.getElementById('editor-title').textContent = 'Customize: ' + (p.name || 'Style');
          schedulePreview();
          setTab('editor');
        });
      });
    }

    function schedulePreview() {
      clearTimeout(previewTimer);
      previewTimer = setTimeout(refreshPreview, 400);
    }

    async function refreshPreview() {
      const payload = payloadFromForm();
      if (activeId) payload.id = activeId;
      const t = templates.find(x => x.id === activeId);
      if (t && t.bg_image_file) payload.bg_image_file = t.bg_image_file;
      try {
        const r = await fetch(PREVIEW, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        if (!r.ok) return;
        const blob = await r.blob();
        const img = document.getElementById('preview');
        img.src = URL.createObjectURL(blob);
        img.style.display = 'block';
        document.getElementById('preview-empty').style.display = 'none';
      } catch (e) {}
    }

    async function load() {
      const r = await fetch(API, { credentials: 'same-origin' });
      const j = await r.json();
      templates = j.templates || [];
      presets = j.presets || [];
      renderLibrary();
      renderPresets();
      if (templates.length && !activeId) fillForm(templates[0]);
    }

    async function seedPresets() {
      if (!CAN_EDIT) return;
      const r = await fetch(API, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'seed_presets', mode: 'core' }),
      });
      const j = await r.json();
      if (!r.ok) { showMsg(j.error || 'Could not add starters', false); setTab('editor'); return; }
      templates = j.templates || [];
      renderLibrary();
      if (templates.length) {
        fillForm(templates[0]);
        setTab('library');
      }
      showMsg(j.added ? ('Added ' + j.added + ' starter style' + (j.added === 1 ? '' : 's')) : 'Starter styles already in your library', true);
      setTab(j.added ? 'library' : 'starters');
    }

    document.getElementById('tab-library').addEventListener('click', e => { e.preventDefault(); setTab('library'); });
    document.getElementById('tab-starters').addEventListener('click', e => { e.preventDefault(); setTab('starters'); });
    document.getElementById('tab-editor').addEventListener('click', e => { e.preventDefault(); setTab('editor'); });

    document.querySelectorAll('input[name="layout_key_radio"]').forEach(r => {
      r.addEventListener('change', () => {
        if (!r.checked) return;
        document.getElementById('layout_key').value = r.value;
        schedulePreview();
      });
    });

    document.getElementById('overlay_opacity').addEventListener('input', e => {
      document.getElementById('overlay_val').textContent = e.target.value;
      schedulePreview();
    });
    ['name','description','default_label','bg','fg','ac','width','height','font_key','font_size_main','overlay_color'].forEach(id => {
      document.getElementById(id).addEventListener('input', schedulePreview);
      document.getElementById(id).addEventListener('change', schedulePreview);
    });

    function bindNew(btn) {
      if (btn) btn.addEventListener('click', () => newTemplateDraft());
    }
    function bindSeed(btn) {
      if (btn) btn.addEventListener('click', () => seedPresets());
    }
    bindNew(document.getElementById('btn-new'));
    bindNew(document.getElementById('btn-new-top'));
    bindNew(document.getElementById('btn-new-empty'));
    bindSeed(document.getElementById('btn-seed'));
    bindSeed(document.getElementById('btn-seed-top'));
    bindSeed(document.getElementById('btn-seed-empty'));

    if (CAN_EDIT) {
      document.getElementById('bg_file').addEventListener('change', () => {
        document.getElementById('btn-upload-bg').disabled = !document.getElementById('bg_file').files.length;
      });
      document.getElementById('btn-upload-bg').addEventListener('click', async () => {
        if (!activeId) { showMsg('Save the template first, then upload an image.', false); return; }
        const f = document.getElementById('bg_file').files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('action', 'upload_bg');
        fd.append('template_id', activeId);
        fd.append('bg_image', f);
        const r = await fetch(API, { method: 'POST', credentials: 'same-origin', body: fd });
        const j = await r.json();
        if (!r.ok) { showMsg(j.error || 'Upload failed', false); return; }
        showMsg('Background uploaded', true);
        document.getElementById('bg_file').value = '';
        document.getElementById('btn-upload-bg').disabled = true;
        document.getElementById('btn-remove-bg').disabled = false;
        await load();
        const t = templates.find(x => x.id === activeId);
        if (t) fillForm(t);
      });
      document.getElementById('btn-remove-bg').addEventListener('click', async () => {
        if (!activeId || !confirm('Remove background image?')) return;
        const r = await fetch(API, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: activeId, ...payloadFromForm(), remove_bg_image: true }) });
        const j = await r.json();
        if (!r.ok) { showMsg(j.error || 'Could not remove image', false); return; }
        showMsg('Background removed', true);
        await load();
        fillForm(j.template);
      });
      document.getElementById('editor').addEventListener('submit', async e => {
        e.preventDefault();
        const payload = payloadFromForm();
        const isNew = !payload.id;
        const r = await fetch(API, { method: isNew ? 'POST' : 'PUT', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const j = await r.json();
        if (!r.ok) { showMsg(j.error || 'Save failed', false); return; }
        showMsg('Template saved', true);
        activeId = j.template.id;
        await load();
        fillForm(j.template);
      });
      document.getElementById('btn-delete').addEventListener('click', async () => {
        if (!activeId || !confirm('Delete this template?')) return;
        const r = await fetch(API + '?id=' + encodeURIComponent(activeId), { method: 'DELETE', credentials: 'same-origin' });
        const j = await r.json();
        if (!r.ok) { showMsg(j.error || 'Delete failed', false); return; }
        activeId = '';
        newTemplateDraft();
        await load();
        showMsg('Deleted', true);
        setTab('library');
      });
    }

    load();
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
