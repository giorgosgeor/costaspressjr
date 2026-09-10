<?php
/**
 * Garment mockup colour tinting, server side.
 *
 * PHP mirror of public/js/color-tint.js. Some previews are rendered on the
 * server (saved-design cards, cart lines, the admin order view) and some in the
 * browser, and they must agree — a design card showing a different shade from
 * the studio that produced it looks like a bug to the person who saved it.
 *
 * Keep this file and color-tint.js in step: same formula, same override table.
 * The reasoning behind the overrides is documented there.
 */
class Tint
{
    /**
     * Solved chains for colours the generic formula flattens. Each was found
     * numerically against the real mockup art, maximising L* spread over
     * garment pixels under a CIE76 dE budget on the midtone.
     */
    private const OVERRIDES = [
        // Generic formula measures L* spread 2.1 here; this gives 6.6.
        '#ff0000' => 'grayscale(1) brightness(0.4) sepia(1) saturate(4) hue-rotate(-40deg) saturate(3.25)',
        // Generic formula asks for saturate(8.0), which clamps the red channel
        // on 81.5% of garment pixels and flattens the shirt: spread 1.4 against
        // orange's 7.1. This gives 4.8 with midtone #951d12 (dE 6.9 vs 26.6).
        '#800000' => 'grayscale(1) brightness(0.2852) sepia(1) saturate(3.565) hue-rotate(-43.55deg) saturate(2.086) brightness(1.358)',
    ];

    public static function normalizeHex(?string $hex): string
    {
        $hex = strtolower(trim((string)$hex));
        if ($hex === '') return '';
        if ($hex[0] !== '#') $hex = '#' . $hex;
        if (strlen($hex) === 4) {
            $hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
        }
        return $hex;
    }

    /** @return array{h: float, s: float, l: float} */
    public static function hexToHsl(string $hex): array
    {
        $hex = ltrim(self::normalizeHex($hex), '#');
        if (strlen($hex) !== 6) return ['h' => 0.0, 's' => 0.0, 'l' => 0.0];
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = 0.0;
        $s = 0.0;
        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            if ($max === $r)      $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
            elseif ($max === $g)  $h = (($b - $r) / $d + 2) / 6;
            else                  $h = (($r - $g) / $d + 4) / 6;
        }
        return ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
    }

    /**
     * Round to 4dp and drop trailing zeros. PHP and JS print floats to
     * different precisions ("2.36" vs "2.3599999999999994"), which is harmless
     * to CSS but makes it impossible to assert the two implementations agree.
     * Rounding both the same way keeps them byte-identical and testable.
     */
    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format(round($v, 4), 4, '.', ''), '0'), '.');
    }

    /** Complete CSS filter chain for a swatch colour. */
    public static function filterFor(?string $hex): string
    {
        $norm = self::normalizeHex($hex);
        if ($norm === '') return 'none';
        if (isset(self::OVERRIDES[$norm])) return self::OVERRIDES[$norm];

        $hsl = self::hexToHsl($norm);
        if ($norm === '#ffffff' || $hsl['l'] > 95) return 'grayscale(1) brightness(2.2) contrast(0.85)';
        if ($hsl['l'] < 10)                        return 'grayscale(1) brightness(0.45) contrast(1.2)';
        if ($hsl['s'] < 10)                        return 'grayscale(1) brightness(' . self::num(0.2 + ($hsl['l'] / 100) * 1.5) . ')';

        $hueRotate   = $hsl['h'] - 38; // sepia base hue ~38deg
        $isReddish   = $hsl['h'] <= 20 || $hsl['h'] >= 340;
        $isYellowish = $hsl['h'] >= 45 && $hsl['h'] <= 80;

        $saturate = ($hsl['s'] / 100) * 3 + 0.8;
        // Capped: past ~4.5 the red channel clamps across most of the garment
        // and the shading is gone. Deep reds get a solved override instead.
        if ($isReddish)   $saturate = min(($hsl['s'] / 100) * 6 + 2.0, 4.5);
        if ($isYellowish) $saturate = ($hsl['s'] / 100) * 4 + 1.0;

        if ($hsl['l'] < 30)      $brightness = 0.3 + ($hsl['l'] / 100) * 0.7;
        elseif ($hsl['l'] < 50)  $brightness = 0.5 + ($hsl['l'] / 100) * 0.6;
        else                     $brightness = 0.6 + ($hsl['l'] / 100) * 0.5;
        if ($isYellowish && $hsl['l'] >= 45) $brightness = min($brightness * 1.25, 1.5);

        return 'grayscale(1) sepia(1) saturate(' . self::num($saturate)
             . ') hue-rotate(' . self::num($hueRotate)
             . 'deg) brightness(' . self::num($brightness) . ')';
    }
}
