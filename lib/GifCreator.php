<?php

declare(strict_types=1);

/**
 * Animated GIF from GD image frames (Sybio/GifCreator, PHP 8+ string offsets, GdImage).
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link https://github.com/Sybio/GifCreator
 */
final class GifCreator
{
    private string $gif = '';

    private string $version;

    private bool $imgBuilt = false;

    /** @var list<string> */
    private array $frameSources = [];

    private int $loop = 0;

    private int $dis = 2;

    private int $colour = -1;

    /** If true, omit NETSCAPE loop block (many decoders play the sequence once). */
    private bool $omitNetscapeLoop = false;

    /** @var array<string, string> */
    private array $errors;

    public function __construct()
    {
        $this->version = 'GifCreator';
        $this->errors = [
            'ERR00' => 'Does not supported function for only one image.',
            'ERR01' => 'Source is not a GIF image.',
            'ERR02' => 'You have to give resource image variables, image URL or image binary sources in $frames array.',
            'ERR03' => 'Does not make animation from animated GIF source.',
        ];
        $this->reset();
    }

    public function setOmitNetscapeLoop(bool $omit): void
    {
        $this->omitNetscapeLoop = $omit;
    }

    /**
     * @param list<\GdImage|resource|string> $frames
     * @param list<int> $durations delay per frame in 1/100 second
     */
    public function create(array $frames, array $durations, int $loop = 0): string
    {
        if (count($frames) < 1) {
            throw new \Exception($this->version . ': ' . $this->errors['ERR00']);
        }

        $this->loop = ($loop > -1) ? $loop : 0;
        $this->dis = 2;
        $this->frameSources = [];
        $this->gif = 'GIF89a';
        $this->imgBuilt = false;

        $colour = null;

        for ($i = 0; $i < count($frames); $i++) {
            if ($this->isGdFrame($frames[$i])) {
                $resourceImg = $frames[$i];
                ob_start();
                imagegif($frames[$i]);
                $this->frameSources[] = ob_get_clean() ?: '';
            } elseif (is_string($frames[$i])) {
                if (file_exists($frames[$i]) || filter_var($frames[$i], FILTER_VALIDATE_URL)) {
                    $frames[$i] = (string) file_get_contents($frames[$i]);
                }
                $resourceImg = imagecreatefromstring($frames[$i]);
                if ($resourceImg === false) {
                    throw new \Exception($this->version . ': ' . $this->errors['ERR02']);
                }
                ob_start();
                imagegif($resourceImg);
                $this->frameSources[] = ob_get_clean() ?: '';
                imagedestroy($resourceImg);
                $resourceImg = null;
            } else {
                throw new \Exception($this->version . ': ' . $this->errors['ERR02']);
            }

            if ($i === 0 && $resourceImg !== null) {
                $colour = @imagecolortransparent($resourceImg);
            }

            if (substr($this->frameSources[$i], 0, 6) !== 'GIF87a' && substr($this->frameSources[$i], 0, 6) !== 'GIF89a') {
                throw new \Exception($this->version . ': ' . $i . ' ' . $this->errors['ERR01']);
            }

            for (
                $j = (13 + 3 * (2 << (ord($this->frameSources[$i][10]) & 0x07))), $k = true;
                $k;
                $j++
            ) {
                switch ($this->frameSources[$i][$j]) {
                    case '!':
                        if ((substr($this->frameSources[$i], ($j + 3), 8)) === 'NETSCAPE') {
                            throw new \Exception($this->version . ': ' . $this->errors['ERR03'] . ' (' . ($i + 1) . ' source).');
                        }
                        break;
                    case ';':
                        $k = false;
                        break;
                }
            }
        }

        if ($colour !== null && $colour >= 0) {
            $this->colour = $colour;
        } else {
            $this->colour = -1;
        }

        $this->gifAddHeader();

        for ($i = 0; $i < count($this->frameSources); $i++) {
            $d = $durations[$i] ?? 100;
            $this->addGifFrames($i, $d);
        }

        $this->gifAddFooter();

        return $this->gif;
    }

    private function isGdFrame(mixed $f): bool
    {
        if ($f instanceof \GdImage) {
            return true;
        }
        return is_resource($f) && get_resource_type($f) === 'gd';
    }

    public function gifAddHeader(): void
    {
        if (ord($this->frameSources[0][10]) & 0x80) {
            $cmap = 3 * (2 << (ord($this->frameSources[0][10]) & 0x07));

            $this->gif .= substr($this->frameSources[0], 6, 7);
            $this->gif .= substr($this->frameSources[0], 13, $cmap);
            if (!$this->omitNetscapeLoop) {
                $this->gif .= "!\377\13NETSCAPE2.0\3\1" . $this->encodeAsciiToChar($this->loop) . "\0";
            }
        }
    }

