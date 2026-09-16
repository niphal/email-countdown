<?php

declare(strict_types=1);

require_once __DIR__ . '/timer_fonts.php';
require_once __DIR__ . '/timer_layouts.php';
require_once __DIR__ . '/timer_templates.php';

/**
 * Curated starter brand styles (no background images — colors + layout + type).
 *
 * @return array<string, array<string, mixed>>
 */
function timer_template_presets(): array
{
    return [
        'midnight_urgency' => [
            'name' => 'Midnight urgency',
            'description' => 'Ink navy cards with hot coral digits — classic flash-sale energy.',
            'category' => 'Promo',
            'bg_color' => '#0b1220',
            'text_color' => '#e8eef7',
            'accent_color' => '#ff4d6d',
            'default_label' => 'Ends soon · Free shipping',
            'width' => 560,
            'height' => 148,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 0,
        ],
        'clean_commerce' => [
            'name' => 'Clean commerce',
            'description' => 'Crisp white editorial with calm blue type for everyday retail.',
            'category' => 'Retail',
            'bg_color' => '#ffffff',
            'text_color' => '#0f172a',
            'accent_color' => '#1d4ed8',
            'default_label' => 'Shop the sale',
            'width' => 540,
            'height' => 132,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
        ],
        'forest_gold' => [
            'name' => 'Forest & gold',
            'description' => 'Deep emerald panels with champagne gold — premium seasonal offers.',
            'category' => 'Luxury',
            'bg_color' => '#04160f',
            'text_color' => '#f3faf4',
            'accent_color' => '#d4b483',
            'default_label' => 'Members early access',
            'width' => 560,
            'height' => 150,
            'font_key' => 'roboto_bold',
            'font_size_main' => 38,
            'layout_key' => 'duo_blocks',
            'bg_overlay_color' => '#022c22',
            'bg_overlay_opacity' => 0,
        ],
        'sunset_flash' => [
            'name' => 'Sunset flash',
            'description' => 'Burnt amber ribbon with warm cream type for same-day deals.',
            'category' => 'Promo',
            'bg_color' => '#3b1408',
            'text_color' => '#fff4e6',
            'accent_color' => '#fb923c',
            'default_label' => 'Today only',
            'width' => 540,
            'height' => 140,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#431407',
            'bg_overlay_opacity' => 0,
        ],
        'slate_signal' => [
            'name' => 'Slate signal',
            'description' => 'Graphite field with electric cyan track — launches and webinars.',
            'category' => 'Product',
            'bg_color' => '#0f141b',
            'text_color' => '#f8fafc',
            'accent_color' => '#22d3ee',
            'default_label' => 'Launch window closes',
            'width' => 560,
            'height' => 148,
            'font_key' => 'roboto_bold',
            'font_size_main' => 36,
            'layout_key' => 'progress_hybrid',
            'bg_overlay_color' => '#030712',
            'bg_overlay_opacity' => 0,
        ],
        'rose_boutique' => [
            'name' => 'Rose boutique',
            'description' => 'Blush canvas and rose digits — fashion, beauty, and soft launches.',
            'category' => 'Retail',
            'bg_color' => '#fff1f5',
            'text_color' => '#4a0418',
            'accent_color' => '#e11d48',
            'default_label' => 'New arrivals · Limited',
            'width' => 520,
            'height' => 128,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 32,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#ffe4e6',
            'bg_overlay_opacity' => 0,
        ],
        'electric_night' => [
            'name' => 'Electric night',
            'description' => 'Near-black stage with electric blue cards for gaming and drops.',
            'category' => 'Product',
            'bg_color' => '#05070f',
            'text_color' => '#dbeafe',
            'accent_color' => '#60a5fa',
            'default_label' => 'Drop ends at midnight',
            'width' => 560,
            'height' => 148,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 0,
        ],
        'parchment_edit' => [
            'name' => 'Parchment editorial',
            'description' => 'Warm paper tone with ink rules — newsletters and magazines.',
            'category' => 'Editorial',
            'bg_color' => '#f6f1e7',
            'text_color' => '#1c1917',
            'accent_color' => '#0f766e',
            'default_label' => 'Issue closes',
            'width' => 540,
            'height' => 128,
            'font_key' => 'open_sans',
            'font_size_main' => 30,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#e7e5e4',
            'bg_overlay_opacity' => 0,
        ],
        'ocean_tide' => [
            'name' => 'Ocean tide',
            'description' => 'Deep teal hero split — travel, wellness, and subscriptions.',
            'category' => 'Retail',
            'bg_color' => '#083344',
            'text_color' => '#ecfeff',
            'accent_color' => '#67e8f9',
            'default_label' => 'Book before it sails',
            'width' => 560,
            'height' => 150,
            'font_key' => 'roboto_bold',
            'font_size_main' => 36,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#042f2e',
            'bg_overlay_opacity' => 0,
        ],
        'holiday_luxe' => [
            'name' => 'Holiday luxe',
            'description' => 'Burgundy ribbon and gold digits for festive gifting.',
            'category' => 'Luxury',
            'bg_color' => '#2a0612',
            'text_color' => '#fff1f2',
            'accent_color' => '#eab308',
            'default_label' => 'Gift by this deadline',
            'width' => 540,
            'height' => 142,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#1f0208',
            'bg_overlay_opacity' => 0,
        ],
        'noir_marquee' => [
            'name' => 'Noir marquee',
            'description' => 'Cinema bars and bold white-hot type for premieres and events.',
            'category' => 'Promo',
            'bg_color' => '#09090b',
            'text_color' => '#fafafa',
            'accent_color' => '#f97316',
            'default_label' => 'Doors open soon',
            'width' => 560,
            'height' => 150,
            'font_key' => 'roboto_bold',
            'font_size_main' => 40,
            'layout_key' => 'cinema_marquee',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 0,
        ],
        'ivory_atelier' => [
            'name' => 'Ivory atelier',
            'description' => 'Gallery ivory with charcoal dual blocks — design and craft brands.',
            'category' => 'Luxury',
            'bg_color' => '#faf8f5',
            'text_color' => '#1c1917',
            'accent_color' => '#44403c',
            'default_label' => 'Private sale ends',
            'width' => 560,
            'height' => 146,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'duo_blocks',
            'bg_overlay_color' => '#f5f5f4',
            'bg_overlay_opacity' => 0,
        ],
        'violet_pulse' => [
            'name' => 'Violet pulse',
            'description' => 'Royal violet with neon magenta progress for memberships.',
            'category' => 'Product',
            'bg_color' => '#1b1030',
            'text_color' => '#f5e9ff',
            'accent_color' => '#e879f9',
            'default_label' => 'Enrollment closes',
            'width' => 560,
            'height' => 148,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'progress_hybrid',
            'bg_overlay_color' => '#0f021a',
            'bg_overlay_opacity' => 0,
        ],
        'citrus_market' => [
            'name' => 'Citrus market',
            'description' => 'Sunny lemon field with lime accents for summer markets.',
            'category' => 'Promo',
            'bg_color' => '#fef9c3',
            'text_color' => '#422006',
            'accent_color' => '#65a30d',
            'default_label' => 'Market ends Sunday',
            'width' => 540,
            'height' => 140,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#fde047',
            'bg_overlay_opacity' => 0,
        ],
        'ink_broadsheet' => [
            'name' => 'Ink broadsheet',
            'description' => 'High-contrast black on white with editorial rules.',
            'category' => 'Editorial',
            'bg_color' => '#ffffff',
            'text_color' => '#111111',
            'accent_color' => '#111111',
            'default_label' => 'Deadline tonight',
            'width' => 540,
            'height' => 124,
            'font_key' => 'roboto',
            'font_size_main' => 32,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#f5f5f5',
            'bg_overlay_opacity' => 0,
        ],
        'sapphire_boardroom' => [
            'name' => 'Sapphire boardroom',
            'description' => 'Deep sapphire dual cards for B2B and finance campaigns.',
            'category' => 'Product',
            'bg_color' => '#0b1c3a',
            'text_color' => '#e8eef9',
            'accent_color' => '#93c5fd',
            'default_label' => 'Offer expires',
            'width' => 560,
            'height' => 148,
            'font_key' => 'roboto_bold',
            'font_size_main' => 36,
            'layout_key' => 'duo_blocks',
            'bg_overlay_color' => '#0f172a',
            'bg_overlay_opacity' => 0,
        ],
        'coral_coast' => [
            'name' => 'Coral coast',
            'description' => 'Sea-glass teal with coral ribbon for travel and hospitality.',
            'category' => 'Retail',
            'bg_color' => '#0f3d3a',
            'text_color' => '#ecfeff',
            'accent_color' => '#fb7185',
            'default_label' => 'Book before it ends',
            'width' => 560,
            'height' => 146,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#042f2e',
            'bg_overlay_opacity' => 0,
        ],
        'frost_atelier' => [
            'name' => 'Frost atelier',
            'description' => 'Icy blue marquee — winter sales and cold-weather drops.',
            'category' => 'Luxury',
            'bg_color' => '#e8f4fc',
            'text_color' => '#0c4a6e',
            'accent_color' => '#0284c7',
            'default_label' => 'Frost sale ends',
            'width' => 560,
            'height' => 148,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'cinema_marquee',
            'bg_overlay_color' => '#bae6fd',
            'bg_overlay_opacity' => 0,
        ],
        'espresso_ticket' => [
            'name' => 'Espresso ticket',
            'description' => 'Coffee-house brown with cream split panels for culinary brands.',
            'category' => 'Editorial',
            'bg_color' => '#1c1410',
            'text_color' => '#faf6f1',
            'accent_color' => '#d6b48a',
            'default_label' => 'Seasonal menu ends',
            'width' => 560,
            'height' => 148,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#1c1917',
            'bg_overlay_opacity' => 0,
        ],
        'marketplace_flare' => [
            'name' => 'Marketplace flare',
            'description' => 'Clean white with marketplace orange progress for SKU pushes.',
            'category' => 'Retail',
            'bg_color' => '#ffffff',
            'text_color' => '#1f2937',
            'accent_color' => '#ea580c',
            'default_label' => 'Deal ends soon',
            'width' => 540,
            'height' => 140,
            'font_key' => 'roboto_bold',
            'font_size_main' => 34,
            'layout_key' => 'progress_hybrid',
            'bg_overlay_color' => '#fff7ed',
            'bg_overlay_opacity' => 0,
        ],
        'jade_garden' => [
            'name' => 'Jade garden',
            'description' => 'Soft sage cards with deep jade digits for home and lifestyle.',
            'category' => 'Retail',
            'bg_color' => '#eef3ea',
            'text_color' => '#1f2a1c',
            'accent_color' => '#3f6f4c',
            'default_label' => 'Collection ends',
            'width' => 540,
            'height' => 140,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#e8ebe3',
            'bg_overlay_opacity' => 0,
        ],
        'lava_stage' => [
            'name' => 'Lava stage',
            'description' => 'Pitch black with molten red marquee for urgency campaigns.',
            'category' => 'Promo',
            'bg_color' => '#090909',
            'text_color' => '#fee2e2',
            'accent_color' => '#ef4444',
            'default_label' => 'Last chance',
            'width' => 560,
            'height' => 150,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 40,
            'layout_key' => 'cinema_marquee',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 0,
        ],
        'lilac_salon' => [
            'name' => 'Lilac salon',
            'description' => 'Soft lilac dual blocks for beauty salons and wellness.',
            'category' => 'Retail',
            'bg_color' => '#f5f3ff',
            'text_color' => '#2e1065',
            'accent_color' => '#7c3aed',
            'default_label' => 'Booking window closes',
            'width' => 560,
            'height' => 146,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'duo_blocks',
            'bg_overlay_color' => '#ede9fe',
            'bg_overlay_opacity' => 0,
        ],
        'graphite_brief' => [
            'name' => 'Graphite brief',
            'description' => 'Quiet graphite editorial for newsletters and briefing emails.',
            'category' => 'Editorial',
            'bg_color' => '#1f2937',
            'text_color' => '#f9fafb',
            'accent_color' => '#e5e7eb',
            'default_label' => 'Brief goes live',
            'width' => 540,
            'height' => 130,
            'font_key' => 'roboto',
            'font_size_main' => 30,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#111827',
            'bg_overlay_opacity' => 0,
        ],
        'outline_digits_blue' => [
            'name' => 'Outline digits',
            'description' => 'Heavy stroked numerals — clean email hero countdowns.',
            'category' => 'Product',
            'bg_color' => '#ffffff',
            'text_color' => '#111827',
            'accent_color' => '#2563eb',
            'default_label' => 'Sale ends soon',
            'width' => 520,
            'height' => 120,
            'font_key' => 'roboto_bold',
            'font_size_main' => 42,
            'layout_key' => 'digit_outline',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
            'design' => ['bg_mode' => 'solid', 'separator_style' => 'colon'],
        ],
        'flip_block_azure' => [
            'name' => 'Flip azure',
            'description' => 'Solid flip tiles with a center seam for flash sales.',
            'category' => 'Promo',
            'bg_color' => '#f8fafc',
            'text_color' => '#0f172a',
            'accent_color' => '#1d4ed8',
            'default_label' => 'Ends tonight',
            'width' => 540,
            'height' => 140,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'flip_blocks',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
        ],
        'pill_bar_navy' => [
            'name' => 'Pill bar navy',
            'description' => 'One capsule row for compact product emails.',
            'category' => 'Retail',
            'bg_color' => '#ffffff',
            'text_color' => '#0f172a',
            'accent_color' => '#1e3a8a',
            'default_label' => 'Limited stock',
            'width' => 520,
            'height' => 118,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 32,
            'layout_key' => 'pill_bar',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
        ],
        'ring_arc_sky' => [
            'name' => 'Ring arcs',
            'description' => 'Circular arcs per unit — launches and webinars.',
            'category' => 'Product',
            'bg_color' => '#ffffff',
            'text_color' => '#334155',
            'accent_color' => '#0284c7',
            'default_label' => 'Session starts',
            'width' => 560,
            'height' => 150,
            'font_key' => 'roboto_bold',
            'font_size_main' => 28,
            'layout_key' => 'ring_arc',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
        ],
        'ring_wedge_cobalt' => [
            'name' => 'Ring wedges',
            'description' => 'Filled circular wedges for modern product drops.',
            'category' => 'Product',
            'bg_color' => '#f1f5f9',
            'text_color' => '#0f172a',
            'accent_color' => '#1d4ed8',
            'default_label' => 'Drop window',
            'width' => 560,
            'height' => 150,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 28,
            'layout_key' => 'ring_wedge',
            'bg_overlay_color' => '#e2e8f0',
            'bg_overlay_opacity' => 0,
        ],
        'plain_ink' => [
            'name' => 'Plain ink',
            'description' => 'Bold plain digits with dotted separators.',
            'category' => 'Editorial',
            'bg_color' => '#ffffff',
            'text_color' => '#111111',
            'accent_color' => '#111111',
            'default_label' => 'Closes today',
            'width' => 500,
            'height' => 110,
            'font_key' => 'roboto_bold',
            'font_size_main' => 40,
            'layout_key' => 'digit_plain',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
            'design' => ['separator_style' => 'dots'],
        ],
        'outline_blocks_sky' => [
            'name' => 'Outline blocks',
            'description' => 'Stroked tiles on a light canvas for retail.',
            'category' => 'Retail',
            'bg_color' => '#ffffff',
            'text_color' => '#1e293b',
            'accent_color' => '#2563eb',
            'default_label' => 'Shop the deal',
            'width' => 540,
            'height' => 136,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'outline_blocks',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 0,
        ],
    ];
}

