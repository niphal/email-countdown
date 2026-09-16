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
    $footerBand = (int) max(28, round($h * 0.22));
    $labelBand = (int) max(24, round($h * 0.18));
    $topPad = (int) max(12, round($h * 0.10));
    $usableH = max(44, $h - $footerBand - $labelBand - $topPad);
    $padX = (int) max(18, $w * 0.055);
    $gap = (int) max(20, round(22 * $spacing));
    $cell = (int) floor(($w - $padX * 2 - $gap * ($n - 1)) / $n);
    $r = (int) min(floor($cell * 0.32), floor($usableH * 0.38));
    $r = max(18, $r);
    $cy = $topPad + (int) ($usableH / 2) + (int) round($yShift * $h * 0.04);
    $labelY = $cy + $r + (int) max(22, round($h * 0.10)) + (int) round($labelShift * $h * 0.03);
    $digitSize = (int) max(12, min($fontSizeMain * 0.58, $r * 0.68));
    $unitSize = (int) max(8, min($fontSizeUnit + 1, (int) round($labelBand * 0.48)));
    $shortLabels = [
        'days' => 'DAYS',
        'hours' => 'HRS',
        'minutes' => 'MIN',
        'seconds' => 'SEC',
    ];

    $acRgb = timer_gd_color_rgb($im, $colAc);
    $trackRgb = timer_gd_color_rgb($im, $colMuted);
    // Keep track a touch softer than labels
    $trackRgb = [
        (int) round($trackRgb[0] * 0.55 + 200 * 0.45),
        (int) round($trackRgb[1] * 0.55 + 200 * 0.45),
        (int) round($trackRgb[2] * 0.55 + 200 * 0.45),
    ];

    $stroke = max(4.0, $r * 0.145);
    for ($i = 0; $i < $n; $i++) {
        $cx = (float) ($padX + ($cell + $gap) * $i + $cell / 2);
        $progress = timer_design_unit_progress($units[$i]);
        if ($wedge) {
            timer_draw_smooth_wedge_ring($im, $cx, (float) $cy, (float) $r, $stroke, $trackRgb, $acRgb, $progress);
        } else {
            timer_draw_smooth_arc_ring($im, $cx, (float) $cy, (float) $r, $stroke, $trackRgb, $acRgb, $progress);
        }
        draw_ttf_centered($im, $fontPath, $digitSize, $units[$i]['value'], $colAc, $cx, $cy);
        $lab = $shortLabels[$units[$i]['key']] ?? $units[$i]['label'];
        draw_ttf_centered($im, $labelFont, $unitSize, $lab, $colMuted, $cx, $labelY);
    }
    $subY = min($h - 12, max($labelY + (int) round($h * 0.13), (int) round($h * 0.91)));
    draw_ttf_centered($im, $labelFont, $unitSize, $sub, $colFg, $w / 2, $subY);
}

/**
 * @param \GdImage|resource $im
 * @return array{0:int,1:int,2:int}
 */
function timer_gd_color_rgb($im, int $color): array
{
    $c = imagecolorsforindex($im, $color);
    if (!is_array($c)) {
        return [128, 128, 128];
    }

    return [(int) $c['red'], (int) $c['green'], (int) $c['blue']];
}

/**
 * Coverage-based anti-aliased annular progress ring (4x4 subpixel).
 *
 * @param \GdImage|resource $im
 * @param array{0:int,1:int,2:int} $trackRgb
 * @param array{0:int,1:int,2:int} $acRgb
 */
