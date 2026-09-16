<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/timer_fonts.php';
require dirname(__DIR__) . '/lib/timer_layouts.php';
require dirname(__DIR__) . '/lib/timer_background.php';
require dirname(__DIR__) . '/lib/timer_design.php';
require dirname(__DIR__) . '/timer.php';

$f = timer_ensure_ttf_path('noto_sans_bold');
$d = timer_design_defaults();
$now = time();
$rem = 2 * 86400 + 5 * 3600 + 12 * 60 + 34;
$out = dirname(__DIR__) . '/data/render_cache';

foreach (['ring_arc', 'ring_wedge'] as $layout) {
    $im = render_timer_frame(
        560, 180, [255, 255, 255], [30, 30, 40], [232, 74, 95],
        'Countdown', $rem, $f, 36, $layout, $now - 86400, $now + 200000,
        'Ends 18 Sep 2026 15:37 UTC', true, '', '#000', 0, null, false, $d, $f
    );
    imagepng($im, $out . '/_aa_' . $layout . '.png');
    imagedestroy($im);
    echo $layout . " ok\n";
}
