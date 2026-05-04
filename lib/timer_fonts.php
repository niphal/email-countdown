<?php

declare(strict_types=1);

/**
 * Server-side TTF resolution for GD imagettftext (not browser Google Fonts).
 *
 * @return list<string>
 */
function timer_font_keys(): array
{
    return ['system', 'dejavu_bold', 'dejavu_book', 'segoe_bold', 'arial_bold'];
}

/** @return array<string, string> key => label */
function timer_font_labels(): array
{
    return [
        'system' => 'System default (first available)',
        'dejavu_bold' => 'DejaVu Sans Bold',
        'dejavu_book' => 'DejaVu Sans (regular)',
        'segoe_bold' => 'Segoe UI Bold (Windows)',
        'arial_bold' => 'Arial Bold (Windows)',
    ];
}

function timer_is_valid_font_key(string $k): bool
{
    return in_array($k, timer_font_keys(), true);
}

function timer_pick_system_font(): ?string
{
    $candidates = [
        'C:\\Windows\\Fonts\\segoeuib.ttf',
        'C:\\Windows\\Fonts\\arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    ];
    foreach ($candidates as $p) {
        if (is_readable($p)) {
            return $p;
        }
    }

    return null;
}

function timer_resolve_font(string $key): ?string
{
    if ($key === 'system') {
        return timer_pick_system_font();
    }
    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
    $paths = [
        'dejavu_bold' => [
            $root . '/fonts/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ],
        'dejavu_book' => [
            $root . '/fonts/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ],
        'segoe_bold' => ['C:\\Windows\\Fonts\\segoeuib.ttf'],
        'arial_bold' => ['C:\\Windows\\Fonts\\arialbd.ttf'],
    ];
    if (!isset($paths[$key])) {
        return timer_pick_system_font();
    }
    foreach ($paths[$key] as $p) {
        if (is_readable($p)) {
            return $p;
        }
    }

    return timer_pick_system_font();
}