/** @return list<string> First-wave presets used when seeding a new workspace. */
function timer_template_core_preset_keys(): array
{
    return [
        'midnight_urgency',
        'clean_commerce',
        'forest_gold',
        'sunset_flash',
        'slate_signal',
        'noir_marquee',
        'ivory_atelier',
        'violet_pulse',
        'ocean_tide',
        'holiday_luxe',
    ];
}

/**
 * @return array<string, mixed>|null
 */
function timer_template_preset_get(string $key): ?array
{
    $all = timer_template_presets();
    if (!isset($all[$key])) {
        return null;
    }
    $p = $all[$key];
    $p['preset_key'] = $key;
    $p['font_key'] = timer_normalize_font_key((string) $p['font_key']);
    $p['layout_key'] = timer_normalize_layout_key((string) $p['layout_key']);

    return $p;
}

/**
 * @return list<array<string, mixed>>
 */
function timer_template_presets_list(): array
{
    $out = [];
    foreach (timer_template_presets() as $key => $p) {
        $row = $p;
        $row['preset_key'] = $key;
        $row['layout_label'] = timer_layout_labels()[$row['layout_key']] ?? $row['layout_key'];
        $out[] = $row;
    }

    return $out;
}

/**
 * Insert a preset as a workspace template. Returns the new row.
 *
 * @param array<string, mixed>|null $preset
 * @return array<string, mixed>
 */
