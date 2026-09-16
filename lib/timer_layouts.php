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
    ];
}

function timer_normalize_layout_key(string $key): string
{
    if (in_array($key, timer_layout_keys(), true)) {
        return $key;
    }

    return 'segmented_pills';
}
