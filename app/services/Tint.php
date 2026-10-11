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
    private const L_MIN = 0.19;
    private const L_MAX = 0.96;
    private const MID_GREY = 0.46;
    private const KEEP_FROM = 0.83;
    private const KEEP_TO = 0.93;
    private const SOFT_LIFT = 0.5;
    private const SAT_FULL = 0.25;
    private const DYE_CHROMA = 0.22;
    private const DEEPEN = 0.06;

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
        [$lA, $aA, $bA] = self::rgbToOklab(hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255);
        $c = sqrt($aA * $aA + $bA * $bA);
        $sat = min(1.0, $c / self::SAT_FULL);
        $x = max(0.0, min(1.0, ($c - self::DYE_CHROMA) / (self::SAT_FULL - self::DYE_CHROMA)));
        $lA = max(self::L_MIN, min(self::L_MAX, $lA)) - self::DEEPEN * $x * $x * (3 - 2 * $x);
        $lMid = self::rgbToOklab(self::MID_GREY, self::MID_GREY, self::MID_GREY)[0];
        $out = [[], [], []];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $grey = $i / (self::SAMPLES - 1);
            $lGrey = self::rgbToOklab($grey, $grey, $grey)[0];
            $d = self::FOLD_DEPTH * ($lGrey - $lMid);
            if ($d > 0) $d *= 1 - self::SOFT_LIFT * $sat;
            $l = max(0.0, min(1.0, $lA + $d));
            $chroma = $l < $lA ? $l / $lA : (1 - $l) / (1 - $lA);
            $rgb = self::oklabToRgb($l, $aA * $chroma, $bA * $chroma);
            $x = max(0.0, min(1.0, ($lGrey - self::KEEP_FROM) / (self::KEEP_TO - self::KEEP_FROM)));
            $keep = $x * $x * (3 - 2 * $x);
            foreach ($rgb as $ch => $v) {
                $out[$ch][] = round($v * (1 - $keep) + $grey * $keep, 4);
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

    /** sRGB 0..1 to OKLab. */
    private static function rgbToOklab(float $r, float $g, float $b): array
    {
        [$r, $g, $b] = [self::toLinear($r), self::toLinear($g), self::toLinear($b)];
        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);
        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    /** OKLab to linear sRGB, unclamped. */
    private static function oklabToLinear(float $l, float $a, float $b): array
    {
        $lc = ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $mc = ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $sc = ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;
        return [
             4.0767416621 * $lc - 3.3077115913 * $mc + 0.2309699292 * $sc,
            -1.2684380046 * $lc + 2.6097574011 * $mc - 0.3413193965 * $sc,
            -0.0041960863 * $lc - 0.7034186147 * $mc + 1.7076147010 * $sc,
        ];
    }

    /**
     * OKLab to sRGB 0..1. A colour the screen can't show loses chroma, keeping
     * its lightness and hue, until it fits (clipping each channel shifted hue:
     * red highlights went salmon).
     */
    private static function oklabToRgb(float $l, float $a, float $b): array
    {
        $fits = fn(array $c): bool => min($c) >= -1e-6 && max($c) <= 1 + 1e-6;
        $rgb = self::oklabToLinear($l, $a, $b);
        if (!$fits($rgb)) {
            $lo = 0.0;
            $hi = 1.0;
            for ($i = 0; $i < 16; $i++) {
                $mid = ($lo + $hi) / 2;
                if ($fits(self::oklabToLinear($l, $a * $mid, $b * $mid))) $lo = $mid; else $hi = $mid;
            }
            $rgb = self::oklabToLinear($l, $a * $lo, $b * $lo);
        }
        return [self::toSrgb($rgb[0]), self::toSrgb($rgb[1]), self::toSrgb($rgb[2])];
    }
}
