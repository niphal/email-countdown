<?php

declare(strict_types=1);

require_once __DIR__ . '/timer_fonts.php';
require_once __DIR__ . '/timer_layouts.php';
require_once __DIR__ . '/timer_background.php';

function timer_template_style_columns(): array
{
    return [
        'bg_color', 'text_color', 'accent_color', 'width', 'height',
        'font_key', 'font_size_main', 'layout_key', 'default_label',
        'bg_image_file', 'bg_overlay_color', 'bg_overlay_opacity',
    ];
}

/**
 * @return array<string, mixed>|null
 */
function timer_template_get(PDO $pdo, int $workspaceId, string $templateId): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $templateId)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM timer_templates WHERE id = ? AND workspace_id = ? LIMIT 1');
    $stmt->execute([$templateId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : timer_template_normalize_row($row);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function timer_template_normalize_row(array $row): array
{
    $row['font_key'] = timer_normalize_font_key((string) ($row['font_key'] ?? 'noto_sans_bold'));
    $row['layout_key'] = timer_normalize_layout_key((string) ($row['layout_key'] ?? 'segmented_pills'));
    $row['width'] = (int) ($row['width'] ?? 480);
    $row['height'] = (int) ($row['height'] ?? 120);
    $row['font_size_main'] = (int) ($row['font_size_main'] ?? 32);
    $row['bg_overlay_opacity'] = (int) ($row['bg_overlay_opacity'] ?? 0);
    $row['is_default'] = (int) ($row['is_default'] ?? 0);
    $row['bg_image_url'] = timer_template_public_asset_url((string) ($row['bg_image_file'] ?? ''));

    return $row;
}

function timer_template_public_asset_url(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return '';
    }
    $prefix = app_web_path_prefix();
    $path = ($prefix === '' ? '' : $prefix) . '/' . ltrim($relativePath, '/');
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    $origin = app_embed_origin();

    return $origin !== '' ? ($origin . $path) : $path;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function timer_template_sanitize_payload(array $input, bool $isCreate): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Template name is required');
    }
    $description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 400);
    $bg = timer_template_sanitize_hex((string) ($input['bg_color'] ?? '#1a1a2e'), '#1a1a2e');
    $fg = timer_template_sanitize_hex((string) ($input['text_color'] ?? '#eaeaea'), '#eaeaea');
    $ac = timer_template_sanitize_hex((string) ($input['accent_color'] ?? '#e94560'), '#e94560');
    $overlay = timer_template_sanitize_hex((string) ($input['bg_overlay_color'] ?? '#000000'), '#000000');
    $w = max(200, min(600, (int) ($input['width'] ?? 480)));
    $h = max(80, min(300, (int) ($input['height'] ?? 120)));
    $fontKey = timer_normalize_font_key((string) ($input['font_key'] ?? 'noto_sans_bold'));
    $fontSize = max(14, min(72, (int) ($input['font_size_main'] ?? 32)));
    $layoutKey = timer_normalize_layout_key((string) ($input['layout_key'] ?? 'segmented_pills'));
    $defaultLabel = mb_substr((string) ($input['default_label'] ?? ''), 0, 120);
    $overlayOpacity = max(0, min(100, (int) ($input['bg_overlay_opacity'] ?? 0)));
    $isDefault = !empty($input['is_default']) ? 1 : 0;

    return [
        'name' => mb_substr($name, 0, 120),
        'description' => $description,
        'bg_color' => $bg,
        'text_color' => $fg,
        'accent_color' => $ac,
        'width' => $w,
        'height' => $h,
        'font_key' => $fontKey,
        'font_size_main' => $fontSize,
        'layout_key' => $layoutKey,
        'default_label' => $defaultLabel,
        'bg_overlay_color' => $overlay,
        'bg_overlay_opacity' => $overlayOpacity,
        'is_default' => $isDefault,
    ];
}

function timer_template_sanitize_hex(string $s, string $fallback): string
{
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $s)) {
        return '#' . strtolower(substr($s, 1));
    }

    return $fallback;
}

/**
 * Apply template style fields onto timer create/update payload.
 *
 * @param array<string, mixed> $template
 * @return array<string, mixed>
 */
function timer_template_apply_to_timer_fields(array $template): array
{
    return [
        'bg_color' => (string) ($template['bg_color'] ?? '#1a1a2e'),
        'text_color' => (string) ($template['text_color'] ?? '#eaeaea'),
        'accent_color' => (string) ($template['accent_color'] ?? '#e94560'),
        'width' => (int) ($template['width'] ?? 480),
        'height' => (int) ($template['height'] ?? 120),
        'font_key' => (string) ($template['font_key'] ?? 'noto_sans_bold'),
        'font_size_main' => (int) ($template['font_size_main'] ?? 32),
        'layout_key' => (string) ($template['layout_key'] ?? 'segmented_pills'),
        'label' => (string) ($template['default_label'] ?? ''),
        'template_id' => (string) ($template['id'] ?? ''),
        'bg_image_file' => (string) ($template['bg_image_file'] ?? ''),
        'bg_overlay_color' => (string) ($template['bg_overlay_color'] ?? '#000000'),
        'bg_overlay_opacity' => (int) ($template['bg_overlay_opacity'] ?? 0),
    ];
}

function timer_template_clear_default(PDO $pdo, int $workspaceId, ?string $exceptId = null): void
{
    if ($exceptId !== null && preg_match('/^[a-f0-9]{32}$/', $exceptId)) {
        $pdo->prepare('UPDATE timer_templates SET is_default = 0 WHERE workspace_id = ? AND id != ?')->execute([$workspaceId, $exceptId]);
    } else {
        $pdo->prepare('UPDATE timer_templates SET is_default = 0 WHERE workspace_id = ?')->execute([$workspaceId]);
    }
}

function timer_template_delete_assets(string $relativePath): void
{
    $abs = timer_resolve_asset_absolute($relativePath);
    if ($abs !== null && is_file($abs)) {
        @unlink($abs);
    }
}
