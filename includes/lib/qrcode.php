<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Pure Blog Lightweight Self-Contained QR Code Generator (SVG Output)
// Pure PHP ISO/IEC 18004 specification compliant QR encoder.
// ---------------------------------------------------------------------------

class PureQRCode
{
    public const ERROR_CORRECT_L = 1;
    public const ERROR_CORRECT_M = 0;
    public const ERROR_CORRECT_Q = 3;
    public const ERROR_CORRECT_H = 2;

    private const MODE_8BIT_BYTE = 4;

    private static ?array $EXP_TABLE = null;
    private static ?array $LOG_TABLE = null;

    private static array $PATTERN_POSITION_TABLE = [
        [],
        [6, 18],
        [6, 22],
        [6, 26],
        [6, 30],
        [6, 34],
        [6, 22, 38],
        [6, 24, 42],
        [6, 26, 46],
        [6, 28, 50],
        [6, 30, 54],
        [6, 32, 58],
        [6, 34, 62],
        [6, 26, 46, 66],
    ];

    private static array $RS_BLOCK_TABLE = [
        // Version 1 (M = 0: 1 block, total 26, data 16)
        1 => [
            0 => [[1, 26, 16]], // M
            1 => [[1, 26, 19]], // L
            2 => [[1, 26, 9]],  // H
            3 => [[1, 26, 13]], // Q
        ],
        // Version 2 (M: 1 block, total 44, data 28)
        2 => [
            0 => [[1, 44, 28]],
            1 => [[1, 44, 34]],
            2 => [[1, 44, 16]],
            3 => [[1, 44, 22]],
        ],
        // Version 3 (M: 1 block, total 70, data 44)
        3 => [
            0 => [[1, 70, 44]],
            1 => [[1, 70, 55]],
            2 => [[2, 35, 13]],
            3 => [[2, 35, 17]],
        ],
        // Version 4 (M: 2 blocks, total 100, data 64 => 2x32)
        4 => [
            0 => [[2, 50, 32]],
            1 => [[1, 100, 80]],
            2 => [[4, 25, 9]],
            3 => [[2, 50, 24]],
        ],
        // Version 5 (M: 2 blocks, total 134, data 86 => 2x43)
        5 => [
            0 => [[2, 67, 43]],
            1 => [[1, 134, 108]],
            2 => [[2, 33, 11], [2, 34, 12]],
            3 => [[2, 33, 15], [2, 34, 16]],
        ],
        // Version 6 (M: 4 blocks, total 172, data 108 => 4x27)
        6 => [
            0 => [[4, 43, 27]],
            1 => [[2, 86, 68]],
            2 => [[4, 43, 15]],
            3 => [[4, 43, 19]],
        ],
        // Version 7 (M: 4 blocks, total 196, data 124 => 4x31)
        7 => [
            0 => [[4, 49, 31]],
            1 => [[2, 98, 78]],
            2 => [[4, 39, 13], [1, 40, 14]],
            3 => [[2, 32, 14], [4, 33, 15]],
        ],
        // Version 8 (M: 2x38 + 2x39 data = 154)
        8 => [
            0 => [[2, 60, 38], [2, 61, 39]],
            1 => [[2, 121, 97]],
            2 => [[4, 40, 14], [2, 41, 15]],
            3 => [[4, 40, 18], [2, 41, 19]],
        ],
        // Version 9 (M: 3x36 + 2x37 data = 182)
        9 => [
            0 => [[3, 58, 36], [2, 59, 37]],
            1 => [[2, 146, 116]],
            2 => [[4, 36, 12], [4, 37, 13]],
            3 => [[4, 36, 16], [4, 37, 17]],
        ],
        // Version 10 (M: 4x40 + 1x41 data = 216)
        10 => [
            0 => [[4, 69, 43], [1, 70, 44]],
            1 => [[2, 86, 68], [2, 87, 69]],
            2 => [[6, 43, 15], [2, 44, 16]],
            3 => [[6, 43, 19], [2, 44, 20]],
        ],
    ];

