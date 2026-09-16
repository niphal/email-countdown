<?php

declare(strict_types=1);

/**
 * New competitor-style layout drawers (unit-aware).
 */

/**
 * @param \GdImage|resource $im
 * @param list<array{key:string,label:string,value:string,max:int}> $units
 */
function timer_draw_digit_outline($im, string $fontPath, string $labelFont, int $colAc, int $colMuted, int $colFg, int $colSep, array $units, string $sub, int $fontSizeMain, int $fontSizeUnit, float $spacing, string $sepGlyph, float $yShift, float $labelShift): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $cy = $h * (0.42 + $yShift * 0.08);
    $labelY = $h * (0.62 + $labelShift * 0.08);
    $n = max(1, count($units));
    $slot = ($w * 0.82) / $n;
    $start = $w * 0.09;
    $digitSize = (int) max(18, min($fontSizeMain * 1.15, $slot * 0.42 * $spacing));
    for ($i = 0; $i < $n; $i++) {
        $cx = $start + $slot * ($i + 0.5);
        timer_draw_ttf_outline_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colAc, $cx, $cy, 2);
        draw_ttf_centered($im, $labelFont, $fontSizeUnit, $units[$i]['label'], $colMuted, $cx, $labelY);
        if ($i < $n - 1 && $sepGlyph !== ' ') {
            draw_ttf_centered($im, $fontPath, (int) ($digitSize * 0.55), $sepGlyph, $colSep, $start + $slot * ($i + 1), $cy);
        }
    }
    draw_ttf_centered($im, $labelFont, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.86);
}

/**
 * @param \GdImage|resource $im
 * @param list<array{key:string,label:string,value:string,max:int}> $units
 */
function timer_draw_digit_plain($im, string $fontPath, string $labelFont, int $colAc, int $colMuted, int $colFg, int $colSep, array $units, string $sub, int $fontSizeMain, int $fontSizeUnit, float $spacing, string $sepGlyph, float $sepSize, float $yShift, float $labelShift): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $cy = $h * (0.40 + $yShift * 0.08);
    $labelY = $h * (0.62 + $labelShift * 0.08);
    $n = max(1, count($units));
    $slot = ($w * 0.84) / $n;
    $start = $w * 0.08;
    $digitSize = (int) max(18, min($fontSizeMain * 1.2, $slot * 0.48 * $spacing));
    for ($i = 0; $i < $n; $i++) {
        $cx = $start + $slot * ($i + 0.5);
        draw_ttf_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colAc, $cx, $cy);
        draw_ttf_centered($im, $labelFont, $fontSizeUnit, $units[$i]['label'], $colMuted, $cx, $labelY);
        if ($i < $n - 1 && $sepGlyph !== ' ') {
            $sepFs = (int) max(10, $digitSize * 0.5 * $sepSize);
            if ($sepGlyph === ':') {
                draw_ttf_centered($im, $fontPath, $sepFs, ':', $colSep, $start + $slot * ($i + 1), $cy - $h * 0.02);
            } else {
                imagefilledellipse($im, (int) ($start + $slot * ($i + 1)), (int) ($cy - 4), 5, 5, $colSep);
                imagefilledellipse($im, (int) ($start + $slot * ($i + 1)), (int) ($cy + 6), 5, 5, $colSep);
            }
        }
    }
    draw_ttf_centered($im, $labelFont, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.86);
}

/**
 * @param \GdImage|resource $im
 * @param list<array{key:string,label:string,value:string,max:int}> $units
 */
