<?php

declare(strict_types=1);

const TIMER_ANIMATION_FRAMES = 20;
const TIMER_FRAME_DELAY_CS = 100;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';
require_once __DIR__ . '/lib/timer_layouts.php';

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP GD is not enabled. In php.ini set extension=gd, then restart PHP or your web server.';
    exit;
}

$id = $_GET['id'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
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
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Dynamic end override requires a valid sig parameter.';
        exit;
    }
}

$stmt = db()->prepare('SELECT name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main, layout_key, created_at FROM timers WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
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

if (!timer_gd_has_freetype()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP GD is missing FreeType support, so TrueType fonts cannot render. Install a PHP build where GD is linked with FreeType (often shown as "FreeType Support => enabled" in phpinfo).';
    exit;
}

$fontPath = timer_ensure_ttf_path($fontKey);
if ($fontPath === null) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Timer fonts could not be downloaded. Ensure the data/fonts directory is writable and that PHP can make outbound HTTPS requests (curl extension or allow_url_fopen). Fonts are fetched once from Google Fonts open-source repositories.';
    exit;
}

$format = strtolower((string) ($_GET['format'] ?? 'gif'));
$t0 = time();

header('Cache-Control: private, max-age=60');

if ($format === 'png') {
    header('Content-Type: image/png');
    $remaining = max(0, $endsAt - $t0);
    $im = render_timer_frame($w, $h, $bg, $fg, $ac, $label, $remaining, $fontPath, $fontSizeMain, $layoutKey, $createdAt, $storedEndsAt);
    imagepng($im);
    imagedestroy($im);
    exit;
}

require_once __DIR__ . '/lib/GifCreator.php';

$frames = [];
$gifBinary = null;
try {
    for ($k = 0; $k < TIMER_ANIMATION_FRAMES; $k++) {
        $remaining = max(0, $endsAt - ($t0 + $k));
        $frames[] = render_timer_frame($w, $h, $bg, $fg, $ac, $label, $remaining, $fontPath, $fontSizeMain, $layoutKey, $createdAt, $storedEndsAt);
    }
    $durations = array_fill(0, TIMER_ANIMATION_FRAMES, TIMER_FRAME_DELAY_CS);
    $creator = new GifCreator();
    $creator->setOmitNetscapeLoop(true);
    $gifBinary = $creator->create($frames, $durations, 0);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not build animated timer.';
    exit;
} finally {
    foreach ($frames as $im) {
        if ($im instanceof \GdImage || (is_resource($im) && get_resource_type($im) === 'gd')) {
            imagedestroy($im);
        }
    }
}

