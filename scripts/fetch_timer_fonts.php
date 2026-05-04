<?php

declare(strict_types=1);

/**
 * CLI: prefetch all timer TTF files into data/fonts/ (same as install warm-up).
 * Usage: php scripts/fetch_timer_fonts.php
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/timer_fonts.php';

timer_font_warm_all();

foreach (timer_font_keys() as $key) {
    $p = timer_ensure_ttf_path($key);
    fwrite(STDOUT, $key . ': ' . ($p !== null ? 'ok' : 'FAILED') . "\n");
}