function timer_template_insert_from_preset(PDO $pdo, int $workspaceId, string $presetKey, ?array $preset = null, bool $asDefault = false): array
{
    $preset = $preset ?? timer_template_preset_get($presetKey);
    if ($preset === null) {
        throw new InvalidArgumentException('Unknown preset');
    }
    $payload = timer_template_sanitize_payload($preset, true);
    if ($asDefault) {
        $payload['is_default'] = 1;
        timer_template_clear_default($pdo, $workspaceId);
    }
    $id = bin2hex(random_bytes(16));
    $now = time();
    $stmt = $pdo->prepare('INSERT INTO timer_templates (
        id, workspace_id, name, description, bg_color, text_color, accent_color, default_label,
        width, height, font_key, font_size_main, layout_key, bg_image_file, bg_overlay_color, bg_overlay_opacity,
        is_default, created_at, updated_at, design_json
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id, $workspaceId, $payload['name'], $payload['description'], $payload['bg_color'], $payload['text_color'],
        $payload['accent_color'], $payload['default_label'], $payload['width'], $payload['height'],
        $payload['font_key'], $payload['font_size_main'], $payload['layout_key'], '',
        $payload['bg_overlay_color'], $payload['bg_overlay_opacity'], $payload['is_default'], $now, $now,
        $payload['design_json'],
    ]);
    $row = timer_template_get($pdo, $workspaceId, $id);
    if ($row === null) {
        throw new RuntimeException('Could not create template from preset');
    }

    return $row;
}