header('Content-Type: image/gif');
echo $gifBinary;

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
    int $storedEndsAt
): \GdImage
{
    $im = imagecreatetruecolor($w, $h);
    $colBg = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
    $colFg = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
    $colAc = imagecolorallocate($im, $ac[0], $ac[1], $ac[2]);
    $colMuted = imagecolorallocate($im, (int) (($fg[0] + $bg[0] * 2) / 3), (int) (($fg[1] + $bg[1] * 2) / 3), (int) (($fg[2] + $bg[2] * 2) / 3));
    $colPanel = imagecolorallocate($im, (int) (($bg[0] * 3 + 255) / 4), (int) (($bg[1] * 3 + 255) / 4), (int) (($bg[2] * 3 + 255) / 4));
    imagefilledrectangle($im, 0, 0, $w, $h, $colBg);

    [$days, $hh, $mm, $ss] = timer_split_remaining($remaining);
    $mainLine = sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
    $daysLine = sprintf('%02d', $days);
    $sub = $remaining <= 0 ? 'Offer ended' : ($label !== '' ? $label : 'Time remaining');
    $progress = timer_progress_ratio($remaining, $createdAt, $storedEndsAt);

    $fontSizeMain = (int) max(10, min(72, $fontSizeMain, (int) ($h * 0.52)));
    $fontSizeSub = (int) max(8, min(40, (int) round($fontSizeMain * 0.45)));
    $fontSizeUnit = (int) max(8, (int) round($fontSizeSub * 0.6));

    if (is_readable($fontPath) && function_exists('imagettfbbox') && $layoutKey === 'segmented_pills') {
        timer_draw_segmented_pills($im, $fontPath, $colPanel, $colAc, $colMuted, $colFg, $days, $hh, $mm, $ss, $fontSizeMain, $fontSizeUnit, $sub);
    } elseif (is_readable($fontPath) && function_exists('imagettfbbox') && $layoutKey === 'split_emphasis') {
        draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.1), $mainLine, $colAc, $w / 2, $h * 0.55);
        draw_ttf_centered($im, $fontPath, $fontSizeSub, 'DAYS LEFT: ' . $daysLine, $colMuted, $w / 2, $h * 0.22);
        draw_ttf_centered($im, $fontPath, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.84);
    } elseif (is_readable($fontPath) && function_exists('imagettfbbox') && $layoutKey === 'minimal_editorial') {
        draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.05), sprintf('%02d  :  %02d  :  %02d', $hh, $mm, $ss), $colAc, $w / 2, $h * 0.50);
        draw_ttf_centered($im, $fontPath, $fontSizeSub, 'D ' . $daysLine, $colMuted, $w / 2, $h * 0.22);
        draw_ttf_centered($im, $fontPath, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.80);
    } elseif (is_readable($fontPath) && function_exists('imagettfbbox') && $layoutKey === 'progress_hybrid') {
        draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 1.05), $mainLine, $colAc, $w / 2, $h * 0.42);
        draw_ttf_centered($im, $fontPath, $fontSizeSub, 'DAYS ' . $daysLine, $colFg, $w / 2, $h * 0.67);
        timer_draw_progress_bar($im, $colPanel, $colAc, $progress);
        draw_ttf_centered($im, $fontPath, $fontSizeUnit, $sub, $colMuted, $w / 2, $h * 0.86);
    } elseif (is_readable($fontPath) && function_exists('imagettfbbox') && $layoutKey === 'badge_countdown') {
        timer_draw_badge_left($im, $fontPath, $colAc, $colBg, $fontSizeUnit);
        draw_ttf_centered($im, $fontPath, (int) ($fontSizeMain * 0.95), sprintf('%02d:%02d:%02d', $hh, $mm, $ss), $colAc, $w * 0.62, $h * 0.45);
        draw_ttf_centered($im, $fontPath, $fontSizeSub, 'D ' . $daysLine . '   ' . $sub, $colFg, $w * 0.62, $h * 0.78);
    } else {
        $y1 = (int) ($h / 2 - imagefontheight(5));
        imagestring_centered($im, 5, sprintf('%02d:%02d:%02d', $hh, $mm, $ss), $colAc, $y1);
        $y2 = (int) ($h / 2 + 8);
        imagestring_centered($im, 3, $sub, $colFg, $y2);
    }

    imagetruecolortopalette($im, true, 255);

    return $im;
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
function timer_draw_segmented_pills($im, string $fontPath, int $colPanel, int $colAc, int $colMuted, int $colFg, int $days, int $hh, int $mm, int $ss, int $fontSizeMain, int $fontSizeUnit, string $sub): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $pad = (int) max(8, round($w * 0.025));
    $gap = (int) max(6, round($w * 0.012));
    $boxW = (int) floor(($w - ($pad * 2) - ($gap * 3)) / 4);
    $top = (int) round($h * 0.12);
    $bottom = (int) round($h * 0.68);
    $labels = ['DAYS', 'HRS', 'MIN', 'SEC'];
    $vals = [sprintf('%02d', $days), sprintf('%02d', $hh), sprintf('%02d', $mm), sprintf('%02d', $ss)];
    for ($i = 0; $i < 4; $i++) {
        $x1 = $pad + ($boxW + $gap) * $i;
        $x2 = $x1 + $boxW;
        imagefilledrectangle($im, $x1, $top, $x2, $bottom, $colPanel);
        draw_ttf_centered($im, $fontPath, $fontSizeMain, $vals[$i], $colAc, ($x1 + $x2) / 2, $h * 0.40);
        draw_ttf_centered($im, $fontPath, $fontSizeUnit, $labels[$i], $colMuted, ($x1 + $x2) / 2, $h * 0.60);
    }
    draw_ttf_centered($im, $fontPath, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.86);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_progress_bar($im, int $colTrack, int $colFill, float $ratio): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $x1 = (int) round($w * 0.12);
    $x2 = (int) round($w * 0.88);
    $y1 = (int) round($h * 0.74);
    $y2 = (int) round($h * 0.82);
    imagefilledrectangle($im, $x1, $y1, $x2, $y2, $colTrack);
    $fillX = (int) round($x1 + ($x2 - $x1) * $ratio);
    imagefilledrectangle($im, $x1, $y1, $fillX, $y2, $colFill);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_badge_left($im, string $fontPath, int $colBadge, int $colText, int $fontSize): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $x1 = (int) round($w * 0.04);
    $x2 = (int) round($w * 0.28);
    $y1 = (int) round($h * 0.22);
    $y2 = (int) round($h * 0.78);
    imagefilledrectangle($im, $x1, $y1, $x2, $y2, $colBadge);
    draw_ttf_centered($im, $fontPath, (int) ($fontSize * 1.2), 'ENDS IN', $colText, ($x1 + $x2) / 2, $h * 0.48);
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
