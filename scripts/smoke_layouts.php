<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/timer_fonts.php';
require dirname(__DIR__) . '/lib/timer_layouts.php';
require dirname(__DIR__) . '/lib/timer_background.php';
require dirname(__DIR__) . '/lib/timer_design.php';
require dirname(__DIR__) . '/timer.php';
require dirname(__DIR__) . '/lib/timer_template_presets.php';

$presets = timer_template_presets();
echo count($presets) . " presets\n";
echo count(timer_layout_keys()) . " layouts\n";

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
$design = timer_design_defaults();

foreach (timer_layout_keys() as $layout) {
    $im = render_timer_frame(
        560, 148, [11, 18, 32], [232, 238, 247], [37, 99, 235],
        'Smoke test', 90000, $font, 36, $layout, $created, $ends,
        'Fri', true, '', '#000000', 0, null, false, $design, $font
    );
    imagepng($im, $outDir . '/_layout_' . $layout . '.png');
    imagedestroy($im);
    echo $layout . " ok\n";
}

$designHidden = timer_design_sanitize(['show_days' => false, 'show_hours' => true, 'bg_mode' => 'transparent', 'after_count' => 'zeros']);
$im = render_timer_frame(
    480, 120, [248, 250, 252], [15, 23, 42], [37, 99, 235],
    'Hidden days', 0, $font, 34, 'digit_plain', $created, $ends,
    '', true, '', '#000000', 0, null, false, $designHidden, $font
);
imagepng($im, $outDir . '/_design_combo.png');
imagedestroy($im);
echo "design combo ok\n";

db(); // migrate design_json
echo "schema migrate ok\n";
echo "done\n";