    private static function initTables(): void
    {
        if (self::$EXP_TABLE !== null) {
            return;
        }

        self::$EXP_TABLE = array_fill(0, 256, 0);
        self::$LOG_TABLE = array_fill(0, 256, 0);

        for ($i = 0; $i < 8; $i++) {
            self::$EXP_TABLE[$i] = 1 << $i;
        }
        for ($i = 8; $i < 256; $i++) {
            self::$EXP_TABLE[$i] = self::$EXP_TABLE[$i - 4] ^ self::$EXP_TABLE[$i - 5] ^ self::$EXP_TABLE[$i - 6] ^ self::$EXP_TABLE[$i - 8];
        }
        for ($i = 0; $i < 255; $i++) {
            self::$LOG_TABLE[self::$EXP_TABLE[$i]] = $i;
        }
    }

    private static function glog(int $n): int
    {
        if ($n < 1) {
            throw new RuntimeException("glog($n)");
        }
        return self::$LOG_TABLE[$n];
    }

    private static function gexp(int $n): int
    {
        while ($n < 0) {
            $n += 255;
        }
        while ($n >= 255) {
            $n -= 255;
        }
        return self::$EXP_TABLE[$n];
    }

    private static function polyMul(array $p1, array $p2): array
    {
        $len1 = count($p1);
        $len2 = count($p2);
        $res = array_fill(0, $len1 + $len2 - 1, 0);

        for ($i = 0; $i < $len1; $i++) {
            for ($j = 0; $j < $len2; $j++) {
                if ($p1[$i] !== 0 && $p2[$j] !== 0) {
                    $res[$i + $j] ^= self::gexp(self::glog($p1[$i]) + self::glog($p2[$j]));
                }
            }
        }
        return $res;
    }

