<?php

declare(strict_types=1);

require_once __DIR__ . '/timer_fonts.php';

/**
 * Default advanced design options stored in design_json.
 *
 * @return array<string, mixed>
 */
function timer_design_defaults(): array
{
    return [
        'bg_mode' => 'solid',
        'show_days' => true,
        'show_hours' => true,
        'show_minutes' => true,
        'show_seconds' => true,
        'after_count' => 'message',
        'label_font_key' => 'open_sans',
        'label_font_size' => 0,
        'labels_color' => '',
        'separator_color' => '',
        'numbers_spacing' => 50,
        'separator_style' => 'colon',
        'separator_size' => 50,
        'labels_position' => 50,
        'section_position' => 50,
    ];
}

/**
 * @param mixed $raw JSON string, array, or null
 * @return array<string, mixed>
 */
function timer_design_normalize(mixed $raw): array
{
    $data = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    } elseif (is_array($raw)) {
        $data = $raw;
    }

    return timer_design_sanitize($data);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function timer_design_sanitize(array $input): array
{
    $d = timer_design_defaults();
    $bgMode = (string) ($input['bg_mode'] ?? $d['bg_mode']);
    $d['bg_mode'] = in_array($bgMode, ['solid', 'transparent'], true) ? $bgMode : 'solid';

    foreach (['show_days', 'show_hours', 'show_minutes', 'show_seconds'] as $k) {
        if (array_key_exists($k, $input)) {
            $d[$k] = !empty($input[$k]);
        }
    }
    // At least one unit must remain visible.
    if (!$d['show_days'] && !$d['show_hours'] && !$d['show_minutes'] && !$d['show_seconds']) {
        $d['show_seconds'] = true;
    }

    $after = (string) ($input['after_count'] ?? $d['after_count']);
    $d['after_count'] = in_array($after, ['zeros', 'message', 'hide'], true) ? $after : 'message';

    $d['label_font_key'] = timer_normalize_font_key((string) ($input['label_font_key'] ?? $d['label_font_key']));
    $labelFs = (int) ($input['label_font_size'] ?? 0);
    $d['label_font_size'] = max(0, min(48, $labelFs));

    $d['labels_color'] = timer_design_sanitize_hex_or_empty((string) ($input['labels_color'] ?? ''));
    $d['separator_color'] = timer_design_sanitize_hex_or_empty((string) ($input['separator_color'] ?? ''));

    foreach (['numbers_spacing', 'separator_size', 'labels_position', 'section_position'] as $k) {
        $d[$k] = max(0, min(100, (int) ($input[$k] ?? $d[$k])));
    }

    $sep = (string) ($input['separator_style'] ?? $d['separator_style']);
    $d['separator_style'] = in_array($sep, ['colon', 'dots', 'none'], true) ? $sep : 'colon';

    return $d;
}

function timer_design_sanitize_hex_or_empty(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $s)) {
        return '#' . strtolower(substr($s, 1));
    }

    return '';
}

function timer_design_encode(array $design): string
{
    return json_encode(timer_design_sanitize($design), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/**
 * Visible unit slots for drawing.
 *
 * @param array<string, mixed> $design
 * @return list<array{key:string,label:string,value:string,max:int}>
 */
function timer_design_visible_units(array $design, int $days, int $hh, int $mm, int $ss): array
{
    $slots = [];
    if (!empty($design['show_days'])) {
        $slots[] = ['key' => 'days', 'label' => 'DAYS', 'value' => sprintf('%02d', $days), 'max' => max(1, $days + 1)];
    }
    if (!empty($design['show_hours'])) {
        $slots[] = ['key' => 'hours', 'label' => 'HOURS', 'value' => sprintf('%02d', $hh), 'max' => 24];
    }
    if (!empty($design['show_minutes'])) {
        $slots[] = ['key' => 'minutes', 'label' => 'MINUTES', 'value' => sprintf('%02d', $mm), 'max' => 60];
    }
    if (!empty($design['show_seconds'])) {
        $slots[] = ['key' => 'seconds', 'label' => 'SECONDS', 'value' => sprintf('%02d', $ss), 'max' => 60];
    }
    if ($slots === []) {
        $slots[] = ['key' => 'seconds', 'label' => 'SECONDS', 'value' => sprintf('%02d', $ss), 'max' => 60];
    }

    return $slots;
}

/**
 * Map unit progress 0..1 for ring layouts.
 *
 * @param array{key:string,value:string,max:int} $slot
 */
function timer_design_unit_progress(array $slot): float
{
    $v = (int) $slot['value'];
    $max = max(1, (int) $slot['max']);
    if ($slot['key'] === 'days') {
        return max(0.0, min(1.0, $v / $max));
    }

    return max(0.0, min(1.0, $v / $max));
}

function timer_design_separator_glyph(array $design): string
{
    return match ((string) ($design['separator_style'] ?? 'colon')) {
        'dots' => '·',
        'none' => ' ',
        default => ':',
    };
}
