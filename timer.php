<?php

declare(strict_types=1);

/** ~10 one-second frames: still animates in email clients without 5s+ encode cost. */
const TIMER_ANIMATION_FRAMES = 10;
const TIMER_FRAME_DELAY_CS = 100;
/** More colors keep rounded cards readable after GIF quantization. */
const TIMER_GIF_COLORS = 96;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';
require_once __DIR__ . '/lib/timer_background.php';
require_once __DIR__ . '/lib/timer_render_cache.php';

if (!function_exists('imagecreatetruecolor')) {
    observability_log('timer.render.gd_missing', 'error');
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP GD is not enabled. In php.ini set extension=gd, then restart PHP or your web server.';
    exit;
}

function timer_serve_image_request(): void
{
$id = $_GET['id'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
    observability_log('timer.render.bad_id', 'warning');
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Bad id';
    exit;
}

/** Optional Braze/Liquid: override end unix time via query string when image is requested */
$overrideEnd = isset($_GET['end']) ? (int) $_GET['end'] : null;
if ($overrideEnd !== null && $overrideEnd <= 0) {
    $overrideEnd = null;
}
if ($overrideEnd !== null) {
    $key = app_timer_signing_key();
    if ($key !== '' && !app_timer_has_valid_signature($id, $_GET['sig'] ?? null)) {
        observability_log('timer.render.invalid_dynamic_signature', 'warning', ['timer_id' => $id]);
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Dynamic end override requires a valid sig parameter.';
        exit;
    }
}

$stmt = db()->prepare('SELECT name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, created_at, bg_image_file, bg_overlay_color, bg_overlay_opacity FROM timers WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    observability_log('timer.render.not_found', 'warning', ['timer_id' => $id]);
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}

$storedEndsAt = (int) $row['ends_at'];
$endsAt = $overrideEnd ?? $storedEndsAt;
$createdAt = (int) ($row['created_at'] ?? time());
$w = (int) $row['width'];
$h = (int) $row['height'];
$bg = parse_hex($row['bg_color']);
$fg = parse_hex($row['text_color']);
$ac = parse_hex($row['accent_color']);
$label = (string) $row['label'];
$fontKey = timer_normalize_font_key((string) ($row['font_key'] ?? 'noto_sans_bold'));
$layoutKey = timer_normalize_layout_key((string) ($row['layout_key'] ?? 'segmented_pills'));
$fontSizeMain = (int) ($row['font_size_main'] ?? 32);
if ($fontSizeMain < 14) {
    $fontSizeMain = 14;
}
if ($fontSizeMain > 72) {
    $fontSizeMain = 72;
}
$bgImageFile = (string) ($row['bg_image_file'] ?? '');
$bgOverlayColor = (string) ($row['bg_overlay_color'] ?? '#000000');
$bgOverlayOpacity = (int) ($row['bg_overlay_opacity'] ?? 0);

if (!timer_gd_has_freetype()) {
    observability_log('timer.render.freetype_missing', 'error', ['timer_id' => $id]);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP GD is missing FreeType support, so TrueType fonts cannot render. Install a PHP build where GD is linked with FreeType (often shown as "FreeType Support => enabled" in phpinfo).';
    exit;
}

$fontPath = timer_ensure_ttf_path($fontKey);
if ($fontPath === null) {
    observability_log('timer.render.font_unavailable', 'error', ['timer_id' => $id, 'font_key' => $fontKey]);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Timer fonts could not be downloaded. Ensure the data/fonts directory is writable and that PHP can make outbound HTTPS requests (curl extension or allow_url_fopen). Fonts are fetched once from Google Fonts open-source repositories.';
    exit;
}

$format = strtolower((string) ($_GET['format'] ?? 'gif'));
if ($format !== 'png' && $format !== 'gif') {
    $format = 'gif';
}
$renderStart = microtime(true);
$t0 = time();
$deadlineLabel = app_format_deadline_label($endsAt);
$cacheKey = timer_render_cache_key($id, $format, $endsAt, $overrideEnd, $row);
$cached = timer_render_cache_get($cacheKey, 25);

// Hint freshness to intermediate caches. Gmail's image proxy may still serve a stale copy on re-open.
header('Cache-Control: public, max-age=30, s-maxage=30');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 30) . ' GMT');
header('Pragma: no-cache');