function timer_draw_flip_blocks($im, string $fontPath, string $labelFont, int $colAc, int $colInk, int $colMuted, int $colFg, array $units, string $sub, int $fontSizeMain, int $fontSizeUnit, float $spacing, float $yShift, float $labelShift): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $n = max(1, count($units));
    $pad = (int) max(10, $w * 0.03);
    $gap = (int) max(6, round(8 * $spacing));
    $boxW = (int) floor(($w - $pad * 2 - $gap * ($n - 1)) / $n);
    $top = (int) ($h * (0.12 + $yShift * 0.06));
    $bottom = (int) ($h * (0.68 + $yShift * 0.04));
    $digitSize = (int) max(16, min($fontSizeMain, $boxW * 0.45));
    for ($i = 0; $i < $n; $i++) {
        $x1 = $pad + ($boxW + $gap) * $i;
        $x2 = $x1 + $boxW;
        timer_fill_rounded_rect($im, $x1, $top, $x2, $bottom, 10, $colAc);
        $midY = (int) (($top + $bottom) / 2);
        imagefilledrectangle($im, $x1 + 4, $midY - 1, $x2 - 4, $midY + 1, $colInk);
        draw_ttf_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colInk, ($x1 + $x2) / 2, ($top + $bottom) / 2 - 2);
        draw_ttf_centered($im, $labelFont, $fontSizeUnit, $units[$i]['label'], $colMuted, ($x1 + $x2) / 2, $h * (0.78 + $labelShift * 0.05));
    }
    draw_ttf_centered($im, $labelFont, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.92);
}

/**
 * @param \GdImage|resource $im
 * @param list<array{key:string,label:string,value:string,max:int}> $units
 */
function timer_draw_outline_blocks($im, string $fontPath, string $labelFont, int $colAc, int $colPanel, int $colMuted, int $colFg, array $units, string $sub, int $fontSizeMain, int $fontSizeUnit, float $spacing, float $yShift, float $labelShift): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $n = max(1, count($units));
    $pad = (int) max(10, $w * 0.03);
    $gap = (int) max(6, round(8 * $spacing));
    $boxW = (int) floor(($w - $pad * 2 - $gap * ($n - 1)) / $n);
    $top = (int) ($h * (0.12 + $yShift * 0.06));
    $bottom = (int) ($h * (0.68 + $yShift * 0.04));
    $digitSize = (int) max(16, min($fontSizeMain, $boxW * 0.45));
    for ($i = 0; $i < $n; $i++) {
        $x1 = $pad + ($boxW + $gap) * $i;
        $x2 = $x1 + $boxW;
        timer_fill_rounded_rect($im, $x1, $top, $x2, $bottom, 10, $colAc);
        timer_fill_rounded_rect($im, $x1 + 3, $top + 3, $x2 - 3, $bottom - 3, 8, $colPanel);
        draw_ttf_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colAc, ($x1 + $x2) / 2, ($top + $bottom) / 2 - 2);
        draw_ttf_centered($im, $labelFont, $fontSizeUnit, $units[$i]['label'], $colMuted, ($x1 + $x2) / 2, $h * (0.78 + $labelShift * 0.05));
    }
    draw_ttf_centered($im, $labelFont, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.92);
}

/**
 * @param \GdImage|resource $im
 * @param list<array{key:string,label:string,value:string,max:int}> $units
 */
function timer_draw_pill_bar($im, string $fontPath, string $labelFont, int $colAc, int $colInk, int $colMuted, int $colFg, array $units, string $sub, int $fontSizeMain, int $fontSizeUnit, float $spacing, string $sepGlyph, float $yShift, float $labelShift): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $pad = (int) max(12, $w * 0.04);
    $top = (int) ($h * (0.14 + $yShift * 0.05));
    $bottom = (int) ($h * (0.62 + $yShift * 0.04));
    $radius = (int) (($bottom - $top) / 2);
    timer_fill_rounded_rect($im, $pad, $top, $w - $pad, $bottom, $radius, $colAc);
    $n = max(1, count($units));
    $inner = $w - $pad * 2;
    $slot = $inner / $n;
    $digitSize = (int) max(16, min($fontSizeMain, $slot * 0.38 * $spacing));
    $cy = ($top + $bottom) / 2;
    for ($i = 0; $i < $n; $i++) {
        $cx = $pad + $slot * ($i + 0.5);
        draw_ttf_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colInk, $cx, $cy - $h * 0.04);
        draw_ttf_centered($im, $labelFont, max(8, (int) ($fontSizeUnit * 0.9)), $units[$i]['label'], $colInk, $cx, $cy + $h * 0.12);
        if ($i < $n - 1 && $sepGlyph !== ' ') {
            draw_ttf_centered($im, $fontPath, (int) ($digitSize * 0.45), $sepGlyph === ':' ? ':' : '·', $colInk, $pad + $slot * ($i + 1), $cy - $h * 0.02);
        }
    }
    draw_ttf_centered($im, $labelFont, $fontSizeUnit, $sub, $colFg, $w / 2, $h * (0.82 + $labelShift * 0.04));
}

