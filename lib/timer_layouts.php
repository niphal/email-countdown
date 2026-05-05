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
    ];
}

/** @return array<string, string> */
function timer_layout_labels(): array
{
    return [
        'segmented_pills' => 'Segmented Pills',
        'split_emphasis' => 'Split Emphasis',
        'minimal_editorial' => 'Minimal Editorial',
        'progress_hybrid' => 'Progress-Bar Hybrid',
        'badge_countdown' => 'Badge + Countdown',
    ];
}

function timer_normalize_layout_key(string $key): string
{
    if (in_array($key, timer_layout_keys(), true)) {
        return $key;
    }

    return 'segmented_pills';
}