if ($cached !== null) {
    header('Content-Type: ' . ($format === 'png' ? 'image/png' : 'image/gif'));
    header('X-Timer-Cache: hit');
    echo $cached;
    timer_observe_render_success($id, $format, $layoutKey, (int) round((microtime(true) - $renderStart) * 1000), [
        'cache' => 'hit',
    ]);
    exit;
}

$frames = [];
$binary = null;
$bgCanvas = null;
$buildMs = 0;
$encodeMs = 0;
try {
    $bgCanvas = imagecreatetruecolor($w, $h);
    if ($bgCanvas === false) {
        throw new RuntimeException('Could not allocate canvas');
    }
    timer_paint_canvas_background($bgCanvas, $w, $h, $bg, $bgImageFile, $bgOverlayColor, $bgOverlayOpacity);

    $buildStart = microtime(true);
    $quantize = ($format === 'gif');
    for ($k = 0; $k < TIMER_ANIMATION_FRAMES; $k++) {
        $remaining = max(0, $endsAt - ($t0 + $k));
        // First frame includes the deadline for clients that only show frame 1.
        $frames[] = render_timer_frame(
            $w,
            $h,
            $bg,
            $fg,
            $ac,
            $label,
            $remaining,
            $fontPath,
            $fontSizeMain,
            $layoutKey,
            $createdAt,
            $storedEndsAt,
            $deadlineLabel,
            $k === 0,
            $bgImageFile,
            $bgOverlayColor,
            $bgOverlayOpacity,
            $bgCanvas,
            $quantize
        );
    }
    $buildMs = (int) round((microtime(true) - $buildStart) * 1000);
    $durations = array_fill(0, TIMER_ANIMATION_FRAMES, TIMER_FRAME_DELAY_CS);

    $encodeStart = microtime(true);
    if ($format === 'png') {
        require_once __DIR__ . '/lib/ApngCreator.php';
        $binary = (new ApngCreator())->create($frames, $durations, 1);
        header('Content-Type: image/png');
    } else {
        require_once __DIR__ . '/lib/GifCreator.php';
        $creator = new GifCreator();
        $creator->setOmitNetscapeLoop(true);
        $binary = $creator->create($frames, $durations, 0);
        header('Content-Type: image/gif');
    }
    $encodeMs = (int) round((microtime(true) - $encodeStart) * 1000);
    header('X-Timer-Cache: miss');
    timer_render_cache_put($cacheKey, $binary);
} catch (Throwable $e) {
    observability_log('timer.render.failed', 'error', ['timer_id' => $id, 'format' => $format, 'error' => $e->getMessage()]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not build animated timer.';
    exit;
} finally {
    if ($bgCanvas instanceof \GdImage) {
        imagedestroy($bgCanvas);
    }
    foreach ($frames as $im) {
        if ($im instanceof \GdImage || (is_resource($im) && get_resource_type($im) === 'gd')) {
            imagedestroy($im);
        }
    }
}

echo $binary;
timer_observe_render_success($id, $format, $layoutKey, (int) round((microtime(true) - $renderStart) * 1000), [
    'cache' => 'miss',
    'build_ms' => $buildMs,
    'encode_ms' => $encodeMs,
    'frames' => TIMER_ANIMATION_FRAMES,
]);
}

/**
 * @return array{0:int,1:int,2:int}
 */
