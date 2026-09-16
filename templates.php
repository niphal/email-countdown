<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_login_redirect();

$canEdit = auth_has_min_role(AUTH_ROLE_EDITOR);
$appNav = 'templates';
$appTitle = 'Templates';
$appSubtitle = 'Reusable brand styles with colors, layouts, and background images.';
$appTopActions = $canEdit ? '<button type="button" class="btn btn-primary btn-sm" id="btn-new-top">+ New template</button>' : '';
require __DIR__ . '/include/app_shell_start.php';
?>
    <div class="tpl-layout">
      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-4">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="card-title text-lg">Your templates</h2>
              <p class="text-sm text-base-content/60 mt-1">Pick one to edit, or create a new brand style.</p>
            </div>
            <?php if ($canEdit): ?>
            <button type="button" id="btn-new" class="btn btn-outline btn-sm">+ New</button>
            <?php endif; ?>
          </div>
          <div id="tpl-list" class="tpl-list"><p class="text-sm text-base-content/50">Loading…</p></div>
        </div>
      </div>

      <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-5">
          <div>
            <h2 class="card-title text-lg" id="editor-title">Edit template</h2>
            <p class="text-sm text-base-content/60 mt-1">Changes update the live preview. Upload a wide JPG/PNG (≤2MB) for hero-style backgrounds.</p>
          </div>

          <div class="preview-wrap">
            <img id="preview" alt="Template preview" src="" style="display:none">
            <p id="preview-empty" class="text-sm text-base-content/50 text-center m-0">Select or create a template to preview</p>
          </div>
          <div id="msg" class="alert py-3 text-sm" style="display:none" role="status"></div>

          <form id="editor" class="space-y-5">
            <input type="hidden" id="id" value="">
            <div class="grid gap-4 sm:grid-cols-2">
              <fieldset class="fieldset p-0">
                <label class="label" for="name"><span class="label-text font-semibold">Template name</span></label>
                <input id="name" required placeholder="Holiday brand 2026" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
              </fieldset>
              <fieldset class="fieldset p-0">
                <label class="label" for="layout_key"><span class="label-text font-semibold">Layout style</span></label>
                <select id="layout_key" class="select select-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
                  <?php foreach (timer_layout_labels() as $val => $lab): ?>
                  <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </fieldset>
              <fieldset class="fieldset p-0 sm:col-span-2">
                <label class="label" for="description"><span class="label-text font-semibold">Description (optional)</span></label>
                <input id="description" placeholder="Used for spring campaigns" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
              </fieldset>
              <fieldset class="fieldset p-0 sm:col-span-2">
                <label class="label" for="default_label"><span class="label-text font-semibold">Default line under countdown</span></label>
                <input id="default_label" placeholder="Shop now · Free shipping" class="input input-bordered w-full" <?= $canEdit ? '' : 'disabled' ?>>
              </fieldset>
            </div>

            <div class="grid grid-cols-3 gap-3">
              <fieldset class="fieldset p-0">
                <label class="label" for="bg"><span class="label-text">Fallback color</span></label>
                <input type="color" id="bg" value="#1a1a2e" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
              </fieldset>
              <fieldset class="fieldset p-0">
                <label class="label" for="fg"><span class="label-text">Text</span></label>
                <input type="color" id="fg" value="#eaeaea" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
              </fieldset>
              <fieldset class="fieldset p-0">
                <label class="label" for="ac"><span class="label-text">Accent</span></label>
                <input type="color" id="ac" value="#e94560" class="h-11 w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1" <?= $canEdit ? '' : 'disabled' ?>>
              </fieldset>
            </div>

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

            <div class="bg-upload space-y-3">
              <div>
                <strong class="text-sm">Background image</strong>
                <p class="text-xs text-base-content/60 mt-1 mb-0">Photo shows behind the countdown. Use overlay so digits stay readable.</p>
              </div>
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
                  <label class="label" for="overlay_opacity"><span class="label-text font-semibold">Overlay strength (<span id="overlay_val">35</span>%)</span></label>
                  <input type="range" id="overlay_opacity" min="0" max="100" value="35" class="range range-primary range-sm mt-2" <?= $canEdit ? '' : 'disabled' ?>>
                </fieldset>
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

  <script>
    const API = 'api/templates.php';
    const PREVIEW = 'api/template_preview.php';
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    let templates = [];
    let activeId = '';
    let previewTimer = null;

    function showMsg(text, ok) {
      const el = document.getElementById('msg');
      if (!text) { el.style.display = 'none'; el.textContent = ''; return; }
      el.style.display = '';
      el.className = 'alert py-3 text-sm ' + (ok ? 'alert-success' : 'alert-error');
      el.textContent = text;
    }

    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

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
      document.getElementById('layout_key').value = t.layout_key || 'segmented_pills';
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
    }

    function newTemplateDraft() {
      activeId = '';
      document.getElementById('id').value = '';
      document.getElementById('name').value = 'New brand template';
      document.getElementById('description').value = '';
      document.getElementById('default_label').value = '';
      document.getElementById('bg').value = '#004225';
      document.getElementById('fg').value = '#ffffff';
      document.getElementById('ac').value = '#c5a572';
      document.getElementById('width').value = '480';
      document.getElementById('height').value = '120';
      document.getElementById('font_key').value = 'noto_sans_bold';
      document.getElementById('font_size_main').value = '32';
      document.getElementById('layout_key').value = 'segmented_pills';
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
    }

    function renderList() {
      const el = document.getElementById('tpl-list');
      if (!templates.length) {
        el.innerHTML = '<p class="text-sm text-base-content/50 px-1 py-4">No templates yet. Create one to match your brand.</p>';
        return;
      }
      el.innerHTML = templates.map(t => {
        const active = t.id === activeId ? ' active' : '';
        const thumb = PREVIEW + '?id=' + encodeURIComponent(t.id) + '&_=' + Date.now();
        const def = Number(t.is_default) ? '<span class="badge badge-primary badge-sm mr-1">Default</span> ' : '';
        return '<div class="tpl-card' + active + '" data-id="' + esc(t.id) + '">' +
          '<img src="' + thumb + '" alt="" loading="lazy">' +
          '<div>' + def + '<strong>' + esc(t.name) + '</strong><span>' + esc(t.layout_key) + ' · ' + Number(t.width) + '\u00d7' + Number(t.height) + '</span></div></div>';
      }).join('');
      el.querySelectorAll('.tpl-card').forEach(card => {
        card.addEventListener('click', () => {
          const id = card.getAttribute('data-id');
          const t = templates.find(x => x.id === id);
          if (t) fillForm(t);
          renderList();
        });
      });
    }

    function schedulePreview() {
      clearTimeout(previewTimer);
      previewTimer = setTimeout(refreshPreview, 450);
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
      renderList();
      if (templates.length && !activeId) fillForm(templates[0]);
    }

    document.getElementById('overlay_opacity').addEventListener('input', e => {
      document.getElementById('overlay_val').textContent = e.target.value;
      schedulePreview();
    });
    ['name','description','default_label','bg','fg','ac','width','height','font_key','font_size_main','layout_key','overlay_color'].forEach(id => {
      document.getElementById(id).addEventListener('input', schedulePreview);
      document.getElementById(id).addEventListener('change', schedulePreview);
    });

    if (CAN_EDIT) {
      document.getElementById('btn-new').addEventListener('click', () => { newTemplateDraft(); renderList(); });
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
      });
    }

    (function(){
      const topBtn = document.getElementById('btn-new-top');
      const mainBtn = document.getElementById('btn-new');
      if (topBtn && mainBtn) topBtn.addEventListener('click', () => mainBtn.click());
    })();

    load();
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
