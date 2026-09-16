<?php

declare(strict_types=1);

function timer_render_cache_dir(): string
{
    $dir = APP_ROOT . '/data/render_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * Short-lived binary cache for animated timer responses (aligns with Cache-Control).
 */
function timer_render_cache_get(string $key, int $ttlSec = 25): ?string
{
    if (!preg_match('/^[a-f0-9]{64}$/', $key)) {
        return null;
    }
    $path = timer_render_cache_dir() . '/' . $key . '.bin';
    if (!is_readable($path)) {
        return null;
    }
    $mtime = @filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttlSec) {
        @unlink($path);

        return null;
    }
    $bin = @file_get_contents($path);

    return ($bin !== false && $bin !== '') ? $bin : null;
}

function timer_render_cache_put(string $key, string $binary): void
{
    if (!preg_match('/^[a-f0-9]{64}$/', $key) || $binary === '') {
        return;
    }
    $dir = timer_render_cache_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    $path = $dir . '/' . $key . '.bin';
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $binary, LOCK_EX) === false) {
        return;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
    }
    timer_render_cache_gc($dir);
}

function timer_render_cache_gc(string $dir, int $maxFiles = 200, int $ttlSec = 120): void
{
    // Cheap occasional cleanup — avoid scanning every request.
    if (random_int(1, 40) !== 1) {
        return;
    }
    $files = glob($dir . '/*.bin') ?: [];
    $now = time();
    foreach ($files as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && ($now - $mtime) > $ttlSec) {
            @unlink($file);
        }
    }
    $files = glob($dir . '/*.bin') ?: [];
    if (count($files) <= $maxFiles) {
        return;
    }
    usort($files, static function (string $a, string $b): int {
        return (@filemtime($a) ?: 0) <=> (@filemtime($b) ?: 0);
    });
    $extra = count($files) - $maxFiles;
    for ($i = 0; $i < $extra; $i++) {
        @unlink($files[$i]);
    }
}

/**
 * @param array<string, mixed> $row
 */
function timer_render_cache_key(string $id, string $format, int $endsAt, ?int $overrideEnd, array $row): string
{
    return hash('sha256', implode("\0", [
        $id,
        $format,
        (string) $endsAt,
        $overrideEnd === null ? '' : (string) $overrideEnd,
        (string) ($row['bg_color'] ?? ''),
        (string) ($row['text_color'] ?? ''),
        (string) ($row['accent_color'] ?? ''),
        (string) ($row['label'] ?? ''),
        (string) ($row['width'] ?? ''),
        (string) ($row['height'] ?? ''),
        (string) ($row['font_key'] ?? ''),
        (string) ($row['font_size_main'] ?? ''),
        (string) ($row['layout_key'] ?? ''),
        (string) ($row['bg_image_file'] ?? ''),
        (string) ($row['bg_overlay_color'] ?? ''),
        (string) ($row['bg_overlay_opacity'] ?? ''),
        (string) ($row['created_at'] ?? ''),
        (string) TIMER_ANIMATION_FRAMES,
        (string) TIMER_GIF_COLORS,
    ]));
}
