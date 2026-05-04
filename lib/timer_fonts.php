<?php

declare(strict_types=1);

/**
 * Timer image fonts: open-licensed TTF from Google Fonts source repos, cached under data/fonts/.
 * GD needs real font files on disk; this module downloads them once (curl or allow_url_fopen).
 */

function timer_font_cache_dir(): string
{
    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
    $dir = $root . '/data/fonts';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * @return array<string, array{file: string, min: int, urls: list<string>}>
 */
function timer_font_manifest(): array
{
    $raw = 'https://raw.githubusercontent.com';
    $jsd = 'https://cdn.jsdelivr.net/gh';

    return [
            'noto_sans_bold' => [
                'file' => 'NotoSans-Bold.ttf',
                'min' => 100000,
                'urls' => [
                    $raw . '/googlefonts/noto-fonts/main/hinted/ttf/NotoSans/NotoSans-Bold.ttf',
                    $jsd . '/googlefonts/noto-fonts@main/hinted/ttf/NotoSans/NotoSans-Bold.ttf',
                ],
            ],
            'noto_sans' => [
                'file' => 'NotoSans-Regular.ttf',
                'min' => 100000,
                'urls' => [
                    $raw . '/googlefonts/noto-fonts/main/hinted/ttf/NotoSans/NotoSans-Regular.ttf',
                    $jsd . '/googlefonts/noto-fonts@main/hinted/ttf/NotoSans/NotoSans-Regular.ttf',
                ],
            ],
            'roboto_bold' => [
                'file' => 'Roboto-Bold.ttf',
                'min' => 100000,
                'urls' => [
                    $raw . '/googlefonts/roboto/main/src/hinted/Roboto-Bold.ttf',
                    $jsd . '/googlefonts/roboto@main/src/hinted/Roboto-Bold.ttf',
                ],
            ],
            'roboto' => [
                'file' => 'Roboto-Regular.ttf',
                'min' => 100000,
                'urls' => [
                    $raw . '/googlefonts/roboto/main/src/hinted/Roboto-Regular.ttf',
                    $jsd . '/googlefonts/roboto@main/src/hinted/Roboto-Regular.ttf',
                ],
            ],
            'open_sans_bold' => [
                'file' => 'OpenSans-Bold.ttf',
                'min' => 80000,
                'urls' => [
                    $raw . '/googlefonts/opensans/main/fonts/ttf/OpenSans-Bold.ttf',
                    $jsd . '/googlefonts/opensans@main/fonts/ttf/OpenSans-Bold.ttf',
                ],
            ],
            'open_sans' => [
                'file' => 'OpenSans-Regular.ttf',
                'min' => 80000,
                'urls' => [
                    $raw . '/googlefonts/opensans/main/fonts/ttf/OpenSans-Regular.ttf',
                    $jsd . '/googlefonts/opensans@main/fonts/ttf/OpenSans-Regular.ttf',
                ],
            ],
    ];
}

/** @return list<string> */
function timer_font_keys(): array
{
    return array_keys(timer_font_manifest());
}

/** @return array<string, string> */
function timer_font_labels(): array
{
    return [
        'noto_sans_bold' => 'Noto Sans (bold) — Google Fonts / OFL',
        'noto_sans' => 'Noto Sans (regular) — Google Fonts / OFL',
        'roboto_bold' => 'Roboto (bold) — Google Fonts / Apache 2.0',
        'roboto' => 'Roboto (regular) — Google Fonts / Apache 2.0',
        'open_sans_bold' => 'Open Sans (bold) — Google Fonts / Apache 2.0',
        'open_sans' => 'Open Sans (regular) — Google Fonts / Apache 2.0',
    ];
}

function timer_normalize_font_key(string $k): string
{
    $legacy = [
        'system' => 'noto_sans_bold',
        'dejavu_bold' => 'noto_sans_bold',
        'segoe_bold' => 'noto_sans_bold',
        'arial_bold' => 'noto_sans_bold',
        'dejavu_book' => 'noto_sans',
    ];
    if (isset($legacy[$k])) {
        return $legacy[$k];
    }
    if (array_key_exists($k, timer_font_manifest())) {
        return $k;
    }

    return 'noto_sans_bold';
}

function timer_is_valid_font_key(string $k): bool
{
    return array_key_exists($k, timer_font_manifest());
}

function timer_http_get(string $url, int $timeoutSec = 45): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_USERAGENT => 'email-countdown-font-fetch/1',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($out !== false && $code >= 200 && $code < 300 && $out !== '') {
            return $out;
        }

        return null;
    }
    if (!ini_get('allow_url_fopen')) {
        return null;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeoutSec,
            'header' => "User-Agent: email-countdown-font-fetch/1\r\n",
        ],
    ]);
    $out = @file_get_contents($url, false, $ctx);

    return $out === false ? null : $out;
}

function timer_looks_like_ttf_or_otf(string $bin): bool
{
    if (strlen($bin) < 12) {
        return false;
    }
    if (str_starts_with($bin, '<!DOCTYPE') || str_starts_with($bin, '<html')) {
        return false;
    }
    $sig = substr($bin, 0, 4);

    return $sig === 'OTTO' || $sig === "\x00\x01\x00\x00" || $sig === 'true' || $sig === 'ttcf';
}

/** Download one font into cache if missing; return absolute path or null. */
function timer_ensure_ttf_path(string $fontKey): ?string
{
    $fontKey = timer_normalize_font_key($fontKey);
    $manifest = timer_font_manifest();
    if (!isset($manifest[$fontKey])) {
        return null;
    }
    $meta = $manifest[$fontKey];
    $dir = timer_font_cache_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return null;
    }
    $dest = $dir . '/' . $meta['file'];
    if (is_readable($dest) && filesize($dest) >= $meta['min']) {
        return $dest;
    }
    foreach ($meta['urls'] as $url) {
        $bin = timer_http_get($url, 60);
        if ($bin === null || strlen($bin) < $meta['min'] || !timer_looks_like_ttf_or_otf($bin)) {
            continue;
        }
        $tmp = $dest . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $bin, LOCK_EX) === false) {
            continue;
        }
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            continue;
        }
        if (is_readable($dest) && filesize($dest) >= $meta['min']) {
            return $dest;
        }
    }

    return is_readable($dest) && filesize($dest) >= $meta['min'] ? $dest : null;
}

/** Prefetch all manifest fonts (e.g. after install). */
function timer_font_warm_all(): void
{
    foreach (timer_font_keys() as $key) {
        timer_ensure_ttf_path($key);
    }
}

/** True if PHP can render TTF with GD. */
function timer_gd_has_freetype(): bool
{
    if (!extension_loaded('gd') || !function_exists('imagettfbbox')) {
        return false;
    }
    $info = function_exists('gd_info') ? gd_info() : [];

    return !empty($info['FreeType Support']);
}

/** True if HTTP font download is likely to work. */
function timer_can_fetch_remote_fonts(): bool
{
    return function_exists('curl_init') || (bool) ini_get('allow_url_fopen');
}