function timer_draw_smooth_arc_ring($im, float $cx, float $cy, float $radius, float $stroke, array $trackRgb, array $acRgb, float $progress): void
{
    $progress = max(0.0, min(1.0, $progress));
    $rOut = $radius + $stroke * 0.5;
    $rIn = max(1.0, $radius - $stroke * 0.5);
    $x0 = (int) floor($cx - $rOut - 2);
    $y0 = (int) floor($cy - $rOut - 2);
    $x1 = (int) ceil($cx + $rOut + 2);
    $y1 = (int) ceil($cy + $rOut + 2);
    $span = $progress * 360.0;

    for ($y = $y0; $y <= $y1; $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $trackCov = 0.0;
            $arcCov = 0.0;
            for ($sy = 0; $sy < 4; $sy++) {
                for ($sx = 0; $sx < 4; $sx++) {
                    $px = $x + ($sx + 0.5) / 4.0;
                    $py = $y + ($sy + 0.5) / 4.0;
                    $dx = $px - $cx;
                    $dy = $py - $cy;
                    $dist = sqrt($dx * $dx + $dy * $dy);
                    if ($dist < $rIn || $dist > $rOut) {
                        continue;
                    }
                    $trackCov += 1.0;
                    if ($span > 0.25) {
                        $theta = fmod(atan2($dx, -$dy) * 180.0 / M_PI + 360.0, 360.0);
                        if ($theta <= $span) {
                            $arcCov += 1.0;
                        }
                    }
                }
            }
            $trackCov /= 16.0;
            $arcCov /= 16.0;
            if ($trackCov > 0.01) {
                timer_blend_pixel($im, $x, $y, $trackRgb, $trackCov * 0.9);
            }
            if ($arcCov > 0.01) {
                timer_blend_pixel($im, $x, $y, $acRgb, $arcCov);
            }
        }
    }
}

/**
 * Soft filled donut with wedge progress (4x4 subpixel AA).
 *
 * @param \GdImage|resource $im
 * @param array{0:int,1:int,2:int} $trackRgb
 * @param array{0:int,1:int,2:int} $acRgb
 */
function timer_draw_smooth_wedge_ring($im, float $cx, float $cy, float $radius, float $stroke, array $trackRgb, array $acRgb, float $progress): void
{
    $progress = max(0.0, min(1.0, $progress));
    $rOut = $radius;
    $rIn = max(1.0, $radius - $stroke * 1.85);
    $x0 = (int) floor($cx - $rOut - 2);
    $y0 = (int) floor($cy - $rOut - 2);
    $x1 = (int) ceil($cx + $rOut + 2);
    $y1 = (int) ceil($cy + $rOut + 2);
    $span = $progress * 360.0;

    for ($y = $y0; $y <= $y1; $y++) {
        for ($x = $x0; $x <= $x1; $x++) {
            $baseCov = 0.0;
            $arcCov = 0.0;
            for ($sy = 0; $sy < 4; $sy++) {
                for ($sx = 0; $sx < 4; $sx++) {
                    $px = $x + ($sx + 0.5) / 4.0;
                    $py = $y + ($sy + 0.5) / 4.0;
                    $dx = $px - $cx;
                    $dy = $py - $cy;
                    $dist = sqrt($dx * $dx + $dy * $dy);
                    if ($dist < $rIn || $dist > $rOut) {
                        continue;
                    }
                    $baseCov += 1.0;
                    if ($span > 0.25) {
                        $theta = fmod(atan2($dx, -$dy) * 180.0 / M_PI + 360.0, 360.0);
                        if ($theta <= $span) {
                            $arcCov += 1.0;
                        }
                    }
                }
            }
            $baseCov /= 16.0;
            $arcCov /= 16.0;
            if ($baseCov > 0.01) {
                timer_blend_pixel($im, $x, $y, $trackRgb, $baseCov * 0.5);
            }
            if ($arcCov > 0.01) {
                timer_blend_pixel($im, $x, $y, $acRgb, $arcCov);
            }
        }
    }
}

/**
 * @param \GdImage|resource $im
 * @param array{0:int,1:int,2:int} $rgb
 */
function timer_blend_pixel($im, int $x, int $y, array $rgb, float $alpha): void
{
    if ($alpha <= 0.02) {
        return;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
        return;
    }
    $a = max(0.0, min(1.0, $alpha));
    if ($a >= 0.98) {
        imagesetpixel($im, $x, $y, ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2]);

        return;
    }
    $existing = imagecolorat($im, $x, $y);
    if ($existing === false) {
        return;
    }
    $er = ($existing >> 16) & 0xFF;
    $eg = ($existing >> 8) & 0xFF;
    $eb = $existing & 0xFF;
    $nr = (int) round($er + ($rgb[0] - $er) * $a);
    $ng = (int) round($eg + ($rgb[1] - $eg) * $a);
    $nb = (int) round($eb + ($rgb[2] - $eb) * $a);
    imagesetpixel($im, $x, $y, ($nr << 16) | ($ng << 8) | $nb);
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
