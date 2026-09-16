<?php

declare(strict_types=1);

function timer_assets_dir(): string
{
    $dir = APP_ROOT . '/data/template_assets';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function timer_template_asset_path(int $workspaceId, string $templateId, string $ext): string
{
    $ws = max(1, $workspaceId);
    $dir = timer_assets_dir() . '/' . $ws;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?? 'jpg');
    if ($ext === '') {
        $ext = 'jpg';
    }

    return $dir . '/' . $templateId . '.' . $ext;
}

/** Relative path stored in DB (from APP_ROOT). */
function timer_template_asset_relative(int $workspaceId, string $templateId, string $ext): string
{
    $ws = max(1, $workspaceId);
    $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?? 'jpg');

    return 'data/template_assets/' . $ws . '/' . $templateId . '.' . $ext;
}

function timer_resolve_asset_absolute(string $relativePath): ?string
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return null;
    }
    $full = APP_ROOT . '/' . ltrim($relativePath, '/');
    if (!is_file($full) || !is_readable($full)) {
        return null;
    }

    return $full;
}

/**
 * @param array{0:int,1:int,2:int} $fallbackRgb
 */
function timer_paint_canvas_background(
    \GdImage $im,
    int $w,
    int $h,
    array $fallbackRgb,
    string $bgImageRelative,
    string $overlayHex,
    int $overlayOpacityPct,
    bool $transparent = false
): void {
    if ($transparent) {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $clear);
        imagealphablending($im, true);
    } else {
        $colBg = imagecolorallocate($im, $fallbackRgb[0], $fallbackRgb[1], $fallbackRgb[2]);
        imagefilledrectangle($im, 0, 0, $w, $h, $colBg);
    }

    $abs = timer_resolve_asset_absolute($bgImageRelative);
    $hasPhoto = false;
    if ($abs !== null) {
        $src = timer_load_image_file($abs);
        if ($src instanceof \GdImage) {
            timer_blit_cover($im, $src, $w, $h);
            imagedestroy($src);
            $hasPhoto = true;
        }
    }

    if (!$transparent && function_exists('timer_paint_canvas_atmosphere')) {
        timer_paint_canvas_atmosphere($im, $w, $h, $fallbackRgb, $hasPhoto);
    }

    $overlayOpacityPct = max(0, min(100, $overlayOpacityPct));
    if ($overlayOpacityPct > 0) {
        $ov = timer_parse_hex_color($overlayHex, [0, 0, 0]);
        $alpha = (int) round(127 - ($overlayOpacityPct / 100) * 127);
        $overlay = imagecolorallocatealpha($im, $ov[0], $ov[1], $ov[2], $alpha);
        imagefilledrectangle($im, 0, 0, $w, $h, $overlay);
    }
}

function timer_load_image_file(string $path): ?\GdImage
{
    $info = @getimagesize($path);
    if ($info === false) {
        return null;
    }
    $type = $info[2] ?? 0;
    $im = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_GIF => @imagecreatefromgif($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if ($im === false) {
        return null;
    }

    return $im;
}

function timer_blit_cover(\GdImage $dest, \GdImage $src, int $dw, int $dh): void
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        return;
    }
    $scale = max($dw / $sw, $dh / $sh);
    $nw = (int) ceil($sw * $scale);
    $nh = (int) ceil($sh * $scale);
    $tmp = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    $ox = (int) floor(($nw - $dw) / 2);
    $oy = (int) floor(($nh - $dh) / 2);
    imagecopy($dest, $tmp, 0, 0, $ox, $oy, $dw, $dh);
    imagedestroy($tmp);
}

/**
 * @return array{0:int,1:int,2:int}
 */
function timer_parse_hex_color(string $hex, array $fallback): array
{
    $hex = trim($hex);
    if (preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m)) {
        $h = strtolower($m[1]);

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    return $fallback;
}

function timer_allowed_upload_image(string $tmpPath, string $origName): ?string
{
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        return null;
    }
    $maxBytes = 2 * 1024 * 1024;
    if (@filesize($tmpPath) > $maxBytes) {
        return null;
    }
    $type = $info[2] ?? 0;
    $ext = match ($type) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
    if ($ext === null) {
        return null;
    }
    $safe = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($safe, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $safe = $ext;
    }
    if ($safe === 'jpeg') {
        $safe = 'jpg';
    }

    return $safe;
}