function parse_hex(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return [26, 26, 46];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function timer_observe_render_success(string $id, string $format, string $layoutKey, int $durationMs, array $extra = []): void
{
    $slowThreshold = 750;
    $fields = array_merge([
        'timer_id' => $id,
        'format' => $format,
        'layout' => $layoutKey,
        'duration_ms' => $durationMs,
    ], $extra);
    if ($durationMs >= $slowThreshold) {
        observability_log('timer.render.slow', 'warning', $fields);

        return;
    }
    $sampleRate = observability_config_float('timer_render_success_sample_rate', 0.02, 0.0, 1.0);
    if (!observability_should_sample($sampleRate)) {
        return;
    }
    observability_log('timer.render.success_sampled', 'info', $fields);
}

/**
 * @param array{0:int,1:int,2:int} $bg
 * @param array{0:int,1:int,2:int} $fg
 * @param array{0:int,1:int,2:int} $ac
 */
function render_timer_frame(
    int $w,
    int $h,
    array $bg,
    array $fg,
    array $ac,
    string $label,
    int $remaining,
    string $fontPath,
    int $fontSizeMain,
    string $layoutKey,
    int $createdAt,
    int $storedEndsAt,
    string $deadlineLabel = '',
    bool $firstFrame = false,
    string $bgImageFile = '',
    string $bgOverlayColor = '#000000',
    int $bgOverlayOpacity = 0,
    ?\GdImage $bgCanvas = null,
    bool $quantizeForGif = true
): \GdImage {
    $im = imagecreatetruecolor($w, $h);
    if ($bgCanvas instanceof \GdImage) {
        imagecopy($im, $bgCanvas, 0, 0, 0, 0, $w, $h);
    } else {
        timer_paint_canvas_background($im, $w, $h, $bg, $bgImageFile, $bgOverlayColor, $bgOverlayOpacity);
    }
    $colFg = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
    $colAc = imagecolorallocate($im, $ac[0], $ac[1], $ac[2]);
    $darkCanvas = timer_luma($bg) < 150;
    $mutedRgb = timer_mix_rgb($fg, $bg, 0.38);
    // Light canvases need a soft dark inset; dark canvases lift toward white.
    $panelRgb = $darkCanvas
        ? timer_mix_rgb(timer_mix_rgb($bg, [255, 255, 255], 0.16), $ac, 0.08)
        : timer_mix_rgb(timer_mix_rgb($bg, [0, 0, 0], 0.055), $ac, 0.04);
    $panelHiRgb = $darkCanvas
        ? timer_mix_rgb($panelRgb, [255, 255, 255], 0.18)
        : timer_mix_rgb($panelRgb, [255, 255, 255], 0.55);
    $ruleRgb = timer_mix_rgb($fg, $bg, 0.58);
    $inkOnAc = timer_luma($ac) > 160 ? [20, 20, 24] : [255, 255, 255];
    $colMuted = imagecolorallocate($im, $mutedRgb[0], $mutedRgb[1], $mutedRgb[2]);
    $colPanel = imagecolorallocate($im, $panelRgb[0], $panelRgb[1], $panelRgb[2]);
    $colPanelHi = imagecolorallocate($im, $panelHiRgb[0], $panelHiRgb[1], $panelHiRgb[2]);
    $colRule = imagecolorallocate($im, $ruleRgb[0], $ruleRgb[1], $ruleRgb[2]);
    $colBg = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
    $colInkOnAc = imagecolorallocate($im, $inkOnAc[0], $inkOnAc[1], $inkOnAc[2]);
    $acSoftRgb = timer_mix_rgb($ac, $bg, 0.32);
    $colAcSoft = imagecolorallocate($im, $acSoftRgb[0], $acSoftRgb[1], $acSoftRgb[2]);

    [$days, $hh, $mm, $ss] = timer_split_remaining($remaining);
    $mainLine = sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
    $daysLine = sprintf('%02d', $days);
    $ended = $remaining <= 0;
    if ($ended) {
        $sub = 'Offer ended';
    } elseif ($firstFrame && $deadlineLabel !== '') {
        // Outlook desktop freezes on frame 1 — keep deadline readable without animation.
        $sub = $label !== '' ? ($label . ' · ' . $deadlineLabel) : $deadlineLabel;
    } else {
        $sub = $label !== '' ? $label : ($deadlineLabel !== '' ? $deadlineLabel : 'Time remaining');
    }
    $progress = timer_progress_ratio($remaining, $createdAt, $storedEndsAt);

    $fontSizeMain = (int) max(10, min(72, $fontSizeMain, (int) ($h * 0.52)));
    $fontSizeSub = (int) max(8, min(40, (int) round($fontSizeMain * 0.42)));
    $fontSizeUnit = (int) max(8, (int) round($fontSizeSub * 0.72));
    $hasTtf = is_readable($fontPath) && function_exists('imagettfbbox');

    if ($ended && $hasTtf) {
        timer_draw_soft_card($im, (int) ($w * 0.16), (int) ($h * 0.18), (int) ($w * 0.84), (int) ($h * 0.82), 16, $colPanel, $colPanelHi);
        draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 0.72), 'OFFER ENDED', $colAc, $w / 2, $h * 0.42);
        draw_ttf_centered($im, $fontPath, $fontSizeSub, $deadlineLabel !== '' ? $deadlineLabel : 'This offer has expired', $colFg, $w / 2, $h * 0.66);
    } elseif ($hasTtf && $layoutKey === 'segmented_pills') {
        timer_draw_segmented_pills($im, $fontPath, $colPanel, $colPanelHi, $colAc, $colMuted, $colFg, $days, $hh, $mm, $ss, $fontSizeMain, $fontSizeUnit, $sub);
    } elseif ($hasTtf && $layoutKey === 'split_emphasis') {
        timer_draw_split_emphasis($im, $fontPath, $colPanel, $colPanelHi, $colAc, $colMuted, $colFg, $mainLine, $daysLine, $sub, $fontSizeMain, $fontSizeSub, $fontSizeUnit);
    } elseif ($hasTtf && $layoutKey === 'minimal_editorial') {
        timer_draw_minimal_editorial($im, $fontPath, $colAc, $colMuted, $colFg, $colRule, $hh, $mm, $ss, $daysLine, $sub, $fontSizeMain, $fontSizeSub, $fontSizeUnit);
    } elseif ($hasTtf && $layoutKey === 'progress_hybrid') {
        timer_draw_progress_hybrid($im, $fontPath, $colPanel, $colAc, $colAcSoft, $colMuted, $colFg, $mainLine, $daysLine, $sub, $progress, $fontSizeMain, $fontSizeSub, $fontSizeUnit);
    } elseif ($hasTtf && $layoutKey === 'badge_countdown') {
        timer_draw_badge_countdown($im, $fontPath, $colAc, $colInkOnAc, $colFg, $colMuted, $colPanel, $colPanelHi, $hh, $mm, $ss, $daysLine, $sub, $fontSizeMain, $fontSizeSub, $fontSizeUnit);
    } elseif ($hasTtf && $layoutKey === 'cinema_marquee') {
        timer_draw_cinema_marquee($im, $fontPath, $colAc, $colMuted, $colFg, $colRule, $colPanel, $mainLine, $daysLine, $sub, $fontSizeMain, $fontSizeSub, $fontSizeUnit);
    } elseif ($hasTtf && $layoutKey === 'duo_blocks') {
        timer_draw_duo_blocks($im, $fontPath, $colPanel, $colPanelHi, $colAc, $colMuted, $colFg, $daysLine, $mainLine, $sub, $fontSizeMain, $fontSizeSub, $fontSizeUnit);
    } else {
        $y1 = (int) ($h / 2 - imagefontheight(5));
        imagestring_centered($im, 5, $ended ? 'ENDED' : sprintf('%02d:%02d:%02d', $hh, $mm, $ss), $colAc, $y1);
        $y2 = (int) ($h / 2 + 8);
        imagestring_centered($im, 3, $sub, $colFg, $y2);
    }

    // Palette conversion is GIF-only — APNG keeps truecolor and avoids this cost.
    if ($quantizeForGif) {
        imagetruecolortopalette($im, false, TIMER_GIF_COLORS);
    }

    return $im;
}

