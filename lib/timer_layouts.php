<?php

declare(strict_types=1);

/** @return list<string> */
function timer_layout_keys(): array
{
    return [
        'segmented_pills',
        'split_emphasis',
        'minimal_editorial',
        'progress_hybrid',
        'badge_countdown',
        'cinema_marquee',
        'duo_blocks',
        'digit_outline',
        'digit_plain',
        'flip_blocks',
        'outline_blocks',
        'pill_bar',
        'ring_arc',
        'ring_wedge',
    ];
}

/** @return array<string, string> */
function timer_layout_labels(): array
{
    return [
        'segmented_pills' => 'Segmented cards',
        'split_emphasis' => 'Hero split',
        'minimal_editorial' => 'Editorial line',
        'progress_hybrid' => 'Progress track',
        'badge_countdown' => 'Ribbon badge',
        'cinema_marquee' => 'Cinema marquee',
        'duo_blocks' => 'Dual blocks',
        'digit_outline' => 'Outlined digits',
        'digit_plain' => 'Plain digits',
        'flip_blocks' => 'Flip blocks',
        'outline_blocks' => 'Outline blocks',
        'pill_bar' => 'Pill bar',
        'ring_arc' => 'Ring arcs',
        'ring_wedge' => 'Ring wedges',
    ];
}

/** @return array<string, string> */
function timer_layout_blurbs(): array
{
    return [
        'segmented_pills' => 'Four soft cards for D/H/M/S',
        'split_emphasis' => 'Giant time with side metric',
        'minimal_editorial' => 'Type-led with hairline rules',
        'progress_hybrid' => 'Digits over a rounded track',
        'badge_countdown' => 'Ribbon + countdown pair',
        'cinema_marquee' => 'Centered with marquee bars',
        'duo_blocks' => 'Days card + clock card',
        'digit_outline' => 'Heavy stroked numerals',
        'digit_plain' => 'Clean bold digits + separators',
        'flip_blocks' => 'Solid tiles with a flip seam',
        'outline_blocks' => 'Stroked tiles on a light field',
        'pill_bar' => 'One capsule for all units',
        'ring_arc' => 'Circular outlines with arcs',
        'ring_wedge' => 'Solid circles with wedges',
    ];
}

function timer_normalize_layout_key(string $key): string
{
    if (in_array($key, timer_layout_keys(), true)) {
        return $key;
    }

    return 'segmented_pills';
}