/**
 * Seed starter presets into a workspace. Skips names that already exist.
 *
 * @param list<string>|null $onlyKeys If set, only these preset keys are considered.
 * @return array{added: int, skipped: int, templates: list<array<string, mixed>>}
 */
function timer_template_seed_presets(PDO $pdo, int $workspaceId, bool $onlyIfEmpty = false, ?array $onlyKeys = null): array
{
    $all = timer_template_presets();
    if ($onlyKeys !== null) {
        $filtered = [];
        foreach ($onlyKeys as $key) {
            if (isset($all[$key])) {
                $filtered[$key] = $all[$key];
            }
        }
        $all = $filtered;
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM timer_templates WHERE workspace_id = ?');
    $countStmt->execute([$workspaceId]);
    $existingCount = (int) $countStmt->fetchColumn();
    if ($onlyIfEmpty && $existingCount > 0) {
        return ['added' => 0, 'skipped' => count($all), 'templates' => []];
    }

    $nameStmt = $pdo->prepare('SELECT name FROM timer_templates WHERE workspace_id = ?');
    $nameStmt->execute([$workspaceId]);
    $have = [];
    foreach ($nameStmt->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $have[mb_strtolower((string) $n)] = true;
    }

    $added = 0;
    $skipped = 0;
    $created = [];
    foreach ($all as $key => $preset) {
        $nameKey = mb_strtolower((string) $preset['name']);
        if (isset($have[$nameKey])) {
            $skipped++;
            continue;
        }
        $asDefault = $existingCount === 0 && $added === 0;
        $row = timer_template_insert_from_preset($pdo, $workspaceId, $key, $preset, $asDefault);
        $created[] = $row;
        $have[$nameKey] = true;
        $added++;
    }

    return ['added' => $added, 'skipped' => $skipped, 'templates' => $created];
}
