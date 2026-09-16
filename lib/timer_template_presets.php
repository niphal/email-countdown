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
            'description' => 'Dark navy with a sharp coral countdown — classic flash-sale energy.',
            'category' => 'Promo',
            'bg_color' => '#0f172a',
            'text_color' => '#e2e8f0',
            'accent_color' => '#f43f5e',
            'default_label' => 'Ends soon · Free shipping',
            'width' => 520,
            'height' => 130,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 40,
        ],
        'clean_commerce' => [
            'name' => 'Clean commerce',
            'description' => 'Bright storefront look with calm blue digits for everyday campaigns.',
            'category' => 'Retail',
            'bg_color' => '#f8fafc',
            'text_color' => '#0f172a',
            'accent_color' => '#2563eb',
            'default_label' => 'Shop the sale',
            'width' => 520,
            'height' => 120,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 32,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#ffffff',
            'bg_overlay_opacity' => 10,
        ],
        'forest_gold' => [
            'name' => 'Forest & gold',
            'description' => 'Deep green with soft gold accents — premium seasonal offers.',
            'category' => 'Luxury',
            'bg_color' => '#052e16',
            'text_color' => '#ecfdf5',
            'accent_color' => '#d4a373',
            'default_label' => 'Members early access',
            'width' => 540,
            'height' => 140,
            'font_key' => 'roboto_bold',
            'font_size_main' => 36,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#022c22',
            'bg_overlay_opacity' => 35,
        ],
        'sunset_flash' => [
            'name' => 'Sunset flash',
            'description' => 'Warm amber gradient feel with a bold badge countdown.',
            'category' => 'Promo',
            'bg_color' => '#7c2d12',
            'text_color' => '#fff7ed',
            'accent_color' => '#fbbf24',
            'default_label' => 'Today only',
            'width' => 500,
            'height' => 128,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 33,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#431407',
            'bg_overlay_opacity' => 30,
        ],
        'slate_signal' => [
            'name' => 'Slate signal',
            'description' => 'Charcoal with cyan progress — product launches and webinars.',
            'category' => 'Product',
            'bg_color' => '#111827',
            'text_color' => '#f9fafb',
            'accent_color' => '#22d3ee',
            'default_label' => 'Launch window closes',
            'width' => 540,
            'height' => 136,
            'font_key' => 'roboto_bold',
            'font_size_main' => 30,
            'layout_key' => 'progress_hybrid',
            'bg_overlay_color' => '#030712',
            'bg_overlay_opacity' => 25,
        ],
        'rose_boutique' => [
            'name' => 'Rose boutique',
            'description' => 'Soft blush canvas with deep rose digits for fashion and beauty.',
            'category' => 'Retail',
            'bg_color' => '#fff1f2',
            'text_color' => '#4c0519',
            'accent_color' => '#e11d48',
            'default_label' => 'New arrivals · Limited',
            'width' => 500,
            'height' => 118,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 30,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#ffe4e6',
            'bg_overlay_opacity' => 15,
        ],
        'electric_night' => [
            'name' => 'Electric night',
            'description' => 'Near-black with electric blue pills — gaming, tech, and drops.',
            'category' => 'Product',
            'bg_color' => '#020617',
            'text_color' => '#e0f2fe',
            'accent_color' => '#3b82f6',
            'default_label' => 'Drop ends at midnight',
            'width' => 520,
            'height' => 132,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 35,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 45,
        ],
        'parchment_edit' => [
            'name' => 'Parchment editorial',
            'description' => 'Warm paper tone with ink typography for newsletters and magazines.',
            'category' => 'Editorial',
            'bg_color' => '#f5f0e6',
            'text_color' => '#1c1917',
            'accent_color' => '#0f766e',
            'default_label' => 'Issue closes',
            'width' => 520,
            'height' => 114,
            'font_key' => 'open_sans',
            'font_size_main' => 28,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#e7e5e4',
            'bg_overlay_opacity' => 10,
        ],
        'ocean_tide' => [
            'name' => 'Ocean tide',
            'description' => 'Deep teal split layout — travel, wellness, and subscription offers.',
            'category' => 'Retail',
            'bg_color' => '#134e4a',
            'text_color' => '#ccfbf1',
            'accent_color' => '#5eead4',
            'default_label' => 'Book before it sails',
            'width' => 540,
            'height' => 138,
            'font_key' => 'roboto_bold',
            'font_size_main' => 34,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#042f2e',
            'bg_overlay_opacity' => 30,
        ],
        'holiday_luxe' => [
            'name' => 'Holiday luxe',
            'description' => 'Burgundy and gold badge style for festive gifting campaigns.',
            'category' => 'Luxury',
            'bg_color' => '#4c0519',
            'text_color' => '#fff1f2',
            'accent_color' => '#eab308',
            'default_label' => 'Gift by this deadline',
            'width' => 520,
            'height' => 130,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 32,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#1f0208',
            'bg_overlay_opacity' => 35,
        ],
        'arctic_mint' => [
            'name' => 'Arctic mint',
            'description' => 'Cool mint canvas with deep teal digits — wellness and spring drops.',
            'category' => 'Retail',
            'bg_color' => '#ecfdf5',
            'text_color' => '#064e3b',
            'accent_color' => '#0d9488',
            'default_label' => 'Fresh drop ends',
            'width' => 520,
            'height' => 120,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 32,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#ccfbf1',
            'bg_overlay_opacity' => 12,
        ],
        'neon_violet' => [
            'name' => 'Neon violet',
            'description' => 'Night-club purple with electric magenta pills for launches and tickets.',
            'category' => 'Product',
            'bg_color' => '#1e0533',
            'text_color' => '#f5e1ff',
            'accent_color' => '#e879f9',
            'default_label' => 'Doors close soon',
            'width' => 540,
            'height' => 136,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#0f021a',
            'bg_overlay_opacity' => 40,
        ],
        'charcoal_amber' => [
            'name' => 'Charcoal amber',
            'description' => 'Warm amber countdown on charcoal — webinars and B2B offers.',
            'category' => 'Product',
            'bg_color' => '#1c1917',
            'text_color' => '#fafaf9',
            'accent_color' => '#f59e0b',
            'default_label' => 'Registration closes',
            'width' => 520,
            'height' => 128,
            'font_key' => 'roboto_bold',
            'font_size_main' => 33,
            'layout_key' => 'progress_hybrid',
            'bg_overlay_color' => '#0c0a09',
            'bg_overlay_opacity' => 28,
        ],
        'cotton_candy' => [
            'name' => 'Cotton candy',
            'description' => 'Soft pink-to-sky feel with berry accents for beauty and lifestyle.',
            'category' => 'Retail',
            'bg_color' => '#fdf2f8',
            'text_color' => '#831843',
            'accent_color' => '#db2777',
            'default_label' => 'Beauty week ends',
            'width' => 500,
            'height' => 118,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 30,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#fce7f3',
            'bg_overlay_opacity' => 14,
        ],
        'espresso_cream' => [
            'name' => 'Espresso cream',
            'description' => 'Coffee-house browns with cream type for cafés and culinary brands.',
            'category' => 'Editorial',
            'bg_color' => '#292524',
            'text_color' => '#faf7f2',
            'accent_color' => '#d6b48a',
            'default_label' => 'Seasonal menu ends',
            'width' => 520,
            'height' => 124,
            'font_key' => 'open_sans',
            'font_size_main' => 30,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#1c1917',
            'bg_overlay_opacity' => 30,
        ],
        'emerald_city' => [
            'name' => 'Emerald city',
            'description' => 'Bold emerald with bright lime digits — eco and outdoor campaigns.',
            'category' => 'Promo',
            'bg_color' => '#064e3b',
            'text_color' => '#ecfdf5',
            'accent_color' => '#a3e635',
            'default_label' => 'Outdoor sale ends',
            'width' => 540,
            'height' => 134,
            'font_key' => 'roboto_bold',
            'font_size_main' => 35,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#022c22',
            'bg_overlay_opacity' => 32,
        ],
        'steel_blue' => [
            'name' => 'Steel blue',
            'description' => 'Corporate blue-gray with crisp white digits for SaaS and finance.',
            'category' => 'Product',
            'bg_color' => '#1e293b',
            'text_color' => '#f8fafc',
            'accent_color' => '#60a5fa',
            'default_label' => 'Offer expires',
            'width' => 520,
            'height' => 126,
            'font_key' => 'roboto_bold',
            'font_size_main' => 32,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#0f172a',
            'bg_overlay_opacity' => 25,
        ],
        'lava_night' => [
            'name' => 'Lava night',
            'description' => 'Near-black with molten red countdown for urgency and gaming.',
            'category' => 'Promo',
            'bg_color' => '#0a0a0a',
            'text_color' => '#fee2e2',
            'accent_color' => '#ef4444',
            'default_label' => 'Last chance',
            'width' => 520,
            'height' => 130,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 36,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => 45,
        ],
        'sage_linen' => [
            'name' => 'Sage linen',
            'description' => 'Soft sage and linen neutrals for home, garden, and lifestyle.',
            'category' => 'Retail',
            'bg_color' => '#f4f5f0',
            'text_color' => '#3f4a3c',
            'accent_color' => '#6b8f71',
            'default_label' => 'Collection ends',
            'width' => 500,
            'height' => 116,
            'font_key' => 'open_sans',
            'font_size_main' => 28,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#e8ebe3',
            'bg_overlay_opacity' => 10,
        ],
        'grape_soda' => [
            'name' => 'Grape soda',
            'description' => 'Playful violet retail look with candy-bright accents.',
            'category' => 'Promo',
            'bg_color' => '#4c1d95',
            'text_color' => '#f5f3ff',
            'accent_color' => '#c4b5fd',
            'default_label' => 'Weekend deal ends',
            'width' => 520,
            'height' => 128,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 33,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#2e1065',
            'bg_overlay_opacity' => 30,
        ],
        'monochrome_press' => [
            'name' => 'Monochrome press',
            'description' => 'Ink-black on white for newsletters and press-style emails.',
            'category' => 'Editorial',
            'bg_color' => '#ffffff',
            'text_color' => '#111111',
            'accent_color' => '#111111',
            'default_label' => 'Issue deadline',
            'width' => 520,
            'height' => 112,
            'font_key' => 'roboto',
            'font_size_main' => 30,
            'layout_key' => 'minimal_editorial',
            'bg_overlay_color' => '#f5f5f5',
            'bg_overlay_opacity' => 8,
        ],
        'citrus_pop' => [
            'name' => 'Citrus pop',
            'description' => 'Zesty yellow field with lime-green digits for summer promos.',
            'category' => 'Promo',
            'bg_color' => '#fef08a',
            'text_color' => '#422006',
            'accent_color' => '#65a30d',
            'default_label' => 'Summer sale ends',
            'width' => 520,
            'height' => 130,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#fde047',
            'bg_overlay_opacity' => 15,
        ],
        'indigo_ink' => [
            'name' => 'Indigo ink',
            'description' => 'Deep indigo with soft sky accents — education and membership.',
            'category' => 'Product',
            'bg_color' => '#1e1b4b',
            'text_color' => '#e0e7ff',
            'accent_color' => '#818cf8',
            'default_label' => 'Enrollment closes',
            'width' => 540,
            'height' => 136,
            'font_key' => 'roboto_bold',
            'font_size_main' => 32,
            'layout_key' => 'progress_hybrid',
            'bg_overlay_color' => '#0f0d2e',
            'bg_overlay_opacity' => 35,
        ],
        'coral_reef' => [
            'name' => 'Coral reef',
            'description' => 'Teal sea base with coral countdown for travel and hospitality.',
            'category' => 'Retail',
            'bg_color' => '#115e59',
            'text_color' => '#f0fdfa',
            'accent_color' => '#fb7185',
            'default_label' => 'Book before it ends',
            'width' => 540,
            'height' => 138,
            'font_key' => 'open_sans_bold',
            'font_size_main' => 34,
            'layout_key' => 'split_emphasis',
            'bg_overlay_color' => '#042f2e',
            'bg_overlay_opacity' => 28,
        ],
        'winter_frost' => [
            'name' => 'Winter frost',
            'description' => 'Icy blues and silver digits for cold-weather and holiday pre-sales.',
            'category' => 'Luxury',
            'bg_color' => '#e0f2fe',
            'text_color' => '#0c4a6e',
            'accent_color' => '#0369a1',
            'default_label' => 'Frost sale ends',
            'width' => 520,
            'height' => 122,
            'font_key' => 'noto_sans_bold',
            'font_size_main' => 31,
            'layout_key' => 'segmented_pills',
            'bg_overlay_color' => '#bae6fd',
            'bg_overlay_opacity' => 18,
        ],
        'marketplace_grid' => [
            'name' => 'Marketplace grid',
            'description' => 'Clean white retail with marketplace orange — flash deals and SKUs pushes.',
            'category' => 'Retail',
            'bg_color' => '#ffffff',
            'text_color' => '#1f2937',
            'accent_color' => '#ea580c',
            'default_label' => 'Deal ends soon',
            'width' => 520,
            'height' => 120,
            'font_key' => 'roboto_bold',
            'font_size_main' => 32,
            'layout_key' => 'badge_countdown',
            'bg_overlay_color' => '#fff7ed',
            'bg_overlay_opacity' => 10,
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
        'rose_boutique',
        'electric_night',
        'parchment_edit',
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
        is_default, created_at, updated_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id, $workspaceId, $payload['name'], $payload['description'], $payload['bg_color'], $payload['text_color'],
        $payload['accent_color'], $payload['default_label'], $payload['width'], $payload['height'],
        $payload['font_key'], $payload['font_size_main'], $payload['layout_key'], '',
        $payload['bg_overlay_color'], $payload['bg_overlay_opacity'], $payload['is_default'], $now, $now,
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