/**
 * @param array{0:int,1:int,2:int} $a
 * @param array{0:int,1:int,2:int} $b
 * @return array{0:int,1:int,2:int}
 */
function timer_mix_rgb(array $a, array $b, float $t): array
{
    $t = max(0.0, min(1.0, $t));

    return [
        (int) round($a[0] + ($b[0] - $a[0]) * $t),
        (int) round($a[1] + ($b[1] - $a[1]) * $t),
        (int) round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

/** @param array{0:int,1:int,2:int} $rgb */
function timer_luma(array $rgb): float
{
    return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
}

/**
 * @param \GdImage|resource $im
 */
function timer_fill_rounded_rect($im, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    if ($x2 <= $x1 || $y2 <= $y1) {
        return;
    }
    $radius = max(0, min($radius, (int) floor(($x2 - $x1) / 2), (int) floor(($y2 - $y1) / 2)));
    if ($radius < 1) {
        imagefilledrectangle($im, $x1, $y1, $x2, $y2, $color);

        return;
    }
    imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

/**
 * Soft vertical lift on solid backgrounds (skipped when a photo is present).
 *
 * @param \GdImage $im
 * @param array{0:int,1:int,2:int} $fallbackRgb
 */
function timer_paint_canvas_atmosphere(\GdImage $im, int $w, int $h, array $fallbackRgb, bool $hasPhoto): void
{
    if ($hasPhoto) {
        return;
    }
    $steps = max(12, (int) round($h / 4));
    $dark = timer_luma($fallbackRgb) < 150;
    for ($i = 0; $i < $steps; $i++) {
        $t = $i / max(1, $steps - 1);
        if ($dark) {
            $mix = timer_mix_rgb($fallbackRgb, [255, 255, 255], 0.14 * (1 - $t));
        } else {
            $mix = timer_mix_rgb($fallbackRgb, [0, 0, 0], 0.06 * $t);
        }
        $col = imagecolorallocate($im, $mix[0], $mix[1], $mix[2]);
        $y1 = (int) round(($h / $steps) * $i);
        $y2 = (int) round(($h / $steps) * ($i + 1));
        imagefilledrectangle($im, 0, $y1, $w, max($y1, $y2), $col);
    }
    // Soft side vignette for depth without needing photo art.
    $edge = max(6, (int) round($w * ($dark ? 0.035 : 0.02)));
    for ($i = 0; $i < $edge; $i++) {
        $strength = $dark ? 0.14 : 0.03;
        $alphaMix = timer_mix_rgb($fallbackRgb, [0, 0, 0], $strength * (1 - ($i / $edge)));
        $col = imagecolorallocate($im, $alphaMix[0], $alphaMix[1], $alphaMix[2]);
        imageline($im, $i, 0, $i, $h, $col);
        imageline($im, $w - 1 - $i, 0, $w - 1 - $i, $h, $col);
    }
}

/**
 * Card with a thin highlight rim for depth.
 *
 * @param \GdImage|resource $im
 */
function timer_draw_soft_card($im, int $x1, int $y1, int $x2, int $y2, int $radius, int $colPanel, int $colHi): void
{
    timer_fill_rounded_rect($im, $x1, $y1, $x2, $y2, $radius, $colPanel);
    // Hairline top sheen (avoid a thick "lid" band)
    $inset = max(4, (int) round($radius * 0.45));
    imagefilledrectangle($im, $x1 + $inset, $y1 + 2, $x2 - $inset, $y1 + 3, $colHi);
}

/** @return array{0:int,1:int,2:int,3:int} */
function timer_split_remaining(int $remaining): array
{
    $remaining = max(0, $remaining);
    $days = intdiv($remaining, 86400);
    $hms = $remaining % 86400;
    $hh = intdiv($hms, 3600);
    $mm = intdiv($hms % 3600, 60);
    $ss = $hms % 60;

    return [$days, $hh, $mm, $ss];
}

function timer_progress_ratio(int $remaining, int $createdAt, int $storedEndsAt): float
{
    $total = $storedEndsAt - $createdAt;
    if ($total <= 0) {
        return 0.0;
    }
    $ratio = 1.0 - (max(0, $remaining) / $total);

    return max(0.0, min(1.0, $ratio));
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_segmented_pills($im, string $fontPath, int $colPanel, int $colPanelHi, int $colAc, int $colMuted, int $colFg, int $days, int $hh, int $mm, int $ss, int $fontSizeMain, int $fontSizeUnit, string $sub): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $pad = (int) max(12, round($w * 0.03));
    $gap = (int) max(8, round($w * 0.015));
    $boxW = (int) floor(($w - ($pad * 2) - ($gap * 3)) / 4);
    $top = (int) round($h * 0.09);
    $bottom = (int) round($h * 0.68);
    $radius = (int) max(12, round($h * 0.11));
    $labels = ['DAYS', 'HRS', 'MIN', 'SEC'];
    $vals = [sprintf('%02d', $days), sprintf('%02d', $hh), sprintf('%02d', $mm), sprintf('%02d', $ss)];
    $digitSize = (int) max(18, min($fontSizeMain, (int) ($boxW * 0.46)));
    for ($i = 0; $i < 4; $i++) {
        $x1 = $pad + ($boxW + $gap) * $i;
        $x2 = $x1 + $boxW;
        timer_draw_soft_card($im, $x1, $top, $x2, $bottom, $radius, $colPanel, $colPanelHi);
        // Accent underline under digits
        $ux1 = (int) round($x1 + $boxW * 0.22);
        $ux2 = (int) round($x2 - $boxW * 0.22);
        $uy = (int) round($h * 0.48);
        imagefilledrectangle($im, $ux1, $uy, $ux2, $uy + 2, $colAc);
        draw_ttf_centered($im, $fontPath, $digitSize, $vals[$i], $colAc, ($x1 + $x2) / 2, $h * 0.33);
        draw_ttf_centered($im, $fontPath, $fontSizeUnit, $labels[$i], $colMuted, ($x1 + $x2) / 2, $h * 0.56);
    }
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.85);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_split_emphasis($im, string $fontPath, int $colPanel, int $colPanelHi, int $colAc, int $colMuted, int $colFg, string $mainLine, string $daysLine, string $sub, int $fontSizeMain, int $fontSizeSub, int $fontSizeUnit): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $pad = (int) max(12, round($w * 0.03));
    $split = (int) round($w * 0.28);
    $gap = (int) max(8, round($w * 0.018));
    timer_draw_soft_card($im, $pad, (int) ($h * 0.12), $split, (int) ($h * 0.80), 16, $colPanel, $colPanelHi);
    timer_draw_soft_card($im, $split + $gap, (int) ($h * 0.12), $w - $pad, (int) ($h * 0.80), 16, $colPanel, $colPanelHi);
    // Accent rail on days card
    imagefilledrectangle($im, $pad + 4, (int) ($h * 0.22), $pad + 8, (int) ($h * 0.70), $colAc);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.05), $daysLine, $colAc, ($pad + $split) / 2 + 4, $h * 0.38);
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, 'DAYS', $colMuted, ($pad + $split) / 2 + 4, $h * 0.60);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.18), $mainLine, $colAc, ($split + $gap + $w - $pad) / 2, $h * 0.40);
    draw_ttf_centered($im, $fontPath, $fontSizeSub, $sub, $colFg, ($split + $gap + $w - $pad) / 2, $h * 0.64);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_minimal_editorial($im, string $fontPath, int $colAc, int $colMuted, int $colFg, int $colRule, int $hh, int $mm, int $ss, string $daysLine, string $sub, int $fontSizeMain, int $fontSizeSub, int $fontSizeUnit): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $yRule1 = (int) round($h * 0.26);
    $yRule2 = (int) round($h * 0.74);
    $x1 = (int) round($w * 0.10);
    $x2 = (int) round($w * 0.90);
    imageline($im, $x1, $yRule1, $x2, $yRule1, $colRule);
    $midX = (int) round(($x1 + $x2) / 2);
    $accentHalf = (int) round(($x2 - $x1) * 0.16);
    imageline($im, $midX - $accentHalf, $yRule1 + 2, $midX + $accentHalf, $yRule1 + 2, $colAc);
    imageline($im, $x1, $yRule2, $x2, $yRule2, $colRule);
    // Small end caps
    imagefilledrectangle($im, $x1, $yRule1 - 3, $x1 + 2, $yRule1 + 5, $colAc);
    imagefilledrectangle($im, $x2 - 2, $yRule1 - 3, $x2, $yRule1 + 5, $colAc);
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, 'DAY ' . $daysLine, $colMuted, $w / 2, $h * 0.16);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.12), sprintf('%02d  ·  %02d  ·  %02d', $hh, $mm, $ss), $colAc, $w / 2, $h * 0.48);
    draw_ttf_centered($im, $fontPath, $fontSizeSub, $sub, $colFg, $w / 2, $h * 0.86);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_progress_hybrid($im, string $fontPath, int $colPanel, int $colAc, int $colAcSoft, int $colMuted, int $colFg, string $mainLine, string $daysLine, string $sub, float $progress, int $fontSizeMain, int $fontSizeSub, int $fontSizeUnit): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.15), $mainLine, $colAc, $w / 2, $h * 0.30);
    draw_ttf_centered($im, $fontPath, $fontSizeSub, $daysLine . ' days remaining', $colFg, $w / 2, $h * 0.50);
    $x1 = (int) round($w * 0.10);
    $x2 = (int) round($w * 0.90);
    $y1 = (int) round($h * 0.62);
    $y2 = (int) round($h * 0.72);
    $radius = (int) max(4, ($y2 - $y1) / 2);
    timer_fill_rounded_rect($im, $x1, $y1, $x2, $y2, $radius, $colPanel);
    $fillX = (int) round($x1 + ($x2 - $x1) * $progress);
    if ($fillX > $x1 + 4) {
        timer_fill_rounded_rect($im, $x1, $y1, $fillX, $y2, $radius, $colAc);
        timer_fill_rounded_rect($im, max($x1, $fillX - 14), $y1, $fillX, $y2, $radius, $colAcSoft);
    }
    // Tick marks
    for ($t = 1; $t < 4; $t++) {
        $tx = (int) round($x1 + ($x2 - $x1) * ($t / 4));
        imageline($im, $tx, $y1 - 3, $tx, $y2 + 3, $colMuted);
    }
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, $sub, $colMuted, $w / 2, $h * 0.86);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_badge_countdown($im, string $fontPath, int $colAc, int $colInkOnAc, int $colFg, int $colMuted, int $colPanel, int $colPanelHi, int $hh, int $mm, int $ss, string $daysLine, string $sub, int $fontSizeMain, int $fontSizeSub, int $fontSizeUnit): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $pad = (int) max(12, round($w * 0.03));
    $badgeRight = (int) round($w * 0.28);
    $gap = (int) max(8, round($w * 0.016));
    timer_fill_rounded_rect($im, $pad, (int) ($h * 0.14), $badgeRight, (int) ($h * 0.86), 18, $colAc);
    timer_draw_soft_card($im, $badgeRight + $gap, (int) ($h * 0.14), $w - $pad, (int) ($h * 0.86), 18, $colPanel, $colPanelHi);
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, 'ENDS', $colInkOnAc, ($pad + $badgeRight) / 2, $h * 0.42);
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, 'IN', $colInkOnAc, ($pad + $badgeRight) / 2, $h * 0.58);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.08), sprintf('%02d:%02d:%02d', $hh, $mm, $ss), $colAc, ($badgeRight + $gap + $w - $pad) / 2, $h * 0.40);
    draw_ttf_centered($im, $fontPath, $fontSizeSub, $daysLine . 'd · ' . $sub, $colFg, ($badgeRight + $gap + $w - $pad) / 2, $h * 0.64);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_cinema_marquee($im, string $fontPath, int $colAc, int $colMuted, int $colFg, int $colRule, int $colPanel, string $mainLine, string $daysLine, string $sub, int $fontSizeMain, int $fontSizeSub, int $fontSizeUnit): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $barH = max(6, (int) round($h * 0.055));
    $x1 = (int) ($w * 0.07);
    $x2 = (int) ($w * 0.93);
    timer_fill_rounded_rect($im, $x1, (int) ($h * 0.10), $x2, (int) ($h * 0.10) + $barH, 3, $colAc);
    timer_fill_rounded_rect($im, $x1, (int) ($h * 0.82), $x2, (int) ($h * 0.82) + $barH, 3, $colAc);
    $dotY1 = (int) ($h * 0.10) + (int) ($barH / 2);
    $dotY2 = (int) ($h * 0.82) + (int) ($barH / 2);
    $dotStep = max(12, (int) ($w * 0.04));
    for ($x = $x1 + 10; $x < $x2 - 8; $x += $dotStep) {
        imagefilledellipse($im, $x, $dotY1, 4, 4, $colPanel);
        imagefilledellipse($im, $x, $dotY2, 4, 4, $colPanel);
    }
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, $daysLine . ' DAYS TO GO', $colMuted, $w / 2, $h * 0.30);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.22), $mainLine, $colAc, $w / 2, $h * 0.50);
    draw_ttf_centered($im, $fontPath, $fontSizeSub, $sub, $colFg, $w / 2, $h * 0.68);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_duo_blocks($im, string $fontPath, int $colPanel, int $colPanelHi, int $colAc, int $colMuted, int $colFg, string $daysLine, string $mainLine, string $sub, int $fontSizeMain, int $fontSizeSub, int $fontSizeUnit): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $pad = (int) max(12, round($w * 0.03));
    $gap = (int) max(10, round($w * 0.02));
    $mid = (int) round(($w - $pad * 2 - $gap) * 0.32) + $pad;
    timer_draw_soft_card($im, $pad, (int) ($h * 0.10), $mid, (int) ($h * 0.72), 16, $colPanel, $colPanelHi);
    timer_draw_soft_card($im, $mid + $gap, (int) ($h * 0.10), $w - $pad, (int) ($h * 0.72), 16, $colPanel, $colPanelHi);
    imagefilledrectangle($im, $pad + 5, (int) ($h * 0.20), $pad + 9, (int) ($h * 0.62), $colAc);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.25), $daysLine, $colAc, ($pad + $mid) / 2 + 4, $h * 0.36);
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, 'DAYS', $colMuted, ($pad + $mid) / 2 + 4, $h * 0.56);
    draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.08), $mainLine, $colAc, ($mid + $gap + $w - $pad) / 2, $h * 0.38);
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, 'HRS · MIN · SEC', $colMuted, ($mid + $gap + $w - $pad) / 2, $h * 0.56);
    draw_ttf_centered($im, $fontPath, $fontSizeSub, $sub, $colFg, $w / 2, $h * 0.86);
}

/**
 * @param \GdImage|resource $im
 */
function draw_ttf_centered($im, string $font, int $size, string $text, int $color, float $cx, float $cy): void
{
    $box = imagettfbbox($size, 0, $font, $text);
    if ($box === false) {
        return;
    }
    $tw = (int) abs($box[2] - $box[0]);
    $th = (int) abs($box[7] - $box[1]);
    $x = (int) ($cx - $tw / 2);
    $y = (int) ($cy + $th / 2);
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/**
 * @param \GdImage|resource $im
 */
function imagestring_centered($im, int $font, string $text, int $color, int $y): void
{
    $fw = imagefontwidth($font);
    $tw = strlen($text) * $fw;
    $w = imagesx($im);
    $x = (int) (($w - $tw) / 2);
    imagestring($im, $font, $x, $y, $text, $color);
}

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    timer_serve_image_request();
}
