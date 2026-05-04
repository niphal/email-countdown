<?php

declare(strict_types=1);

const TIMER_ANIMATION_FRAMES = 20;
const TIMER_FRAME_DELAY_CS = 100;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/timer_fonts.php';

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

$stmt = db()->prepare('SELECT name, ends_at, bg_color, text_color, accent_color, label, width, height, font_key, font_size_main FROM timers WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}

$endsAt = $overrideEnd ?? (int) $row['ends_at'];
$w = (int) $row['width'];
$h = (int) $row['height'];
$bg = parse_hex($row['bg_color']);
$fg = parse_hex($row['text_color']);
$ac = parse_hex($row['accent_color']);
$label = (string) $row['label'];
$fontKey = timer_normalize_font_key((string) ($row['font_key'] ?? 'noto_sans_bold'));
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
    $im = render_timer_frame($w, $h, $bg, $fg, $ac, $label, $remaining, $fontPath, $fontSizeMain);
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
        $frames[] = render_timer_frame($w, $h, $bg, $fg, $ac, $label, $remaining, $fontPath, $fontSizeMain);
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
function render_timer_frame(int $w, int $h, array $bg, array $fg, array $ac, string $label, int $remaining, string $fontPath, int $fontSizeMain): \GdImage
{
    $im = imagecreatetruecolor($w, $h);
    $colBg = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
    $colFg = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
    $colAc = imagecolorallocate($im, $ac[0], $ac[1], $ac[2]);
    imagefilledrectangle($im, 0, 0, $w, $h, $colBg);

    if ($remaining <= 0) {
        $text = '00 : 00 : 00';
        $sub = 'Offer ended';
    } else {
        $days = intdiv($remaining, 86400);
        $hms = $remaining % 86400;
        $hh = intdiv($hms, 3600);
        $mm = intdiv($hms % 3600, 60);
        $ss = $hms % 60;
        if ($days > 0) {
            $text = sprintf('%dd %02d:%02d:%02d', $days, $hh, $mm, $ss);
        } else {
            $text = sprintf('%02d : %02d : %02d', $hh, $mm, $ss);
        }
        $sub = $label !== '' ? $label : 'Time remaining';
    }

    $fontSizeMain = (int) max(10, min(72, $fontSizeMain, (int) ($h * 0.52)));
    $fontSizeSub = (int) max(8, min(40, (int) round($fontSizeMain * 0.45)));

    if (is_readable($fontPath) && function_exists('imagettfbbox')) {
        draw_ttf_centered($im, $fontPath, $fontSizeMain, $text, $colAc, $w / 2, $h * 0.42);
        draw_ttf_centered($im, $fontPath, $fontSizeSub, $sub, $colFg, $w / 2, $h * 0.78);
    } else {
        $y1 = (int) ($h / 2 - imagefontheight(5));
        imagestring_centered($im, 5, $text, $colAc, $y1);
        $y2 = (int) ($h / 2 + 8);
        imagestring_centered($im, 3, $sub, $colFg, $y2);
    }

    imagetruecolortopalette($im, true, 255);

    return $im;
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
