/**
 * Shared design form helpers for timers.php and templates.php.
 * Expects DOM ids from include/timer_design_panel.php.
 */
window.TimerDesignForm = (function () {
  function $(id) {
    return document.getElementById(id);
  }

  function syncHex(colorId, hexId) {
    const c = $(colorId);
    const h = $(hexId);
    if (!c || !h) return;
    const apply = () => {
      let v = h.value.trim();
      if (!v.startsWith('#')) v = '#' + v;
      if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        c.value = v.toLowerCase();
        h.value = v.toLowerCase();
      }
    };
    c.addEventListener('input', () => {
      h.value = c.value;
    });
    h.addEventListener('change', apply);
    h.addEventListener('blur', apply);
  }

  function readDesign() {
    const bgMode = document.querySelector('input[name="bg_mode"]:checked');
    return {
      bg_mode: bgMode ? bgMode.value : 'solid',
      show_days: !!$('show_days')?.checked,
      show_hours: !!$('show_hours')?.checked,
      show_minutes: !!$('show_minutes')?.checked,
      show_seconds: !!$('show_seconds')?.checked,
      after_count: $('after_count')?.value || 'message',
      label_font_key: $('label_font_key')?.value || 'open_sans',
      label_font_size: parseInt($('label_font_size')?.value || '0', 10) || 0,
      labels_color: $('labels_color')?.value || '',
      separator_color: $('separator_color')?.value || '',
      numbers_spacing: parseInt($('numbers_spacing')?.value || '50', 10),
      separator_style: $('separator_style')?.value || 'colon',
      separator_size: parseInt($('separator_size')?.value || '50', 10),
      labels_position: parseInt($('labels_position')?.value || '50', 10),
      section_position: parseInt($('section_position')?.value || '50', 10),
    };
  }

  function writeDesign(d) {
    d = d || {};
    const mode = d.bg_mode === 'transparent' ? 'transparent' : 'solid';
    const solid = $('bg_mode_solid');
    const tr = $('bg_mode_transparent');
    if (solid && tr) {
      solid.checked = mode === 'solid';
      tr.checked = mode === 'transparent';
    }
    ['show_days', 'show_hours', 'show_minutes', 'show_seconds'].forEach((k) => {
      if ($(k)) $(k).checked = d[k] !== false;
    });
    if ($('after_count')) $('after_count').value = d.after_count || 'message';
    if ($('label_font_key')) $('label_font_key').value = d.label_font_key || 'open_sans';
    if ($('label_font_size')) $('label_font_size').value = String(d.label_font_size || 0);
    if ($('labels_color') && d.labels_color) $('labels_color').value = d.labels_color;
    if ($('separator_color') && d.separator_color) $('separator_color').value = d.separator_color;
    ['numbers_spacing', 'separator_size', 'labels_position', 'section_position'].forEach((k) => {
      if ($(k) && d[k] != null) $(k).value = String(d[k]);
    });
    if ($('separator_style')) $('separator_style').value = d.separator_style || 'colon';
    updateBgModeUi();
  }

  function updateBgModeUi() {
    const tr = $('bg_mode_transparent')?.checked;
    if ($('bg')) $('bg').disabled = !!tr;
    if ($('bg_hex')) $('bg_hex').disabled = !!tr;
  }

  function readStyleFields() {
    return {
      bg_color: $('bg')?.value || '#1a1a2e',
      text_color: $('fg')?.value || '#eaeaea',
      accent_color: $('ac')?.value || '#e94560',
      width: parseInt($('width')?.value || '480', 10),
      height: parseInt($('height')?.value || '120', 10),
      font_key: $('font_key')?.value || 'noto_sans_bold',
      font_size_main: parseInt($('font_size_main')?.value || '32', 10),
      layout_key: $('layout_key')?.value || 'segmented_pills',
      bg_overlay_color: $('overlay_color')?.value || '#000000',
      bg_overlay_opacity: parseInt($('overlay_opacity')?.value || '0', 10),
      design: readDesign(),
    };
  }

  function writeStyleFields(s) {
    s = s || {};
    if ($('bg')) {
      $('bg').value = s.bg_color || '#1a1a2e';
      if ($('bg_hex')) $('bg_hex').value = $('bg').value;
    }
    if ($('fg')) {
      $('fg').value = s.text_color || '#eaeaea';
      if ($('fg_hex')) $('fg_hex').value = $('fg').value;
    }
    if ($('ac')) {
      $('ac').value = s.accent_color || '#e94560';
      if ($('ac_hex')) $('ac_hex').value = $('ac').value;
    }
    if ($('width')) $('width').value = String(s.width || 480);
    if ($('height')) $('height').value = String(s.height || 120);
    if ($('font_key')) $('font_key').value = s.font_key || 'noto_sans_bold';
    if ($('font_size_main')) $('font_size_main').value = String(s.font_size_main || 32);
    if ($('layout_key')) $('layout_key').value = s.layout_key || 'segmented_pills';
    document.querySelectorAll('#layout-pick input[type=radio]').forEach((r) => {
      r.checked = r.value === (s.layout_key || 'segmented_pills');
    });
    if ($('overlay_color')) $('overlay_color').value = s.bg_overlay_color || '#000000';
    if ($('overlay_opacity')) {
      $('overlay_opacity').value = String(s.bg_overlay_opacity ?? 35);
      if ($('overlay_val')) $('overlay_val').textContent = String(s.bg_overlay_opacity ?? 35);
    }
    writeDesign(s.design || {});
    const hasBg = !!(s.bg_image_file);
    if ($('use_bg_image')) $('use_bg_image').checked = hasBg;
    if ($('bg-image-controls')) $('bg-image-controls').hidden = !hasBg;
  }

  function bindLayoutPick() {
    document.querySelectorAll('#layout-pick input[type=radio]').forEach((r) => {
      r.addEventListener('change', () => {
        if (r.checked && $('layout_key')) {
          $('layout_key').value = r.value;
          $('layout_key').dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });
  }

  function bindChrome(onChange) {
    syncHex('bg', 'bg_hex');
    syncHex('fg', 'fg_hex');
    syncHex('ac', 'ac_hex');
    bindLayoutPick();
    document.querySelectorAll('input[name="bg_mode"]').forEach((el) => {
      el.addEventListener('change', () => {
        updateBgModeUi();
        onChange && onChange();
      });
    });
    if ($('use_bg_image')) {
      $('use_bg_image').addEventListener('change', () => {
        if ($('bg-image-controls')) $('bg-image-controls').hidden = !$('use_bg_image').checked;
        onChange && onChange();
      });
    }
    if ($('overlay_opacity')) {
      $('overlay_opacity').addEventListener('input', () => {
        if ($('overlay_val')) $('overlay_val').textContent = $('overlay_opacity').value;
      });
    }
    const root = $('design-panel-root');
    if (root && onChange) {
      root.addEventListener('input', onChange);
      root.addEventListener('change', onChange);
    }
  }

  async function refreshPreview(imgEl, metaEl, payload, previewUrl) {
    if (!imgEl) return;
    const res = await fetch(previewUrl || 'api/template_preview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Preview failed');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const prev = imgEl.dataset.blobUrl;
    imgEl.src = url;
    imgEl.style.display = 'block';
    imgEl.dataset.blobUrl = url;
    if (prev) URL.revokeObjectURL(prev);
    if (metaEl) {
      const w = payload.width || 480;
      const h = payload.height || 120;
      metaEl.textContent = 'Image size: ' + w + ' × ' + h + ' pixels';
    }
  }

  return {
    readDesign,
    writeDesign,
    readStyleFields,
    writeStyleFields,
    bindChrome,
    refreshPreview,
    updateBgModeUi,
  };
})();
