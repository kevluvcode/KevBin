<?php
/**
 * Faithful PHP port of figlet.js (https://github.com/patorjk/figlet.js)
 * Renders standard FIGlet .flf fonts (all smushing/layout rules supported).
 */

final class KbFiglet
{
    public const FULL_WIDTH = 0;
    public const FITTING = 1;
    public const SMUSHING = 2;
    public const CONTROLLED_SMUSHING = 3;

    private const FONT_DIR = __DIR__ . '/fonts';

    private string $name = '';
    private int $height = 0;
    private int $baseline = 0;
    private int $maxLength = 0;
    private int $oldLayout = 0;
    private int $numCommentLines = 0;
    private int $printDirection = 0;
    private ?int $fullLayout = null;
    private ?int $codeTagCount = null;
    private string $hardBlank = '@';
    private array $fittingRules = [];
    private array $chars = [];

    public static function fontList(): array
    {
        $out = [];
        foreach (glob(self::FONT_DIR . '/*.flf') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $out[] = ['name' => $name, 'kb' => round(filesize($file) / 1024, 1)];
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public static function sanitizeName(string $name): string
    {
        $name = basename($name);
        if (!preg_match('/^[A-Za-z0-9 _\+\-\.]+$/', $name)) return 'Standard';
        $path = self::FONT_DIR . '/' . $name . '.flf';
        return is_file($path) ? $name : 'Standard';
    }

    public static function render(string $text, string $fontName, int $width = 0): string
    {
        $f = new self();
        $f->load(self::sanitizeName($fontName));

        $opts = [
            'height' => $f->height,
            'fittingRules' => $f->fittingRules,
            'hardBlank' => $f->hardBlank,
            'width' => $width > 0 ? $width : -1,
            'whitespaceBreak' => false,
            'showHardBlanks' => false,
            'horizontalLayout' => 'default',
            'verticalLayout' => 'default',
            'printDirection' => $f->printDirection,
        ];

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $figChars = $f->chars;
        $lines = explode("\n", $text);
        $figLines = [];
        foreach ($lines as $line) {
            $figLines = array_merge($figLines, self::generateFigTextLines($line, $figChars, $opts));
        }
        if (count($figLines) === 0) $figLines[] = array_fill(0, $f->height, '');

        $output = $figLines[0] ?? [];
        $n = count($figLines);
        for ($i = 1; $i < $n; $i++) {
            $output = self::smushVerticalFigLines($output, $figLines[$i], $opts);
        }
        return is_array($output) ? implode("\n", $output) : '';
    }

    private function load(string $name): void
    {
        $file = self::FONT_DIR . '/' . $name . '.flf';
        if (!is_file($file)) throw new RuntimeException('Font not found: ' . $name);
        $data = str_replace(["\r\n", "\r"], "\n", (string)file_get_contents($file));
        $lines = explode("\n", $data);
        $headerLine = array_shift($lines);
        if ($headerLine === null) throw new RuntimeException('Invalid font file (missing header): ' . $name);

        $h = explode(' ', $headerLine);
        $this->name = $name;
        $this->hardBlank = substr((string)($h[0] ?? ''), 5, 1);
        $this->height = (int)($h[1] ?? 0);
        $this->baseline = (int)($h[2] ?? 0);
        $this->maxLength = (int)($h[3] ?? 0);
        $this->oldLayout = (int)($h[4] ?? 0);
        $this->numCommentLines = (int)($h[5] ?? 0);
        $this->printDirection = isset($h[6]) ? (int)$h[6] : 0;
        $this->fullLayout = isset($h[7]) ? (int)$h[7] : null;
        $this->codeTagCount = isset($h[8]) ? (int)$h[8] : null;

        // Skip !// the special "flf2a$" only; require valid height.
        if ($this->height < 1) throw new RuntimeException('FIGlet header contains invalid values: ' . $name);

        $this->fittingRules = self::getSmushingRules($this->oldLayout, $this->fullLayout);

        $this->chars = [];
        $charNums = [];
        for ($i = 32; $i <= 126; $i++) $charNums[] = $i;
        foreach ([196, 214, 220, 228, 246, 252, 223] as $c) $charNums[] = $c;

        if (count($lines) < $this->numCommentLines + $this->height * count($charNums)) {
            throw new RuntimeException('FIGlet file is missing data: ' . $name);
        }

        // comments
        $comment = [];
        for ($i = 0; $i < $this->numCommentLines; $i++) {
            if (!isset($lines[$i])) break;
            $comment[] = $lines[$i];
        }
        $lines = array_slice($lines, $this->numCommentLines);

        $numChars = 0;
        while (count($lines) > 0 && $numChars < count($charNums)) {
            $cNum = $charNums[$numChars];
            $block = array_slice($lines, 0, $this->height);
            $lines = array_slice($lines, $this->height);
            $glyph = [];
            for ($i = 0; $i < $this->height; $i++) {
                $row = $block[$i] ?? '';
                $glyph[] = self::removeEndChar($row, $i, $this->height);
            }
            $this->chars[$cNum] = $glyph;
            $numChars++;
        }

        // Extra character definitions (code-tagged sections).
        while (count($lines) > 0) {
            $cNumLine = array_shift($lines);
            if ($cNumLine === null || trim($cNumLine) === '') break;
            $cNum = trim(explode(' ', $cNumLine)[0]);
            if (preg_match('/^0[xX][0-9a-fA-F]+$/', $cNum)) $parsed = hexdec($cNum);
            elseif (preg_match('/^0[0-7]+$/', $cNum)) $parsed = octdec($cNum);
            elseif (preg_match('/^-?[0-9]+$/', $cNum)) $parsed = (int)$cNum;
            else continue;
            if ($parsed < -2147483648 || $parsed > 2147483647 || $parsed === -1) continue;
            $block = array_slice($lines, 0, $this->height);
            $lines = array_slice($lines, $this->height);
            $glyph = [];
            for ($i = 0; $i < $this->height; $i++) {
                $row = $block[$i] ?? '';
                $glyph[] = self::removeEndChar($row, $i, $this->height);
            }
            $this->chars[$parsed] = $glyph;
        }
    }

    private static function removeEndChar(string $line, int $lineNum, int $fontHeight): string
    {
        $trimmed = rtrim($line);
        $endChar = $trimmed !== '' ? substr($trimmed, -1) : '@';
        $endChar = preg_quote($endChar, '/');
        $pattern = '/';
        if ($lineNum === $fontHeight - 1) $pattern .= $endChar . $endChar . '?\s*$';
        else $pattern .= $endChar . '\s*$';
        return (string)preg_replace($pattern . '/', '', $line);
    }

    private static function getSmushingRules(int $oldLayout = -1, ?int $newLayout = null): array
    {
        $rules = [];
        $val = $newLayout !== null ? $newLayout : $oldLayout;
        $codes = [
            [16384, 'vLayout', self::SMUSHING],
            [8192, 'vLayout', self::FITTING],
            [4096, 'vRule5', true],
            [2048, 'vRule4', true],
            [1024, 'vRule3', true],
            [512, 'vRule2', true],
            [256, 'vRule1', true],
            [128, 'hLayout', self::SMUSHING],
            [64, 'hLayout', self::FITTING],
            [32, 'hRule6', true],
            [16, 'hRule5', true],
            [8, 'hRule4', true],
            [4, 'hRule3', true],
            [2, 'hRule2', true],
            [1, 'hRule1', true],
        ];
        foreach ($codes as [$code, $rule, $value]) {
            if ($val >= $code) {
                $val -= $code;
                if (!array_key_exists($rule, $rules)) $rules[$rule] = $value;
            } elseif ($rule !== 'vLayout' && $rule !== 'hLayout') {
                $rules[$rule] = false;
            }
        }
        if (!array_key_exists('hLayout', $rules)) {
            if ($oldLayout === 0) $rules['hLayout'] = self::FITTING;
            elseif ($oldLayout === -1) $rules['hLayout'] = self::FULL_WIDTH;
            elseif ($rules['hRule1'] || $rules['hRule2'] || $rules['hRule3'] || $rules['hRule4'] || $rules['hRule5'] || $rules['hRule6']) $rules['hLayout'] = self::CONTROLLED_SMUSHING;
            else $rules['hLayout'] = self::SMUSHING;
        } elseif ($rules['hLayout'] === self::SMUSHING) {
            if ($rules['hRule1'] || $rules['hRule2'] || $rules['hRule3'] || $rules['hRule4'] || $rules['hRule5'] || $rules['hRule6']) $rules['hLayout'] = self::CONTROLLED_SMUSHING;
        }
        if (!array_key_exists('vLayout', $rules)) {
            if ($rules['vRule1'] || $rules['vRule2'] || $rules['vRule3'] || $rules['vRule4'] || $rules['vRule5']) $rules['vLayout'] = self::CONTROLLED_SMUSHING;
            else $rules['vLayout'] = self::FULL_WIDTH;
        } elseif ($rules['vLayout'] === self::SMUSHING) {
            if ($rules['vRule1'] || $rules['vRule2'] || $rules['vRule3'] || $rules['vRule4'] || $rules['vRule5']) $rules['vLayout'] = self::CONTROLLED_SMUSHING;
        }
        return $rules;
    }

    private static function hRule1(string $ch1, string $ch2, string $hardBlank)
    {
        return ($ch1 === $ch2 && $ch1 !== $hardBlank) ? $ch1 : false;
    }
    private static function hRule2(string $ch1, string $ch2)
    {
        $rule2Str = '|/\\[]{}()<>';
        if ($ch1 === '_' && strpos($rule2Str, $ch2) !== false) return $ch2;
        if ($ch2 === '_' && strpos($rule2Str, $ch1) !== false) return $ch1;
        return false;
    }
    private static function hRule3(string $ch1, string $ch2)
    {
        $classes = '| /\\ [] {} () <>';
        $p1 = strpos($classes, $ch1);
        $p2 = strpos($classes, $ch2);
        if ($p1 !== false && $p2 !== false && $p1 !== $p2 && abs($p1 - $p2) !== 1) {
            $start = max($p1, $p2);
            return substr($classes, $start, 1);
        }
        return false;
    }
    private static function hRule4(string $ch1, string $ch2)
    {
        $str = '[] {} ()';
        $p1 = strpos($str, $ch1);
        $p2 = strpos($str, $ch2);
        if ($p1 !== false && $p2 !== false && abs($p1 - $p2) <= 1) return '|';
        return false;
    }
    private static function hRule5(string $ch1, string $ch2)
    {
        $pair = $ch1 . $ch2;
        $map = ["/\\" => '|', '\\/' => 'Y', '><' => 'X'];
        return $map[$pair] ?? false;
    }
    private static function hRule6(string $ch1, string $ch2, string $hardBlank)
    {
        return ($ch1 === $hardBlank && $ch2 === $hardBlank) ? $hardBlank : false;
    }
    private static function vRule1(string $ch1, string $ch2)
    {
        return $ch1 === $ch2 ? $ch1 : false;
    }
    private static function vRule2(string $ch1, string $ch2)
    {
        return self::hRule2($ch1, $ch2);
    }
    private static function vRule3(string $ch1, string $ch2)
    {
        return self::hRule3($ch1, $ch2);
    }
    private static function vRule4(string $ch1, string $ch2)
    {
        if (($ch1 === '-' && $ch2 === '_') || ($ch1 === '_' && $ch2 === '-')) return '=';
        return false;
    }
    private static function vRule5(string $ch1, string $ch2)
    {
        return ($ch1 === '|' && $ch2 === '|') ? '|' : false;
    }
    private static function uniSmush(string $ch1, string $ch2, string $hardBlank): string
    {
        if ($ch2 === ' ' || $ch2 === '') return $ch1;
        if ($ch2 === $hardBlank && $ch1 !== ' ') return $ch1;
        return $ch2;
    }

    private static function canVerticalSmush(string $txt1, string $txt2, array $opts): string
    {
        $fr = $opts['fittingRules'];
        if (($fr['vLayout'] ?? null) === self::FULL_WIDTH) return 'invalid';
        $len = min(strlen($txt1), strlen($txt2));
        if ($len === 0) return 'invalid';
        $endSmush = false;
        for ($ii = 0; $ii < $len; $ii++) {
            $ch1 = $txt1[$ii];
            $ch2 = $txt2[$ii];
            if ($ch1 !== ' ' && $ch2 !== ' ') {
                if (($fr['vLayout'] ?? null) === self::FITTING) return 'invalid';
                if (($fr['vLayout'] ?? null) === self::SMUSHING) return 'end';
                if (self::vRule5($ch1, $ch2)) {
                    $endSmush = $endSmush || false;
                    continue;
                }
                $validSmush = false;
                if (!empty($fr['vRule1'])) $validSmush = self::vRule1($ch1, $ch2);
                if (!$validSmush && !empty($fr['vRule2'])) $validSmush = self::vRule2($ch1, $ch2);
                if (!$validSmush && !empty($fr['vRule3'])) $validSmush = self::vRule3($ch1, $ch2);
                if (!$validSmush && !empty($fr['vRule4'])) $validSmush = self::vRule4($ch1, $ch2);
                $endSmush = true;
                if ($validSmush === false) return 'invalid';
            }
        }
        return $endSmush ? 'end' : 'valid';
    }

    private static function getVerticalSmushDist(array $lines1, array $lines2, array $opts): int
    {
        $maxDist = count($lines1);
        $len1 = count($lines1);
        $curDist = 1;
        while ($curDist <= $maxDist) {
            $sub1 = array_slice($lines1, max(0, $len1 - $curDist), $len1);
            $sub2 = array_slice($lines2, 0, min($maxDist, $curDist));
            $slen = count($sub2);
            $result = '';
            for ($ii = 0; $ii < $slen; $ii++) {
                $ret = self::canVerticalSmush($sub1[$ii], $sub2[$ii], $opts);
                if ($ret === 'end') $result = 'end';
                elseif ($ret === 'invalid') { $result = 'invalid'; break; }
                elseif ($result === '') $result = 'valid';
            }
            if ($result === 'invalid') { $curDist--; break; }
            if ($result === 'end') break;
            if ($result === 'valid') $curDist++;
        }
        return min($maxDist, $curDist);
    }

    private static function verticallySmushLines(string $line1, string $line2, array $opts): string
    {
        $fr = $opts['fittingRules'];
        $len = min(strlen($line1), strlen($line2));
        $result = '';
        for ($ii = 0; $ii < $len; $ii++) {
            $ch1 = $line1[$ii];
            $ch2 = $line2[$ii];
            if ($ch1 !== ' ' && $ch2 !== ' ') {
                if (($fr['vLayout'] ?? null) === self::FITTING || ($fr['vLayout'] ?? null) === self::SMUSHING) {
                    $result .= self::uniSmush($ch1, $ch2, $opts['hardBlank']);
                } else {
                    $validSmush = false;
                    if (!empty($fr['vRule5'])) $validSmush = self::vRule5($ch1, $ch2);
                    if (!$validSmush && !empty($fr['vRule1'])) $validSmush = self::vRule1($ch1, $ch2);
                    if (!$validSmush && !empty($fr['vRule2'])) $validSmush = self::vRule2($ch1, $ch2);
                    if (!$validSmush && !empty($fr['vRule3'])) $validSmush = self::vRule3($ch1, $ch2);
                    if (!$validSmush && !empty($fr['vRule4'])) $validSmush = self::vRule4($ch1, $ch2);
                    $result .= $validSmush !== false ? $validSmush : self::uniSmush($ch1, $ch2, $opts['hardBlank']);
                }
            } else {
                $result .= self::uniSmush($ch1, $ch2, $opts['hardBlank']);
            }
        }
        return $result;
    }

    private static function verticalSmush(array $lines1, array $lines2, int $overlap, array $opts): array
    {
        $len1 = count($lines1);
        $len2 = count($lines2);
        $piece1 = array_slice($lines1, 0, max(0, $len1 - $overlap));
        $p2_1 = array_slice($lines1, max(0, $len1 - $overlap), $len1);
        $p2_2 = array_slice($lines2, 0, min($overlap, $len2));
        $piece2 = [];
        $n = count($p2_1);
        for ($ii = 0; $ii < $n; $ii++) {
            if ($ii >= $len2) $piece2[] = $p2_1[$ii];
            else $piece2[] = self::verticallySmushLines($p2_1[$ii], $p2_2[$ii], $opts);
        }
        $piece3 = array_slice($lines2, min($overlap, $len2), $len2);
        return array_merge($piece1, $piece2, $piece3);
    }

    private static function padLines(array $lines, int $numSpaces): array
    {
        return array_map(fn($l) => $l . str_repeat(' ', $numSpaces), $lines);
    }

    private static function smushVerticalFigLines(array $output, array $lines, array $opts): array
    {
        $len1 = strlen($output[0] ?? '');
        $len2 = strlen($lines[0] ?? '');
        if ($len1 > $len2) $lines = self::padLines($lines, $len1 - $len2);
        elseif ($len2 > $len1) $output = self::padLines($output, $len2 - $len1);
        $overlap = self::getVerticalSmushDist($output, $lines, $opts);
        return self::verticalSmush($output, $lines, $overlap, $opts);
    }

    private static function getHorizontalSmushLength(string $txt1, string $txt2, array $opts): int
    {
        $fr = $opts['fittingRules'];
        if (($fr['hLayout'] ?? null) === self::FULL_WIDTH) return 0;
        $len1 = strlen($txt1);
        $len2 = strlen($txt2);
        $maxDist = $len1;
        $curDist = 1;
        $breakAfter = false;
        if ($len1 === 0) return 0;
        while ($curDist <= $maxDist) {
            $seg1StartPos = $len1 - $curDist;
            $seg1 = substr($txt1, $seg1StartPos, $curDist);
            $seg2 = substr($txt2, 0, min($curDist, $len2));
            $m = min($curDist, $len2);
            for ($ii = 0; $ii < $m; $ii++) {
                $ch1 = $seg1[$ii];
                $ch2 = $seg2[$ii];
                if ($ch1 !== ' ' && $ch2 !== ' ') {
                    if (($fr['hLayout'] ?? null) === self::FITTING) {
                        $curDist = $curDist - 1;
                        break 2;
                    }
                    if (($fr['hLayout'] ?? null) === self::SMUSHING) {
                        if ($ch1 === $opts['hardBlank'] || $ch2 === $opts['hardBlank']) $curDist = $curDist - 1;
                        break 2;
                    }
                    $breakAfter = true;
                    $smush = false;
                    if (!empty($fr['hRule1'])) $smush = $smush ?: self::hRule1($ch1, $ch2, $opts['hardBlank']);
                    if (!$smush && !empty($fr['hRule2'])) $smush = self::hRule2($ch1, $ch2);
                    if (!$smush && !empty($fr['hRule3'])) $smush = self::hRule3($ch1, $ch2);
                    if (!$smush && !empty($fr['hRule4'])) $smush = self::hRule4($ch1, $ch2);
                    if (!$smush && !empty($fr['hRule5'])) $smush = self::hRule5($ch1, $ch2);
                    if (!$smush && !empty($fr['hRule6'])) $smush = self::hRule6($ch1, $ch2, $opts['hardBlank']);
                    if ($smush === false) {
                        $curDist = $curDist - 1;
                        break 2;
                    }
                }
            }
            if ($breakAfter) break;
            $curDist++;
        }
        return min($maxDist, $curDist);
    }

    private static function horizontalSmush(array $block1, array $block2, int $overlap, array $opts): array
    {
        $fr = $opts['fittingRules'];
        $height = $opts['height'];
        $output = [];
        for ($ii = 0; $ii < $height; $ii++) {
            $txt1 = $block1[$ii] ?? '';
            $txt2 = $block2[$ii] ?? '';
            $len1 = strlen($txt1);
            $len2 = strlen($txt2);
            $overlapStart = $len1 - $overlap;
            $piece1 = substr($txt1, 0, max(0, $overlapStart));
            $piece2 = '';
            $seg1Start = max(0, $len1 - $overlap);
            $seg1 = substr($txt1, $seg1Start, $overlap);
            $seg2 = substr($txt2, 0, min($overlap, $len2));
            for ($jj = 0; $jj < $overlap; $jj++) {
                $ch1 = $jj < strlen($seg1) ? $seg1[$jj] : ' ';
                $ch2 = $jj < strlen($seg2) ? $seg2[$jj] : ' ';
                if ($ch1 !== ' ' && $ch2 !== ' ') {
                    if (($fr['hLayout'] ?? null) === self::FITTING || ($fr['hLayout'] ?? null) === self::SMUSHING) {
                        $piece2 .= self::uniSmush($ch1, $ch2, $opts['hardBlank']);
                    } else {
                        $next = false;
                        if (!empty($fr['hRule1'])) $next = $next ?: self::hRule1($ch1, $ch2, $opts['hardBlank']);
                        if (!$next && !empty($fr['hRule2'])) $next = self::hRule2($ch1, $ch2);
                        if (!$next && !empty($fr['hRule3'])) $next = self::hRule3($ch1, $ch2);
                        if (!$next && !empty($fr['hRule4'])) $next = self::hRule4($ch1, $ch2);
                        if (!$next && !empty($fr['hRule5'])) $next = self::hRule5($ch1, $ch2);
                        if (!$next && !empty($fr['hRule6'])) $next = self::hRule6($ch1, $ch2, $opts['hardBlank']);
                        if ($next === false) $next = self::uniSmush($ch1, $ch2, $opts['hardBlank']);
                        $piece2 .= $next;
                    }
                } else {
                    $piece2 .= self::uniSmush($ch1, $ch2, $opts['hardBlank']);
                }
            }
            $piece3 = $overlap >= $len2 ? '' : substr($txt2, $overlap, max(0, $len2 - $overlap));
            $output[$ii] = $piece1 . $piece2 . $piece3;
        }
        return $output;
    }

    private static function newFigChar(int $len): array
    {
        return array_fill(0, $len, '');
    }

    private static function figLinesWidth(array $textLines): int
    {
        $w = 0;
        foreach ($textLines as $line) $w = max($w, strlen($line));
        return $w;
    }

    private static function joinFigArray(array $array, int $len, array $opts): array
    {
        $acc = self::newFigChar($len);
        foreach ($array as $data) {
            $acc = self::horizontalSmush($acc, $data['fig'] ?? [], $data['overlap'] ?? 0, $opts);
        }
        return $acc;
    }

    private static function generateFigTextLines(string $txt, array $figChars, array $opts): array
    {
        $height = $opts['height'];
        $fr = $opts['fittingRules'];
        $output = self::newFigChar($height);
        $outputFigLines = [];
        $overlap = 0;
        if ($opts['printDirection'] === 1) $txt = strrev($txt);

        $len = strlen($txt);
        for ($charIndex = 0; $charIndex < $len; $charIndex++) {
            $char = $txt[$charIndex];
            $figChar = $figChars[ord($char)] ?? null;
            if (!$figChar) continue;
            if (($fr['hLayout'] ?? null) !== self::FULL_WIDTH) {
                $overlap = 10000;
                for ($row = 0; $row < $height; $row++) {
                    $overlap = min($overlap, self::getHorizontalSmushLength($output[$row], $figChar[$row], $opts));
                }
                $overlap = $overlap === 10000 ? 0 : $overlap;
            }
            if ($opts['width'] > 0) {
                // Simple line wrapping (whitespace break disabled).
                $textFigLine = self::horizontalSmush($output, $figChar, $overlap, $opts);
                if (self::figLinesWidth($textFigLine) >= $opts['width'] && $charIndex > 0) {
                    $outputFigLines[] = $output;
                    $output = self::newFigChar($height);
                }
            }
            $output = self::horizontalSmush($output, $figChar, $overlap, $opts);
        }
        if (self::figLinesWidth($output) > 0 || $len === 0) $outputFigLines[] = $output;

        if (!$opts['showHardBlanks']) {
            foreach ($outputFigLines as $idx => $figLine) {
                $hb = preg_quote($opts['hardBlank'], '/');
                $outputFigLines[$idx] = array_map(fn($l) => (string)preg_replace('/' . $hb . '/', ' ', $l), $figLine);
            }
        }
        return $outputFigLines;
    }
}