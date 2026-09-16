<?php

declare(strict_types=1);

/** @var bool $canEdit */
$canEdit = $canEdit ?? true;
$layoutLabels = $layoutLabels ?? timer_layout_labels();
$layoutBlurbs = $layoutBlurbs ?? timer_layout_blurbs();
$fontLabels = timer_font_labels();
$disabled = $canEdit ? '' : 'disabled';
?>
<div class="design-panel space-y-4" id="design-panel-root" data-can-edit="<?= $canEdit ? '1' : '0' ?>">
  <div class="space-y-2">
    <div class="flex items-center justify-between gap-2">
      <h3 class="font-semibold text-sm tracking-wide uppercase text-base-content/50 m-0">Style</h3>
      <button type="button" class="btn btn-ghost btn-xs" id="btn-change-template" <?= $disabled ?>>Change template</button>
    </div>
    <div id="style-carousel" class="style-carousel" hidden>
      <div id="style-carousel-track" class="style-carousel-track"></div>
      <div id="style-carousel-dots" class="style-carousel-dots"></div>
    </div>
    <div class="layout-pick layout-pick-compact" id="layout-pick">
      <?php foreach ($layoutLabels as $val => $lab): ?>
      <label title="<?= htmlspecialchars($layoutBlurbs[$val] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="radio" name="layout_key_radio" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $disabled ?>>
        <strong><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></strong>
      </label>
      <?php endforeach; ?>
    </div>
    <select id="layout_key" class="hidden" <?= $disabled ?>>
      <?php foreach ($layoutLabels as $val => $lab): ?>
      <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="grid grid-cols-3 gap-2">
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="ac"><span class="label-text text-xs">Primary</span></label>
      <div class="hex-swatch">
        <input type="color" id="ac" value="#e94560" class="hex-swatch-color" <?= $disabled ?>>
        <input type="text" id="ac_hex" value="#e94560" class="input input-bordered input-sm font-mono text-xs" maxlength="7" <?= $disabled ?>>
      </div>
    </fieldset>
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="fg"><span class="label-text text-xs">Text</span></label>
      <div class="hex-swatch">
        <input type="color" id="fg" value="#eaeaea" class="hex-swatch-color" <?= $disabled ?>>
        <input type="text" id="fg_hex" value="#eaeaea" class="input input-bordered input-sm font-mono text-xs" maxlength="7" <?= $disabled ?>>
      </div>
    </fieldset>
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="bg"><span class="label-text text-xs">Background</span></label>
      <div class="hex-swatch">
        <input type="color" id="bg" value="#1a1a2e" class="hex-swatch-color" <?= $disabled ?>>
        <input type="text" id="bg_hex" value="#1a1a2e" class="input input-bordered input-sm font-mono text-xs" maxlength="7" <?= $disabled ?>>
      </div>
    </fieldset>
  </div>

  <div class="space-y-2">
    <span class="label-text text-xs font-semibold">Background mode</span>
    <div class="join w-full">
      <input class="btn btn-sm join-item flex-1" type="radio" name="bg_mode" id="bg_mode_solid" value="solid" aria-label="Solid" checked <?= $disabled ?>>
      <input class="btn btn-sm join-item flex-1" type="radio" name="bg_mode" id="bg_mode_transparent" value="transparent" aria-label="Transparent" <?= $disabled ?>>
    </div>
    <p class="text-xs text-base-content/50 m-0">Transparent uses alpha on PNG; GIF falls back to soft white.</p>
  </div>

  <div class="grid gap-2 sm:grid-cols-2">
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="font_size_main"><span class="label-text text-xs">Counter size</span></label>
      <input type="number" id="font_size_main" min="14" max="72" value="32" class="input input-bordered input-sm w-full" <?= $disabled ?>>
    </fieldset>
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="font_key"><span class="label-text text-xs">Counter font</span></label>
      <select id="font_key" class="select select-bordered select-sm w-full" <?= $disabled ?>>
        <?php foreach ($fontLabels as $val => $lab): ?>
        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </fieldset>
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="label_font_size"><span class="label-text text-xs">Label size</span></label>
      <select id="label_font_size" class="select select-bordered select-sm w-full" <?= $disabled ?>>
        <option value="0">Auto</option>
        <?php for ($s = 10; $s <= 28; $s += 2): ?>
        <option value="<?= $s ?>"><?= $s ?>px</option>
        <?php endfor; ?>
      </select>
    </fieldset>
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="label_font_key"><span class="label-text text-xs">Label font</span></label>
      <select id="label_font_key" class="select select-bordered select-sm w-full" <?= $disabled ?>>
        <?php foreach ($fontLabels as $val => $lab): ?>
        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </fieldset>
  </div>

  <div class="grid gap-2 sm:grid-cols-2">
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="width"><span class="label-text text-xs">Width</span></label>
      <input type="number" id="width" min="200" max="600" step="10" value="480" class="input input-bordered input-sm w-full" <?= $disabled ?>>
    </fieldset>
    <fieldset class="fieldset p-0">
      <label class="label py-1" for="height"><span class="label-text text-xs">Height</span></label>
      <input type="number" id="height" min="80" max="300" step="10" value="120" class="input input-bordered input-sm w-full" <?= $disabled ?>>
    </fieldset>
  </div>

  <fieldset class="fieldset p-0">
    <label class="label py-1" for="after_count"><span class="label-text text-xs">After count</span></label>
    <select id="after_count" class="select select-bordered select-sm w-full" <?= $disabled ?>>
      <option value="zeros">Show zeros</option>
      <option value="message" selected>Show expiration message</option>
      <option value="hide">Hide timer</option>
    </select>
  </fieldset>

  <div class="space-y-2">
    <span class="label-text text-xs font-semibold">Time units</span>
    <div class="unit-toggles">
      <?php foreach (['show_days' => 'Days', 'show_hours' => 'Hours', 'show_minutes' => 'Minutes', 'show_seconds' => 'Seconds'] as $id => $lab): ?>
      <label class="unit-toggle">
        <span><?= $lab ?></span>
        <input type="checkbox" id="<?= $id ?>" class="toggle toggle-success toggle-sm" checked <?= $disabled ?>>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="space-y-2" id="design-bg-image-block">
    <div class="flex items-center justify-between">
      <span class="label-text text-xs font-semibold">Background image</span>
      <input type="checkbox" id="use_bg_image" class="toggle toggle-sm" <?= $disabled ?>>
    </div>
    <div id="bg-image-controls" class="space-y-2" hidden>
      <?php if ($canEdit && ($designPanelShowBgUpload ?? true)): ?>
      <input type="file" id="bg_file" accept="image/jpeg,image/png,image/webp,image/gif" class="file-input file-input-bordered file-input-sm w-full">
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline btn-xs" id="btn-upload-bg" disabled>Upload</button>
        <button type="button" class="btn btn-ghost btn-xs" id="btn-remove-bg" disabled>Remove</button>
      </div>
      <?php endif; ?>
      <div class="grid gap-2 sm:grid-cols-2">
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="overlay_color"><span class="label-text text-xs">Overlay</span></label>
          <input type="color" id="overlay_color" value="#000000" class="h-9 w-full cursor-pointer rounded-lg border border-base-300" <?= $disabled ?>>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="overlay_opacity"><span class="label-text text-xs">Opacity (<span id="overlay_val">35</span>%)</span></label>
          <input type="range" id="overlay_opacity" min="0" max="100" value="35" class="range range-primary range-xs" <?= $disabled ?>>
        </fieldset>
      </div>
    </div>
  </div>

  <div class="collapse collapse-arrow border border-base-300 bg-base-100 rounded-box">
    <input type="checkbox" id="advanced-open">
    <div class="collapse-title text-sm font-semibold py-3 min-h-0">Advanced options</div>
    <div class="collapse-content space-y-3">
      <div class="grid gap-3 sm:grid-cols-2">
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="labels_position"><span class="label-text text-xs">Labels position</span></label>
          <input type="range" id="labels_position" min="0" max="100" value="50" class="range range-xs" <?= $disabled ?>>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="section_position"><span class="label-text text-xs">Section position</span></label>
          <input type="range" id="section_position" min="0" max="100" value="50" class="range range-xs" <?= $disabled ?>>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="numbers_spacing"><span class="label-text text-xs">Numbers spacing</span></label>
          <input type="range" id="numbers_spacing" min="0" max="100" value="50" class="range range-xs" <?= $disabled ?>>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="separator_style"><span class="label-text text-xs">Separator style</span></label>
          <select id="separator_style" class="select select-bordered select-sm w-full" <?= $disabled ?>>
            <option value="colon">Colon</option>
            <option value="dots">Dots</option>
            <option value="none">None</option>
          </select>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="separator_size"><span class="label-text text-xs">Separator size</span></label>
          <input type="range" id="separator_size" min="0" max="100" value="50" class="range range-xs" <?= $disabled ?>>
        </fieldset>
        <fieldset class="fieldset p-0">
          <label class="label py-1" for="labels_color"><span class="label-text text-xs">Labels color</span></label>
          <input type="color" id="labels_color" value="#9ca3af" class="h-9 w-full cursor-pointer rounded-lg border border-base-300" <?= $disabled ?>>
        </fieldset>
        <fieldset class="fieldset p-0 sm:col-span-2">
          <label class="label py-1" for="separator_color"><span class="label-text text-xs">Separator color</span></label>
          <input type="color" id="separator_color" value="#4275bc" class="h-9 w-full cursor-pointer rounded-lg border border-base-300" <?= $disabled ?>>
        </fieldset>
      </div>
    </div>
  </div>
</div>
