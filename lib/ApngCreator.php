<?php

declare(strict_types=1);

/**
 * Animated PNG (APNG) from GD frames — same countdown sequence as GifCreator.
 * Play-once by default (num_plays = 1). Clients that ignore APNG show frame 1.
 */
final class ApngCreator
{
    /**
     * @param list<\GdImage|resource> $frames
     * @param list<int> $delayCs delay per frame in 1/100 second (GifCreator-compatible)
     * @param int $plays 0 = infinite loop, 1 = play once
     */
    public function create(array $frames, array $delayCs, int $plays = 1): string
    {
        $n = count($frames);
        if ($n < 1) {
            throw new InvalidArgumentException('APNG requires at least one frame.');
        }

        $pngFrames = [];
        for ($i = 0; $i < $n; $i++) {
            $pngFrames[] = $this->gdToPng($frames[$i]);
        }

        $parsed = [];
        foreach ($pngFrames as $bin) {
            $parsed[] = $this->splitPng($bin);
        }

        $w = $parsed[0]['width'];
        $h = $parsed[0]['height'];
        $ihdr = $parsed[0]['ihdr'];

        $out = "\x89PNG\r\n\x1a\n";
        $out .= $this->chunk('IHDR', $ihdr);
        $out .= $this->chunk('acTL', pack('NN', $n, max(0, $plays)));

        $seq = 0;
        for ($i = 0; $i < $n; $i++) {
            $delayCs = (int) ($delayCs[$i] ?? $delayCs[0] ?? 100);
            if ($delayCs < 1) {
                $delayCs = 1;
            }
            // delay_num / delay_den seconds; den=100 → delayCs centiseconds.
            $fcTL = pack(
                'NNNNNnnCC',
                $seq++,
                $w,
                $h,
                0,
                0,
                $delayCs,
                100,
                1, // dispose: background
                0  // blend: source
            );
            $out .= $this->chunk('fcTL', $fcTL);

            $idatData = $parsed[$i]['idat'];
            if ($i === 0) {
                $out .= $this->chunk('IDAT', $idatData);
            } else {
                $out .= $this->chunk('fdAT', pack('N', $seq++) . $idatData);
            }
        }

        $out .= $this->chunk('IEND', '');

        return $out;
    }

    /** @param \GdImage|resource $im */
    private function gdToPng($im): string
    {
        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        if ($bin === false || $bin === '') {
            throw new RuntimeException('Could not encode PNG frame.');
        }

        return $bin;
    }

    /**
     * @return array{width:int,height:int,ihdr:string,idat:string}
     */
    private function splitPng(string $png): array
    {
        if (strlen($png) < 8 || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new InvalidArgumentException('Not a PNG stream.');
        }

        $pos = 8;
        $len = strlen($png);
        $ihdr = null;
        $idat = '';
        $width = 0;
        $height = 0;

        while ($pos + 8 <= $len) {
            $length = unpack('N', substr($png, $pos, 4))[1];
            $type = substr($png, $pos + 4, 4);
            $data = substr($png, $pos + 8, $length);
            $pos += 12 + $length;

            if ($type === 'IHDR') {
                $ihdr = $data;
                $dims = unpack('Nwidth/Nheight', $data);
                $width = (int) $dims['width'];
                $height = (int) $dims['height'];
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        if ($ihdr === null || $idat === '') {
            throw new InvalidArgumentException('PNG missing IHDR/IDAT.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'ihdr' => $ihdr,
            'idat' => $idat,
        ];
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data) & 0xffffffff);
    }
}
