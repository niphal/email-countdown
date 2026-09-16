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
$appTopActions = $canEdit ? '<button type="button" class="secondary" id="btn-new-top">+ New template</button>' : '';
require __DIR__ . '/include/app_shell_start.php';
?>
<style>
  .layout { display: grid; gap: 1rem; }
  @media (min-width: 960px) { .layout { grid-template-columns: 300px 1fr; align-items: start; } }
  .tpl-list { display: flex; flex-direction: column; gap: .55rem; max-height: 560px; overflow-y: auto; }
  .tpl-card { border: 1px solid var(--border); border-radius: 10px; padding: .55rem; cursor: pointer; background: #fafbfc; display: grid; grid-template-columns: 72px 1fr; gap: .55rem; align-items: center; }
  .tpl-card.active { border-color: var(--accent); background: var(--accent-soft); }
  .tpl-card img { width: 72px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); background: #0f1720; }
  .preview-wrap { border: 1px solid var(--border); border-radius: 10px; padding: .75rem; background: #f8f9fb; margin-bottom: .85rem; }
  .msg { font-size: .85rem; margin: .5rem 0 0; padding: .6rem .75rem; border-radius: 8px; display: none; }
  .msg.show { display: block; }
  .msg.ok { background: var(--accent-soft); border: 1px solid #b7d7c4; }
  .msg.err { background: var(--danger-bg); border: 1px solid #efcaca; }
  .bg-upload { border: 1px dashed var(--border); border-radius: 10px; padding: .75rem; background: #fafbfc; margin-top: .5rem; }
  .hint { font-size: .75rem; color: var(--muted); margin-top: .25rem; }
  input[type="range"] { padding: 0; }
</style>
<p class="card-lead" style="margin-bottom:1rem">Save reusable brand styles—colors, layout, optional background photo with overlay—then pick a template when creating timers on the dashboard.</p>

    <div class="layout">
      <div class="card">
        <h2>Your templates</h2>
        <div class="row" style="margin-top:0;margin-bottom:.65rem">
          <?php if ($canEdit): ?><button type="button" id="btn-new" class="secondary">+ New template</button><?php endif; ?>
        </div>
        <div id="tpl-list" class="tpl-list"><p class="hint">Loading...</p></div>
      </div>

      <div class="card">
        <h2 id="editor-title">Edit template</h2>
        <p class="card-lead">Changes update the live preview. Upload a wide JPG/PNG (<=2MB) for hero-style backgrounds.</p>
        <div class="preview-wrap">
          <img id="preview" alt="Template preview" src="" style="display:none">
          <p id="preview-empty" class="hint" style="margin:0;text-align:center">Select or create a template to preview</p>
        </div>
        <div id="msg" class="msg"></div>

        <form id="editor">
          <input type="hidden" id="id" value="">
          <div class="grid grid-2">
            <div>
              <label for="name">Template name</label>
              <input id="name" required placeholder="Holiday brand 2026" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div>
              <label for="layout_key">Layout style</label>
              <select id="layout_key" <?= $canEdit ? '' : 'disabled' ?>>
                <?php foreach (timer_layout_labels() as $val => $lab): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="grid-column:1/-1">
              <label for="description">Description (optional)</label>
              <input id="description" placeholder="Used for spring campaigns" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div style="grid-column:1/-1">
              <label for="default_label">Default line under countdown</label>
              <input id="default_label" placeholder="Shop now · Free shipping" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
          </div>

          <div class="grid grid-3" style="margin-top:.75rem">
            <div><label for="bg">Fallback color</label><input type="color" id="bg" value="#1a1a2e" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div><label for="fg">Text</label><input type="color" id="fg" value="#eaeaea" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div><label for="ac">Accent</label><input type="color" id="ac" value="#e94560" <?= $canEdit ? '' : 'disabled' ?>></div>
          </div>

          <div class="grid grid-2" style="margin-top:.75rem">
            <div><label for="width">Width (px)</label><input type="number" id="width" min="200" max="600" step="10" value="480" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div><label for="height">Height (px)</label><input type="number" id="height" min="80" max="300" step="10" value="120" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div>
              <label for="font_key">Font</label>
              <select id="font_key" <?= $canEdit ? '' : 'disabled' ?>>
                <?php foreach (timer_font_labels() as $val => $lab): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div><label for="font_size_main">Main size (px)</label><input type="number" id="font_size_main" min="14" max="72" value="32" <?= $canEdit ? '' : 'disabled' ?>></div>
          </div>

          <div class="bg-upload">
            <strong style="font-size:.88rem">Background image</strong>
            <p class="hint">Photo shows behind the countdown. Use overlay so digits stay readable.</p>
            <?php if ($canEdit): ?>
            <input type="file" id="bg_file" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="row">
              <button type="button" class="secondary" id="btn-upload-bg" disabled>Upload image</button>
              <button type="button" class="secondary" id="btn-remove-bg" disabled>Remove image</button>
            </div>
            <?php endif; ?>
            <div class="grid grid-2" style="margin-top:.65rem">
              <div><label for="overlay_color">Overlay color</label><input type="color" id="overlay_color" value="#000000" <?= $canEdit ? '' : 'disabled' ?>></div>
              <div>
                <label for="overlay_opacity">Overlay strength (<span id="overlay_val">35</span>%)</label>
                <input type="range" id="overlay_opacity" min="0" max="100" value="35" <?= $canEdit ? '' : 'disabled' ?>>
              </div>
            </div>
          </div>

          <div style="margin-top:.75rem">
            <label><input type="checkbox" id="is_default" <?= $canEdit ? '' : 'disabled' ?>> Default template for new timers</label>
          </div>

          <?php if ($canEdit): ?>
          <div class="row">
            <button type="submit" id="btn-save">Save template</button>
            <button type="button" class="danger" id="btn-delete" disabled>Delete</button>
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
      if (!text) { el.className = 'msg'; return; }
      el.textContent = text;
      el.className = 'msg show ' + (ok ? 'ok' : 'err');
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
      document.getElementById('btn-delete').disabled = false;
      document.getElementById('btn-remove-bg').disabled = !(t.bg_image_file);
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
      document.getElementById('btn-delete').disabled = true;
      document.getElementById('btn-remove-bg').disabled = true;
      document.getElementById('editor-title').textContent = 'New template';
      document.getElementById('preview').style.display = 'none';
      document.getElementById('preview-empty').style.display = 'block';
      schedulePreview();
    }

    function renderList() {
      const el = document.getElementById('tpl-list');
      if (!templates.length) {
        el.innerHTML = '<p class="hint">No templates yet. Create one to match your brand.</p>';
        return;
      }
      el.innerHTML = templates.map(t => {
        const active = t.id === activeId ? ' active' : '';
        const thumb = PREVIEW + '?id=' + encodeURIComponent(t.id) + '&_=' + Date.now();
        const def = Number(t.is_default) ? '<span class="badge">Default</span> ' : '';
        return '<div class="tpl-card' + active + '" data-id="' + esc(t.id) + '">' +
          '<img src="' + thumb + '" alt="" loading="lazy">' +
          '<div>' + def + '<strong>' + esc(t.name) + '</strong><span>' + esc(t.layout_key) + ' · ' + Number(t.width) + 'Ã—' + Number(t.height) + '</span></div></div>';
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

    load();
  </script>

<script>
  (function(){
    const topBtn = document.getElementById('btn-new-top');
    const mainBtn = document.getElementById('btn-new');
    if (topBtn && mainBtn) topBtn.addEventListener('click', () => mainBtn.click());
  })();
</script>
<?php require __DIR__ . '/include/app_shell_end.php'; ?>
