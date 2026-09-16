<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/timer_fonts.php';
require dirname(__DIR__) . '/lib/timer_layouts.php';
require dirname(__DIR__) . '/lib/timer_background.php';
require dirname(__DIR__) . '/timer.php';
require dirname(__DIR__) . '/lib/timer_template_presets.php';

$presets = timer_template_presets();
echo count($presets) . " presets\n";

$outDir = dirname(__DIR__) . '/data/render_cache';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$font = timer_ensure_ttf_path('noto_sans_bold');
if ($font === null) {
    fwrite(STDERR, "Font missing\n");
    exit(1);
}

$now = time();
$ends = $now + 90000;
$created = $now - 86400;

foreach (timer_layout_keys() as $layout) {
    $im = render_timer_frame(
        560,
        148,
        [11, 18, 32],
        [232, 238, 247],
        [255, 77, 109],
        'Smoke test',
        90000,
        $font,
        36,
        $layout,
        $created,
        $ends,
        'd',
        true,
        '',
        '#000000',
        0,
        null,
        false
    );
    imagepng($im, $outDir . '/_layout_' . $layout . '.png');
    imagedestroy($im);
    echo $layout . " ok\n";
}

foreach ($presets as $key => $preset) {
    $bg = parse_hex((string) ($preset['bg_color'] ?? '#0f172a'));
    $fg = parse_hex((string) ($preset['text_color'] ?? '#ffffff'));
    $ac = parse_hex((string) ($preset['accent_color'] ?? '#38bdf8'));
    $layout = (string) ($preset['layout_key'] ?? 'segmented_pills');
    $w = (int) ($preset['width'] ?? 520);
    $h = (int) ($preset['height'] ?? 130);
    $fontKey = (string) ($preset['font_key'] ?? 'noto_sans_bold');
    $fontPath = timer_ensure_ttf_path($fontKey) ?: $font;
    $fs = (int) ($preset['font_size_main'] ?? 36);
    $label = (string) ($preset['default_label'] ?? $preset['name']);
    $im = render_timer_frame(
        $w,
        $h,
        $bg,
        $fg,
        $ac,
        $label,
        90000,
        $fontPath,
        $fs,
        $layout,
        $created,
        $ends,
        'Fri 5:00 PM',
        true,
        '',
        (string) ($preset['bg_overlay_color'] ?? '#000000'),
        (int) ($preset['bg_overlay_opacity'] ?? 0),
        null,
        false
    );
    imagepng($im, $outDir . '/_preset_' . $key . '.png');
    imagedestroy($im);
    echo $key . " (" . $layout . ") ok\n";
}

echo "done\n";
