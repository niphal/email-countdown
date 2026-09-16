<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_login_redirect();

$cu = auth_current_user();
$canEdit = auth_has_min_role(AUTH_ROLE_EDITOR);
$workspaceName = 'Workspace';
if ($cu !== null) {
    $wst = db()->prepare('SELECT name FROM workspaces WHERE id = ?');
    $wst->execute([(int) $cu['workspace_id']]);
    $wn = $wst->fetchColumn();
    if ($wn !== false) {
        $workspaceName = (string) $wn;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Brand templates — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#fff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --ring:rgba(0,66,37,.18); }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%); color: var(--text); font-family: var(--font-body); }
    .wrap { max-width: 1180px; margin: 0 auto; padding: 2.4rem 1.35rem 3rem; }
    h1 { font-family: var(--font-display); font-size: 1.95rem; margin: 0 0 .25rem; letter-spacing: -.02em; }
    .pill { font-size: .82rem; color: var(--muted); font-family: var(--font-mono); }
    .menu { display: flex; gap: .5rem; flex-wrap: wrap; margin: 1rem 0 1.2rem; }
    .menu a { color: var(--text); text-decoration: none; border: 1px solid var(--border); border-radius: 999px; padding: .4rem .78rem; font-size: .82rem; font-weight: 600; }
    .menu a.active { border-color: var(--accent); color: var(--accent); background: #f5fbf7; }
    .layout { display: grid; gap: 1rem; }
    @media (min-width: 960px) { .layout { grid-template-columns: 300px 1fr; align-items: start; } }
    .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1rem 1.15rem; box-shadow: 0 6px 18px rgba(17,24,39,.06); }
    .panel h2 { margin: 0 0 .5rem; font-size: 1rem; }
    .lead { color: var(--muted); font-size: .86rem; margin: 0 0 .85rem; line-height: 1.45; }
    .tpl-list { display: flex; flex-direction: column; gap: .55rem; max-height: 520px; overflow-y: auto; }
    .tpl-card { border: 1px solid var(--border); border-radius: 10px; padding: .55rem; cursor: pointer; background: #fbfcfb; display: grid; grid-template-columns: 72px 1fr; gap: .55rem; align-items: center; }
    .tpl-card.active { border-color: var(--accent); background: #f5fbf7; }
    .tpl-card img { width: 72px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); background: #0f1720; }
    .tpl-card strong { display: block; font-size: .88rem; }
    .tpl-card span { font-size: .75rem; color: var(--muted); }
    .badge { font-size: .68rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: .04em; }
    label { display: block; font-size: .78rem; color: var(--muted); font-weight: 600; margin: 0 0 .35rem; }
    input, select, textarea { width: 100%; padding: .52rem .58rem; border: 1px solid var(--border); border-radius: 8px; font: inherit; }
    input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--ring); outline: none; }
    .grid { display: grid; gap: .75rem; }
    @media (min-width: 640px) { .grid-2 { grid-template-columns: 1fr 1fr; } .grid-3 { grid-template-columns: repeat(3,1fr); } }
    .row { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .85rem; align-items: center; }
    button { padding: .55rem .85rem; border: none; border-radius: 10px; background: linear-gradient(135deg,var(--accent),var(--accent-dim)); color: #fff; font-weight: 600; cursor: pointer; }
    button.secondary { background: #fff; color: var(--text); border: 1px solid var(--border); }
    button.danger { background: #fff; color: #9b1c1c; border: 1px solid #efcaca; }
    button:disabled { opacity: .5; cursor: not-allowed; }
    .preview-wrap { border: 1px solid var(--border); border-radius: 10px; padding: .75rem; background: #f8faf9; margin-bottom: .85rem; }
    .preview-wrap img { max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 0 auto; }
    .msg { font-size: .85rem; margin: .5rem 0 0; padding: .6rem .75rem; border-radius: 8px; display: none; }
    .msg.show { display: block; }
    .msg.ok { background: #f5fbf7; border: 1px solid #b7d7c4; }
    .msg.err { background: rgba(185,28,28,.08); border: 1px solid #efcaca; }
    .bg-upload { border: 1px dashed var(--border); border-radius: 10px; padding: .75rem; background: #fbfcfb; margin-top: .5rem; }
    .hint { font-size: .75rem; color: var(--muted); margin-top: .25rem; }
    input[type="range"] { padding: 0; }
    input[type="color"] { height: 40px; padding: .2rem; cursor: pointer; }
  </style>
</head>
<body>
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
      <div>
        <h1>Brand templates</h1>
        <div class="pill">Workspace: <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <a href="logout.php" class="pill">Log out</a>
    </div>
    <div class="menu">
      <a href="index.php">Dashboard</a>
      <?php if (auth_has_min_role(AUTH_ROLE_ADMIN)): ?><a href="admin.php">Admin</a><?php endif; ?>
      <?php if (auth_has_min_role(AUTH_ROLE_ADMIN)): ?><a href="integrations.php">Integrations</a><?php endif; ?>
      <a href="templates.php" class="active">Templates</a>
    </div>

    <p class="lead" style="margin-bottom:1rem">Save reusable brand styles—colors, layout, optional background photo with overlay—then pick a template when creating timers on the dashboard.</p>

    <div class="layout">
      <div class="panel">
        <h2>Your templates</h2>
        <div class="row" style="margin-top:0;margin-bottom:.65rem">
          <?php if ($canEdit): ?><button type="button" id="btn-new" class="secondary">+ New template</button><?php endif; ?>
        </div>
        <div id="tpl-list" class="tpl-list"><p class="hint">Loading…</p></div>
      </div>

      <div class="panel">
        <h2 id="editor-title">Edit template</h2>
        <p class="lead">Changes update the live preview. Upload a wide JPG/PNG (≤2MB) for hero-style backgrounds.</p>
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
          '<div>' + def + '<strong>' + esc(t.name) + '</strong><span>' + esc(t.layout_key) + ' · ' + Number(t.width) + '×' + Number(t.height) + '</span></div></div>';
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
</body>
</html>
