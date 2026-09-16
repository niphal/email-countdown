<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/lib/timer_templates.php';
require_once dirname(__DIR__) . '/lib/timer_template_presets.php';
require_once dirname(__DIR__) . '/lib/timer_fonts.php';
require_once dirname(__DIR__) . '/lib/timer_design.php';
require_once dirname(__DIR__) . '/timer.php';

auth_start_session();
auth_require_api_login();

$workspaceId = auth_workspace_id();

/**
 * @return array<string, mixed>
 */
function template_preview_style_from_request(int $workspaceId): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $raw = file_get_contents('php://input') ?: '{}';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }
        if (!empty($body['preset_key']) && is_string($body['preset_key'])) {
            $preset = timer_template_preset_get($body['preset_key']);
            if ($preset === null) {
                http_response_code(404);
                header('Content-Type: text/plain');
                echo 'Unknown preset';
                exit;
            }

            return array_merge($preset, ['bg_image_file' => '']);
        }
        if (trim((string) ($body['name'] ?? '')) === '') {
            $body['name'] = 'Preview';
        }
        if (!isset($body['default_label']) && isset($body['label'])) {
            $body['default_label'] = $body['label'];
        }
        $payload = timer_template_sanitize_payload($body, false);
        $bgImage = (string) ($body['bg_image_file'] ?? '');
        if ($bgImage === '' && !empty($body['id']) && preg_match('/^[a-f0-9]{32}$/', (string) $body['id'])) {
            $tpl = timer_template_get(db(), $workspaceId, (string) $body['id']);
            if ($tpl !== null) {
                $bgImage = (string) ($tpl['bg_image_file'] ?? '');
            }
        }

        return array_merge($payload, ['bg_image_file' => $bgImage]);
    }

    $presetKey = (string) ($_GET['preset'] ?? '');
    if ($presetKey !== '') {
        $preset = timer_template_preset_get($presetKey);
        if ($preset === null) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Unknown preset';
            exit;
        }

        return array_merge($preset, ['bg_image_file' => '']);
    }

    $id = (string) ($_GET['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        http_response_code(422);
        header('Content-Type: text/plain');
        echo 'Bad id';
        exit;
    }
    $tpl = timer_template_get(db(), $workspaceId, $id);
    if ($tpl === null) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Not found';
        exit;
    }

    return $tpl;
}

$style = template_preview_style_from_request($workspaceId);

if (!timer_gd_has_freetype()) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'GD FreeType required';
    exit;
}

$fontPath = timer_ensure_ttf_path((string) ($style['font_key'] ?? 'noto_sans_bold'));
if ($fontPath === null) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Fonts unavailable';
    exit;
}

$design = timer_design_normalize($style['design'] ?? $style['design_json'] ?? []);
$labelFontPath = timer_ensure_ttf_path((string) ($design['label_font_key'] ?? 'open_sans')) ?: $fontPath;

$w = (int) ($style['width'] ?? 480);
$h = (int) ($style['height'] ?? 120);
$bg = parse_hex((string) ($style['bg_color'] ?? '#1a1a2e'));
$fg = parse_hex((string) ($style['text_color'] ?? '#eaeaea'));
$ac = parse_hex((string) ($style['accent_color'] ?? '#e94560'));
$label = (string) ($style['default_label'] ?? $style['label'] ?? 'Brand preview');
$fontSizeMain = (int) ($style['font_size_main'] ?? 32);
$layoutKey = (string) ($style['layout_key'] ?? 'segmented_pills');
$bgImage = (string) ($style['bg_image_file'] ?? '');
$overlayColor = (string) ($style['bg_overlay_color'] ?? '#000000');
$overlayOpacity = (int) ($style['bg_overlay_opacity'] ?? 35);
$transparent = ($design['bg_mode'] ?? 'solid') === 'transparent';
if ($transparent) {
    $bg = [248, 250, 252];
}

$now = time();
$endsAt = $now + 86400 * 2 + 3600 * 5 + 60 * 12 + 34;
$createdAt = $now - 86400;
$remaining = max(0, $endsAt - $now);
$deadlineLabel = app_format_deadline_label($endsAt);

header('Content-Type: image/png');
header('Cache-Control: no-store');
$im = render_timer_frame(
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
    $endsAt,
    $deadlineLabel,
    true,
    $bgImage,
    $overlayColor,
    $overlayOpacity,
    null,
    false,
    $design,
    $labelFontPath
);
imagepng($im);
imagedestroy($im);
