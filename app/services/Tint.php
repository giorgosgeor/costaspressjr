<?php
/**
 * Garment colour, server side: the PHP twin of public/js/lib/color-tint.js,
 * which explains the method. Keep the two in step: same constants, same
 * formula. Some previews are rendered here (saved-design cards, cart and
 * checkout lines, the admin order view) and some in the browser, and they
 * must agree: a card showing a different shade from the studio that made it
 * looks like a bug to the person who saved it.
 *
 * filterFor() returns the CSS filter for a garment in a colour and notes the
 * colour; svgDefs(), printed by the layouts at the end of the page, holds the
 * SVG filters those CSS filters point at.
 */
class Tint
{
    private const SAMPLES = 64;
    private const FOLD_DEPTH = 0.85;
    private const L_MIN = 6.0;
    private const L_MAX = 95.0;
    private const MID_GREY = 0.46;
    private const KEEP_FROM = 80.0;
    private const KEEP_TO = 92.0;

    /** Colours filterFor() handed out on this page, for svgDefs(). */
    private static array $used = [];

    /** "#rrggbb", lower case, or '' for anything that isn't a colour. */
    public static function normalizeHex(?string $hex): string
    {
        $hex = strtolower(trim((string)$hex));
        if ($hex !== '' && $hex[0] !== '#') $hex = '#' . $hex;
        if (preg_match('/^#[0-9a-f]{3}$/', $hex)) {
            $hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
        }
        return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : '';
    }

    /** CSS filter for a garment in this colour; "none" for anything that isn't a colour. */
    public static function filterFor(?string $hex): string
    {
        $hex = self::normalizeHex($hex);
        if ($hex === '') return 'none';
        self::$used[$hex] = true;
        return 'url(#tint-' . substr($hex, 1) . ')';
    }

    /** The SVG filters for every colour filterFor() was asked for; '' if none. */
    public static function svgDefs(): string
    {
        if (!self::$used) return '';
        $filters = '';
        foreach (array_keys(self::$used) as $hex) {
            $funcs = '';
            foreach (self::tables($hex) as $c => $table) {
                $funcs .= '<feFunc' . 'RGB'[$c] . ' type="table" tableValues="' . implode(' ', $table) . '"/>';
            }
            $filters .= '<filter id="tint-' . substr($hex, 1) . '" color-interpolation-filters="sRGB">'
                      . '<feComponentTransfer>' . $funcs . '</feComponentTransfer></filter>';
        }
        // Not display:none, which some browsers skip filters in.
        return '<svg width="0" height="0" aria-hidden="true" style="position:absolute"><defs id="tint-defs">'
             . $filters . '</defs></svg>';
    }

    /** The three tables for a colour: [r[], g[], b[]], SAMPLES values each, 0..1. */
    public static function tables(string $hex): array
    {
        $hex = self::normalizeHex($hex);
        [$lA, $aA, $bA] = self::rgbToLab(hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255);
        $lA = max(self::L_MIN, min(self::L_MAX, $lA));
        $lMid = self::rgbToLab(self::MID_GREY, self::MID_GREY, self::MID_GREY)[0];
        $out = [[], [], []];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $grey = $i / (self::SAMPLES - 1);
            $lGrey = self::rgbToLab($grey, $grey, $grey)[0];
            $l = max(0.0, min(100.0, $lA + self::FOLD_DEPTH * ($lGrey - $lMid)));
            $chroma = $l < $lA ? $l / $lA : (100 - $l) / (100 - $lA);
            $rgb = self::labToRgb($l, $aA * $chroma, $bA * $chroma);
            $x = max(0.0, min(1.0, ($lGrey - self::KEEP_FROM) / (self::KEEP_TO - self::KEEP_FROM)));
            $keep = $x * $x * (3 - 2 * $x);
            foreach ($rgb as $c => $v) {
                $out[$c][] = round($v * (1 - $keep) + $grey * $keep, 4);
            }
        }
        return $out;
    }

    private static function toLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function toSrgb(float $c): float
    {
        $c = max(0.0, min(1.0, $c));
        return $c <= 0.0031308 ? $c * 12.92 : 1.055 * $c ** (1 / 2.4) - 0.055;
    }

    /** sRGB 0..1 to CIE Lab (D65). */
    private static function rgbToLab(float $r, float $g, float $b): array
    {
        [$r, $g, $b] = [self::toLinear($r), self::toLinear($g), self::toLinear($b)];
        $f = fn(float $t): float => $t > 216 / 24389 ? $t ** (1 / 3) : (24389 / 27 * $t + 16) / 116;
        $x = $f((0.4124 * $r + 0.3576 * $g + 0.1805 * $b) / 0.95047);
        $y = $f(0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
        $z = $f((0.0193 * $r + 0.1192 * $g + 0.9505 * $b) / 1.08883);
        return [116 * $y - 16, 500 * ($x - $y), 200 * ($y - $z)];
    }

    /** CIE Lab to sRGB 0..1, each channel clamped. */
    private static function labToRgb(float $l, float $a, float $b): array
    {
        $inv = fn(float $t): float => $t ** 3 > 216 / 24389 ? $t ** 3 : (116 * $t - 16) / (24389 / 27);
        $fy = ($l + 16) / 116;
        $x = 0.95047 * $inv($fy + $a / 500);
        $y = $inv($fy);
        $z = 1.08883 * $inv($fy - $b / 200);
        return [
            self::toSrgb( 3.2406 * $x - 1.5372 * $y - 0.4986 * $z),
            self::toSrgb(-0.9689 * $x + 1.8758 * $y + 0.0415 * $z),
            self::toSrgb( 0.0557 * $x - 0.2040 * $y + 1.0570 * $z),
        ];
    }
}