    private static function rsGeneratorPoly(int $ecCount): array
    {
        $poly = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $poly = self::polyMul($poly, [1, self::gexp($i)]);
        }
        return $poly;
    }

    private static function rsCompute(array $data, int $ecCount): array
    {
        $gen = self::rsGeneratorPoly($ecCount);
        $res = array_fill(0, $ecCount, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $res[0];
            array_shift($res);
            $res[] = 0;
            for ($j = 0; $j < $ecCount; $j++) {
                if ($factor !== 0 && $gen[$j + 1] !== 0) {
                    $res[$j] ^= self::gexp(self::glog($gen[$j + 1]) + self::glog($factor));
                }
            }
        }
        return $res;
    }

    private static function bchTypeInfo(int $data): int
    {
        $G15 = 0x537;
        $G15_MASK = 0x5412;
        $d = $data << 10;
        while (self::bchDigit($d) - self::bchDigit($G15) >= 0) {
            $d ^= $G15 << (self::bchDigit($d) - self::bchDigit($G15));
        }
        return (($data << 10) | $d) ^ $G15_MASK;
    }

    private static function bchDigit(int $data): int
    {
        $digit = 0;
        while ($data !== 0) {
            $digit++;
            $data >>= 1;
        }
        return $digit;
    }

    private static function bchTypeNumber(int $data): int
    {
        $G18 = 0x1F25;
        $d = $data << 12;
        while (self::bchDigit($d) - self::bchDigit($G18) >= 0) {
            $d ^= $G18 << (self::bchDigit($d) - self::bchDigit($G18));
        }
        return ($data << 12) | $d;
    }

    private static function maskCondition(int $pattern, int $row, int $col): bool
    {
        switch ($pattern) {
            case 0: return ($row + $col) % 2 === 0;
            case 1: return $row % 2 === 0;
            case 2: return $col % 3 === 0;
            case 3: return ($row + $col) % 3 === 0;
            case 4: return ((int) floor($row / 2) + (int) floor($col / 3)) % 2 === 0;
            case 5: return (($row * $col) % 2 + ($row * $col) % 3) === 0;
            case 6: return ((($row * $col) % 2 + ($row * $col) % 3) % 2) === 0;
            case 7: return ((($row * $col) % 3 + ($row + $col) % 2) % 2) === 0;
            default: return false;
        }
    }

    private static function lostPoint(array $matrix, int $size): int
    {
        $lost = 0;

        // Level 1: 5 or more same color in row/col
        for ($r = 0; $r < $size; $r++) {
            $prev = $matrix[$r][0];
            $count = 0;
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === $prev) {
                    $count++;
                } else {
                    if ($count >= 5) {
                        $lost += 3 + ($count - 5);
                    }
                    $prev = $matrix[$r][$c];
                    $count = 1;
                }
            }
            if ($count >= 5) {
                $lost += 3 + ($count - 5);
            }
        }
        for ($c = 0; $c < $size; $c++) {
            $prev = $matrix[0][$c];
            $count = 0;
            for ($r = 0; $r < $size; $r++) {
                if ($matrix[$r][$c] === $prev) {
                    $count++;
                } else {
                    if ($count >= 5) {
                        $lost += 3 + ($count - 5);
                    }
                    $prev = $matrix[$r][$c];
                    $count = 1;
                }
            }
            if ($count >= 5) {
                $lost += 3 + ($count - 5);
            }
        }

        // Level 2: 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $color = $matrix[$r][$c];
                if ($matrix[$r + 1][$c] === $color && $matrix[$r][$c + 1] === $color && $matrix[$r + 1][$c + 1] === $color) {
                    $lost += 3;
                }
            }
        }

        // Level 3: 1:1:3:1:1 pattern
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                if (
                    $matrix[$r][$c] &&
                    !$matrix[$r][$c + 1] &&
                    $matrix[$r][$c + 2] &&
                    $matrix[$r][$c + 3] &&
                    $matrix[$r][$c + 4] &&
                    !$matrix[$r][$c + 5] &&
                    $matrix[$r][$c + 6] &&
                    !$matrix[$r][$c + 7] &&
                    !$matrix[$r][$c + 8] &&
                    !$matrix[$r][$c + 9] &&
                    !$matrix[$r][$c + 10]
                ) {
                    $lost += 40;
                }
                if (
                    !$matrix[$r][$c] &&
                    !$matrix[$r][$c + 1] &&
                    !$matrix[$r][$c + 2] &&
                    !$matrix[$r][$c + 3] &&
                    $matrix[$r][$c + 4] &&
                    !$matrix[$r][$c + 5] &&
                    $matrix[$r][$c + 6] &&
                    $matrix[$r][$c + 7] &&
                    $matrix[$r][$c + 8] &&
                    !$matrix[$r][$c + 9] &&
                    $matrix[$r][$c + 10]
                ) {
                    $lost += 40;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 11; $r++) {
                if (
                    $matrix[$r][$c] &&
                    !$matrix[$r + 1][$c] &&
                    $matrix[$r + 2][$c] &&
                    $matrix[$r + 3][$c] &&
                    $matrix[$r + 4][$c] &&
                    !$matrix[$r + 5][$c] &&
                    $matrix[$r + 6][$c] &&
                    !$matrix[$r + 7][$c] &&
                    !$matrix[$r + 8][$c] &&
                    !$matrix[$r + 9][$c] &&
                    !$matrix[$r + 10][$c]
                ) {
                    $lost += 40;
                }
                if (
                    !$matrix[$r][$c] &&
                    !$matrix[$r + 1][$c] &&
                    !$matrix[$r + 2][$c] &&
                    !$matrix[$r + 3][$c] &&
                    $matrix[$r + 4][$c] &&
                    !$matrix[$r + 5][$c] &&
                    $matrix[$r + 6][$c] &&
                    $matrix[$r + 7][$c] &&
                    $matrix[$r + 8][$c] &&
                    !$matrix[$r + 9][$c] &&
                    $matrix[$r + 10][$c]
                ) {
                    $lost += 40;
                }
            }
        }

        // Level 4: proportion of dark modules
        $darkCount = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c]) {
                    $darkCount++;
                }
            }
        }
        $ratio = ($darkCount * 100) / ($size * $size);
        $k = (int) abs($ratio - 50) / 5;
        $lost += (int) $k * 10;

        return $lost;
    }

    public static function encodeToMatrix(string $text, int $ecc = self::ERROR_CORRECT_M): ?array
    {
        self::initTables();

        $textLen = strlen($text);

        // Find version
        $version = null;
        $rsBlocks = null;
        for ($v = 1; $v <= 10; $v++) {
            $blocks = self::$RS_BLOCK_TABLE[$v][$ecc] ?? null;
            if (!$blocks) {
                continue;
            }
            $totalData = 0;
            foreach ($blocks as $block) {
                $totalData += $block[0] * $block[2];
            }

            $charBits = ($v < 10) ? 8 : 16;
            $neededBits = 4 + $charBits + ($textLen * 8);

            if ($neededBits <= $totalData * 8) {
                $version = $v;
                $rsBlocks = $blocks;
                break;
            }
        }

        if ($version === null || $rsBlocks === null) {
            return null;
        }

        $size = $version * 4 + 17;
        $charBits = ($version < 10) ? 8 : 16;

        // Total data capacity
        $totalDataBytes = 0;
        foreach ($rsBlocks as $block) {
            $totalDataBytes += $block[0] * $block[2];
        }

        // 1. Bit stream creation
        $bitStream = '0100'; // 8-bit byte mode
        $bitStream .= str_pad(decbin($textLen), $charBits, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $textLen; $i++) {
            $bitStream .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Terminator
        $maxDataBits = $totalDataBytes * 8;
        $bitStream .= str_repeat('0', min(4, $maxDataBits - strlen($bitStream)));

        // Pad to byte
        if (strlen($bitStream) % 8 !== 0) {
            $bitStream .= str_repeat('0', 8 - (strlen($bitStream) % 8));
        }

        // Pad bytes
        $padBytes = ['11101100', '00010001'];
        $padIdx = 0;
        while (strlen($bitStream) < $maxDataBits) {
            $bitStream .= $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // 2. RS Block generation
        $dataCodewords = [];
        for ($i = 0; $i < strlen($bitStream); $i += 8) {
            $dataCodewords[] = bindec(substr($bitStream, $i, 8));
        }

        $blocksData = [];
        $blocksEc = [];
        $offset = 0;

        foreach ($rsBlocks as $bGroup) {
            $numBlocks = $bGroup[0];
            $totalPerBlock = $bGroup[1];
            $dataPerBlock = $bGroup[2];
            $ecPerBlock = $totalPerBlock - $dataPerBlock;

            for ($b = 0; $b < $numBlocks; $b++) {
                $bData = array_slice($dataCodewords, $offset, $dataPerBlock);
                $offset += $dataPerBlock;
                $bEc = self::rsCompute($bData, $ecPerBlock);
                $blocksData[] = $bData;
                $blocksEc[] = $bEc;
            }
        }

        // 3. Interleaving
        $finalBytes = [];
        $maxDataLen = 0;
        foreach ($blocksData as $bd) {
            $maxDataLen = max($maxDataLen, count($bd));
        }
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocksData as $bd) {
                if (isset($bd[$i])) {
                    $finalBytes[] = $bd[$i];
                }
            }
        }

        $maxEcLen = 0;
        foreach ($blocksEc as $be) {
            $maxEcLen = max($maxEcLen, count($be));
        }
        for ($i = 0; $i < $maxEcLen; $i++) {
            foreach ($blocksEc as $be) {
                if (isset($be[$i])) {
                    $finalBytes[] = $be[$i];
                }
            }
        }

        // 4. Template matrix
        $template = array_fill(0, $size, array_fill(0, $size, null));

        // Finder patterns
        self::placeFinder($template, 0, 0, $size);
        self::placeFinder($template, $size - 7, 0, $size);
        self::placeFinder($template, 0, $size - 7, $size);

        // Alignment patterns
        $alignPos = self::$PATTERN_POSITION_TABLE[$version - 1] ?? [];
        $posCount = count($alignPos);
        for ($i = 0; $i < $posCount; $i++) {
            for ($j = 0; $j < $posCount; $j++) {
                $r = $alignPos[$i];
                $c = $alignPos[$j];
                if ($template[$r][$c] !== null) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $isDark = (abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0));
                        $template[$r + $dr][$c + $dc] = $isDark;
                    }
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            if ($template[$i][6] === null) {
                $template[$i][6] = ($i % 2 === 0);
            }
            if ($template[6][$i] === null) {
                $template[6][$i] = ($i % 2 === 0);
            }
        }

        // Type number for version >= 7
        if ($version >= 7) {
            $typeNum = self::bchTypeNumber($version);
            for ($i = 0; $i < 18; $i++) {
                $mod = (($typeNum >> $i) & 1) === 1;
                $template[(int) ($i / 3)][$i % 3 + $size - 8 - 3] = $mod;
                $template[$i % 3 + $size - 8 - 3][(int) ($i / 3)] = $mod;
            }
        }

        // Reserve format area
        for ($i = 0; $i < 9; $i++) {
            if ($template[8][$i] === null) {
                $template[8][$i] = false;
            }
            if ($template[$i][8] === null) {
                $template[$i][8] = false;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            if ($template[8][$size - 1 - $i] === null) {
                $template[8][$size - 1 - $i] = false;
            }
            if ($template[$size - 1 - $i][8] === null) {
                $template[$size - 1 - $i][8] = false;
            }
        }
        $template[$size - 8][8] = true; // Dark module

        // 5. Evaluate all 8 masks to pick best mask
        $bestMask = 0;
        $bestLost = PHP_INT_MAX;
        $bestMatrix = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $m = $template;

            // Place format info
            $typeInfo = self::bchTypeInfo(($ecc << 3) | $mask);
            for ($i = 0; $i < 15; $i++) {
                $mod = (($typeInfo >> $i) & 1) === 1;
                if ($i < 6) {
                    $m[$i][8] = $mod;
                } elseif ($i < 8) {
                    $m[$i + 1][8] = $mod;
                } else {
                    $m[$size - 15 + $i][8] = $mod;
                }

                if ($i < 8) {
                    $m[8][$size - $i - 1] = $mod;
                } elseif ($i < 9) {
                    $m[8][15 - $i] = $mod;
                } else {
                    $m[8][14 - $i] = $mod;
                }
            }
            $m[$size - 8][8] = true;

            // Map data
            $inc = -1;
            $row = $size - 1;
            $bitIndex = 7;
            $byteIndex = 0;
            $dataLen = count($finalBytes);

            for ($col = $size - 1; $col > 0; $col -= 2) {
                if ($col <= 6) {
                    $col -= 1;
                }

                while (true) {
                    foreach ([$col, $col - 1] as $c) {
                        if ($template[$row][$c] === null) {
                            $dark = false;
                            if ($byteIndex < $dataLen) {
                                $dark = (($finalBytes[$byteIndex] >> $bitIndex) & 1) === 1;
                            }
                            if (self::maskCondition($mask, $row, $c)) {
                                $dark = !$dark;
                            }
                            $m[$row][$c] = $dark;
                            $bitIndex--;
                            if ($bitIndex === -1) {
                                $byteIndex++;
                                $bitIndex = 7;
                            }
                        }
                    }

                    $row += $inc;
                    if ($row < 0 || $row >= $size) {
                        $row -= $inc;
                        $inc = -$inc;
                        break;
                    }
                }
            }

            $lost = self::lostPoint($m, $size);
            if ($lost < $bestLost) {
                $bestLost = $lost;
                $bestMask = $mask;
                $bestMatrix = $m;
            }
        }

        return $bestMatrix;
    }

    private static function placeFinder(array &$m, int $row, int $col, int $size): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                if (
                    (0 <= $r && $r <= 6 && ($c === 0 || $c === 6)) ||
                    (0 <= $c && $c <= 6 && ($r === 0 || $r === 6)) ||
                    (2 <= $r && $r <= 4 && 2 <= $c && $c <= 4)
                ) {
                    $m[$rr][$cc] = true;
                } else {
                    $m[$rr][$cc] = false;
                }
            }
        }
    }

    public static function svg(string $text, int $size = 200, string $darkColor = '#000000', string $lightColor = '#ffffff'): string
    {
        $matrix = self::encodeToMatrix($text);
        if ($matrix === null) {
            return '';
        }

        $moduleCount = count($matrix);
        $quietZone = 4;
        $totalDimension = $moduleCount + ($quietZone * 2);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalDimension . ' ' . $totalDimension . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges" aria-label="QR Code" role="img">';
        if ($lightColor !== 'transparent') {
            $svg .= '<rect width="100%" height="100%" fill="' . htmlspecialchars($lightColor, ENT_QUOTES, 'UTF-8') . '"/>';
        }

        $pathData = '';
        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quietZone;
                    $y = $r + $quietZone;
                    $pathData .= 'M' . $x . ',' . $y . 'h1v1h-1z ';
                }
            }
        }

        $svg .= '<path d="' . rtrim($pathData) . '" fill="' . htmlspecialchars($darkColor, ENT_QUOTES, 'UTF-8') . '"/>';
        $svg .= '</svg>';

        return $svg;
    }
}

/**
 * Generate an inline SVG string of a QR Code.
 */
function qrcode_svg(string $text, int $size = 200, string $darkColor = '#000000', string $lightColor = '#ffffff'): string
{
    return PureQRCode::svg($text, $size, $darkColor, $lightColor);
}