    public function addGifFrames(int $i, int $d): void
    {
        $Locals_str = 13 + 3 * (2 << (ord($this->frameSources[$i][10]) & 0x07));

        $Locals_end = strlen($this->frameSources[$i]) - $Locals_str - 1;
        $Locals_tmp = substr($this->frameSources[$i], $Locals_str, $Locals_end);

        $Global_len = 2 << (ord($this->frameSources[0][10]) & 0x07);
        $Locals_len = 2 << (ord($this->frameSources[$i][10]) & 0x07);

        $Global_rgb = substr($this->frameSources[0], 13, 3 * (2 << (ord($this->frameSources[0][10]) & 0x07)));
        $Locals_rgb = substr($this->frameSources[$i], 13, 3 * (2 << (ord($this->frameSources[$i][10]) & 0x07)));

        $Locals_ext = "!\xF9\x04" . chr(($this->dis << 2) + 0) . chr(($d >> 0) & 0xFF) . chr(($d >> 8) & 0xFF) . "\x0\x0";

        if ($this->colour > -1 && ord($this->frameSources[$i][10]) & 0x80) {
            for ($j = 0; $j < (2 << (ord($this->frameSources[$i][10]) & 0x07)); $j++) {
                if (ord($Locals_rgb[3 * $j + 0]) === (($this->colour >> 16) & 0xFF)
                    && ord($Locals_rgb[3 * $j + 1]) === (($this->colour >> 8) & 0xFF)
                    && ord($Locals_rgb[3 * $j + 2]) === (($this->colour >> 0) & 0xFF)
                ) {
                    $Locals_ext = "!\xF9\x04" . chr(($this->dis << 2) + 1) . chr(($d >> 0) & 0xFF) . chr(($d >> 8) & 0xFF) . chr($j) . "\x0";
                    break;
                }
            }
        }

        switch ($Locals_tmp[0]) {
            case '!':
                $Locals_img = substr($Locals_tmp, 8, 10);
                $Locals_tmp = substr($Locals_tmp, 18, strlen($Locals_tmp) - 18);
                break;
            case ',':
                $Locals_img = substr($Locals_tmp, 0, 10);
                $Locals_tmp = substr($Locals_tmp, 10, strlen($Locals_tmp) - 10);
                break;
            default:
                $Locals_img = '';
                break;
        }

        if (ord($this->frameSources[$i][10]) & 0x80 && $this->imgBuilt) {
            if ($Global_len === $Locals_len) {
                if ($this->gifBlockCompare($Global_rgb, $Locals_rgb, $Global_len)) {
                    $this->gif .= $Locals_ext . $Locals_img . $Locals_tmp;
                } else {
                    $byte = ord($Locals_img[9]);
                    $byte |= 0x80;
                    $byte &= 0xF8;
                    $byte |= (ord($this->frameSources[0][10]) & 0x07);
                    $Locals_img = substr($Locals_img, 0, 9) . chr($byte) . substr($Locals_img, 10);
                    $this->gif .= $Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp;
                }
            } else {
                $byte = ord($Locals_img[9]);
                $byte |= 0x80;
                $byte &= 0xF8;
                $byte |= (ord($this->frameSources[$i][10]) & 0x07);
                $Locals_img = substr($Locals_img, 0, 9) . chr($byte) . substr($Locals_img, 10);
                $this->gif .= $Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp;
            }
        } else {
            $this->gif .= $Locals_ext . $Locals_img . $Locals_tmp;
        }

        $this->imgBuilt = true;
    }

    public function gifAddFooter(): void
    {
        $this->gif .= ';';
    }

    public function gifBlockCompare(string $globalBlock, string $localBlock, int $length): int
    {
        for ($i = 0; $i < $length; $i++) {
            if ($globalBlock[3 * $i + 0] !== $localBlock[3 * $i + 0]
                || $globalBlock[3 * $i + 1] !== $localBlock[3 * $i + 1]
                || $globalBlock[3 * $i + 2] !== $localBlock[3 * $i + 2]) {
                return 0;
            }
        }

        return 1;
    }

    public function encodeAsciiToChar(int $char): string
    {
        return chr($char & 0xFF) . chr(($char >> 8) & 0xFF);
    }

    public function reset(): void
    {
        $this->frameSources = [];
        $this->gif = 'GIF89a';
        $this->imgBuilt = false;
        $this->loop = 0;
        $this->dis = 2;
        $this->colour = -1;
    }
}