/**
 * @param \GdImage|resource $im
 * @param list<array{key:string,label:string,value:string,max:int}> $units
 */
function timer_draw_ring_units($im, string $fontPath, string $labelFont, int $colAc, int $colPanel, int $colMuted, int $colFg, array $units, string $sub, int $fontSizeMain, int $fontSizeUnit, float $spacing, float $yShift, float $labelShift, bool $wedge): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    $n = max(1, count($units));
    $pad = (int) max(10, $w * 0.04);
    $gap = (int) max(8, round(10 * $spacing));
    $cell = (int) floor(($w - $pad * 2 - $gap * ($n - 1)) / $n);
    $cy = (int) ($h * (0.40 + $yShift * 0.06));
    $digitSize = (int) max(12, min($fontSizeMain * 0.7, $cell * 0.28));
    for ($i = 0; $i < $n; $i++) {
        $cx = (int) ($pad + ($cell + $gap) * $i + $cell / 2);
        $r = (int) min($cell * 0.42, $h * 0.32);
        $progress = timer_design_unit_progress($units[$i]);
        if ($wedge) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $colPanel);
            $deg = (int) round(360 * $progress);
            if ($deg > 0) {
                imagefilledarc($im, $cx, $cy, $r * 2, $r * 2, -90, -90 + $deg, $colAc, IMG_ARC_PIE);
            }
            imagefilledellipse($im, $cx, $cy, (int) ($r * 1.15), (int) ($r * 1.15), $colPanel);
        } else {
            imagesetthickness($im, 3);
            imageellipse($im, $cx, $cy, $r * 2, $r * 2, $colPanel);
            $deg = (int) round(360 * $progress);
            if ($deg > 0) {
                imagesetthickness($im, 4);
                imagearc($im, $cx, $cy, $r * 2, $r * 2, -90, -90 + max(1, $deg), $colAc);
            }
            imagesetthickness($im, 1);
        }
        draw_ttf_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colAc, $cx, $cy);
        draw_ttf_centered($im, $labelFont, $fontSizeUnit, $units[$i]['label'], $colMuted, $cx, $h * (0.72 + $labelShift * 0.05));
    }
    draw_ttf_centered($im, $labelFont, $fontSizeUnit, $sub, $colFg, $w / 2, $h * 0.90);
}

/**
 * @param \GdImage|resource $im
 */
function timer_draw_ttf_outline_centered($im, string $font, int $size, string $text, int $color, float $cx, float $cy, int $stroke = 2): void
{
    $box = imagettfbbox($size, 0, $font, $text);
    if ($box === false) {
        return;
    }
    $tw = (int) abs($box[2] - $box[0]);
    $th = (int) abs($box[7] - $box[1]);
    $x = (int) ($cx - $tw / 2);
    $y = (int) ($cy + $th / 2);
    for ($ox = -$stroke; $ox <= $stroke; $ox++) {
        for ($oy = -$stroke; $oy <= $stroke; $oy++) {
            if ($ox === 0 && $oy === 0) {
                continue;
            }
            imagettftext($im, $size, 0, $x + $ox, $y + $oy, $color, $font, $text);
        }
    }
}
