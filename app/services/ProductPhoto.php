<?php

/**
 * Clean-up of the grey garment photos that the shop tints into each colour
 * (Tint, public/js/lib/color-tint.js). The photos were upscaled, and the
 * colouring brought out two flaws: blotchy compression noise on the fabric
 * and a cut-out outline that steps and smears. enhance() smooths the fabric
 * without softening seams, folds or stitching (a bilateral filter: neighbours
 * count for less the more their tone differs), gives flatly lit photos the
 * falloff towards the sides that the well-shot ones have (volume()), and
 * redraws the outline as a clean edge about 2px wide.
 *
 * A cleaned photo carries a text tag saying so (a PNG tEXt chunk, a JPEG
 * comment), which goes wherever the file is copied, the production export
 * included, so no photo is ever smoothed twice. The photo as uploaded is kept
 * in storage/product-originals/ to redo it from (database/enhance_products.php).
 */
final class ProductPhoto {
    /** WebP quality for product photos: shown large and tinted, so above Webp's default. */
    public const WEBP_QUALITY = 90;

    private const RADIUS = 3;             // smoothing window 7×7
    private const SIGMA_SPACE = 2.0;
    private const SIGMA_RANGE = 7.0;      // tone steps beyond ~15/255 (seams, folds) barely count
    private const EDGE_SIGMA = 1.4;       // outline: blurred, then tightened to ~2px
    private const EDGE_RADIUS = 4;
    private const VOLUME_REACH = 60.0;    // falloff towards the outline: px at 1024 wide (volume())
    private const VOLUME_EDGE = -0.09;    // the band along the outline: this much darker than the middle
    private const VOLUME_MAX = 0.16;      // most a photo is darkened there, as a share of full tone
    // 1500×1500. Larger photos would take too long for an upload request and
    // are left as they are (the shop's photos are 1024×1024).
    private const MAX_PIXELS = 2_250_000;
    private const MARK = 'costaspressjr ProductPhoto v1';

    /**
     * Clean up the photo at $file in place: the version there now is kept as
     * its original, unless $fromOriginal, which redoes it from the kept one.
     * A photo already cleaned is left alone unless $fromOriginal. Returns
     * whether the photo was rewritten.
     */
    public static function enhance(string $file, bool $fromOriginal = false): bool {
        if (!function_exists('imagecreatefromstring') || !preg_match('/\.(png|jpe?g)$/i', $file) || !is_file($file)) {
            return false;
        }
        if (!$fromOriginal && self::isEnhanced($file)) {
            return false;
        }
        $original = self::originalPath($file);
        if ($fromOriginal && !is_file($original)) {
            return false;
        }
        $source = $fromOriginal ? $original : $file;
        $image = @imagecreatefromstring((string)file_get_contents($source));
        if ($image === false || imagesx($image) * imagesy($image) > self::MAX_PIXELS) {
            return false;
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $dir = dirname($original);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('Could not create ' . $dir);
            return false;
        }
        if (!$fromOriginal && !copy($file, $original)) {
            return false;
        }

        @set_time_limit(120);   // a few seconds per photo; an upload can bring four
        self::process($image);

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $isPng = (bool)preg_match('/\.png$/i', $file);
        $written = $isPng ? imagepng($image, $tmp, 9) : imagejpeg($image, $tmp, 92);
        $written = $written && file_put_contents($tmp, self::withMark((string)file_get_contents($tmp), $isPng)) !== false;
        if (!$written || !@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /** Whether $file is a photo enhance() produced: it carries the tag. */
    public static function isEnhanced(string $file): bool {
        $bytes = is_file($file) ? (string)file_get_contents($file) : '';
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            // Walk the chunks: length, type, data, CRC.
            for ($at = 8; $at + 12 <= strlen($bytes); $at += 12 + $len) {
                $len = unpack('N', $bytes, $at)[1];
                $type = substr($bytes, $at + 4, 4);
                if ($type === 'tEXt' && substr($bytes, $at + 8, $len) === "Comment\0" . self::MARK) return true;
                if ($type === 'IEND') break;
            }
        } elseif (str_starts_with($bytes, "\xFF\xD8")) {
            // Walk the segments up to the image data (SOS).
            for ($at = 2; $at + 4 <= strlen($bytes) && $bytes[$at] === "\xFF"; $at += 2 + $len) {
                $marker = ord($bytes[$at + 1]);
                $len = unpack('n', $bytes, $at + 2)[1];
                if ($marker === 0xFE && substr($bytes, $at + 4, $len - 2) === self::MARK) return true;
                if ($marker === 0xDA) break;
            }
        }
        return false;
    }

    /** $bytes with the tag added: a tEXt chunk before IEND, or a comment after SOI. */
    private static function withMark(string $bytes, bool $isPng): string {
        if ($isPng) {
            $chunk = "tEXt" . "Comment\0" . self::MARK;
            $iend = strrpos($bytes, 'IEND') - 4;
            return substr($bytes, 0, $iend) . pack('N', strlen($chunk) - 4) . $chunk . pack('N', crc32($chunk)) . substr($bytes, $iend);
        }
        return substr($bytes, 0, 2) . "\xFF\xFE" . pack('n', strlen(self::MARK) + 2) . self::MARK . substr($bytes, 2);
    }

    /** Whether the original of $file is kept, so it can be redone. */
    public static function hasOriginal(string $file): bool {
        return is_file(self::originalPath($file));
    }

    private static function originalPath(string $file): string {
        return dirname(__DIR__, 2) . '/storage/product-originals/' . basename($file);
    }

    /**
     * Smooth the fabric, shade it towards the sides, then redraw the outline.
     * Channels are held as byte strings (one byte a pixel) to stay well inside
     * PHP's memory limit.
     */
    private static function process(\GdImage $image): void {
        $w = imagesx($image);
        $h = imagesy($image);
        $n = $w * $h;
        $r = $g = $b = $lum = $op = str_repeat("\0", $n);
        for ($y = 0, $i = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++, $i++) {
                $c = imagecolorat($image, $x, $y);
                $cr = ($c >> 16) & 0xFF; $cg = ($c >> 8) & 0xFF; $cb = $c & 0xFF;
                $r[$i] = chr($cr); $g[$i] = chr($cg); $b[$i] = chr($cb);
                $lum[$i] = chr((int)(($cr * 77 + $cg * 150 + $cb * 29) >> 8));
                $op[$i] = chr(255 - (int)round((($c >> 24) & 0x7F) * 255 / 127));
            }
        }

        [$r1, $g1, $b1] = self::smooth($r, $g, $b, $lum, $op, $w, $h);
        [$r1, $g1, $b1] = self::volume($r1, $g1, $b1, $op, $w, $h);
        [$r2, $g2, $b2, $op2] = self::outline($r1, $g1, $b1, $op, $w, $h);

        imagealphablending($image, false);
        for ($y = 0, $i = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++, $i++) {
                $alpha = 127 - (int)round(ord($op2[$i]) * 127 / 255);
                imagesetpixel($image, $x, $y, ($alpha << 24) | (ord($r2[$i]) << 16) | (ord($g2[$i]) << 8) | ord($b2[$i]));
            }
        }
    }

    /**
     * The falloff a lit garment has towards its sides. Some supplier photos
     * are evenly lit, or even lighter at the edges (a halo from the white they
     * were cut out of), and tinted in a strong colour they read as a flat
     * cut-out: the tank top in red. This measures how much darker the band
     * along the outline is than the middle and darkens towards the outline by
     * only what is missing to reach VOLUME_EDGE, so photos already shaded (the
     * zip hoodies) barely change. The tones then shift back so the middle keeps
     * its grey, the midtone Tint turns into the colour itself.
     */
    private static function volume(string $r, string $g, string $b, string $op, int $w, int $h): array {
        // Distance to the outline, on a grid a quarter the size: the falloff
        // spans dozens of pixels, so the coarse grid loses nothing and is quick.
        $s  = 4;
        $gw = intdiv($w + $s - 1, $s);
        $gh = intdiv($h + $s - 1, $s);
        $far = 1e9;
        $d = $lum = [];
        for ($gy = 0; $gy < $gh; $gy++) {
            for ($gx = 0; $gx < $gw; $gx++) {
                $i = min($h - 1, $gy * $s + 1) * $w + min($w - 1, $gx * $s + 1);
                $d[] = ord($op[$i]) < 128 ? 0.0 : $far;
                $lum[] = ord($op[$i]) >= 250 ? (ord($r[$i]) * 77 + ord($g[$i]) * 150 + ord($b[$i]) * 29) >> 8 : -1;
            }
        }
        // Chamfer distance, two passes: 1 across, √2 diagonally.
        for ($y = 0; $y < $gh; $y++) {
            for ($x = 0, $i = $y * $gw; $x < $gw; $x++, $i++) {
                if ($d[$i] == 0) continue;
                $m = $d[$i];
                if ($x > 0) $m = min($m, $d[$i - 1] + 1);
                if ($y > 0) {
                    $m = min($m, $d[$i - $gw] + 1);
                    if ($x > 0) $m = min($m, $d[$i - $gw - 1] + M_SQRT2);
                    if ($x < $gw - 1) $m = min($m, $d[$i - $gw + 1] + M_SQRT2);
                }
                $d[$i] = $m;
            }
        }
        for ($y = $gh - 1; $y >= 0; $y--) {
            for ($x = $gw - 1, $i = $y * $gw + $x; $x >= 0; $x--, $i--) {
                if ($d[$i] == 0) continue;
                $m = $d[$i];
                if ($x < $gw - 1) $m = min($m, $d[$i + 1] + 1);
                if ($y < $gh - 1) {
                    $m = min($m, $d[$i + $gw] + 1);
                    if ($x < $gw - 1) $m = min($m, $d[$i + $gw + 1] + M_SQRT2);
                    if ($x > 0) $m = min($m, $d[$i + $gw - 1] + M_SQRT2);
                }
                $d[$i] = $m;
            }
        }

        // The shading profile exp(−distance / reach); the middle's median; the
        // edge band's mean, weighted by the profile.
        $reach = self::VOLUME_REACH * $w / 1024 / $s;
        $weight = $inner = [];
        $band = $sumW = $sumW2 = 0.0;
        foreach ($d as $i => $dist) {
            $weight[$i] = $dist >= $far ? 0.0 : exp(-$dist / $reach);
            if ($lum[$i] < 0) continue;
            if ($dist > 3 * $reach) $inner[] = $lum[$i];
            $band  += $weight[$i] * $lum[$i];
            $sumW  += $weight[$i];
            $sumW2 += $weight[$i] ** 2;
        }
        if (!$inner || $sumW2 <= 0) {
            return [$r, $g, $b];   // too small or too thin to have a middle
        }
        sort($inner);
        $median = $inner[intdiv(count($inner), 2)];
        $lack = ($band / $sumW - $median) / 255 - self::VOLUME_EDGE;   // how much lighter the band is than wanted
        $amp = max(0.0, min(self::VOLUME_MAX, $lack)) * 255;
        if ($amp < 1) {
            return [$r, $g, $b];
        }
        $scale = $amp * $sumW / $sumW2;   // so the band's weighted mean drops by $amp

        // The middle's grey back where it was: the median of the shaded tones.
        $after = [];
        foreach ($lum as $i => $v) {
            if ($v >= 0) $after[] = $v - $scale * $weight[$i];
        }
        sort($after);
        $lift = $median - $after[intdiv(count($after), 2)];

        // Full size, the profile read off the grid bilinearly.
        $shade = [];
        foreach ($weight as $i => $wt) $shade[$i] = $scale * $wt;
        for ($y = 0, $i = 0; $y < $h; $y++) {
            $fy = min($gh - 1.0, max(0.0, ($y - 1) / $s));
            $y0 = (int)$fy; $y1 = min($gh - 1, $y0 + 1); $ty = $fy - $y0;
            for ($x = 0; $x < $w; $x++, $i++) {
                if ($op[$i] === "\0") continue;
                $fx = min($gw - 1.0, max(0.0, ($x - 1) / $s));
                $x0 = (int)$fx; $x1 = min($gw - 1, $x0 + 1); $tx = $fx - $x0;
                $top = $shade[$y0 * $gw + $x0] * (1 - $tx) + $shade[$y0 * $gw + $x1] * $tx;
                $bot = $shade[$y1 * $gw + $x0] * (1 - $tx) + $shade[$y1 * $gw + $x1] * $tx;
                $delta = $lift - ($top * (1 - $ty) + $bot * $ty);
                $r[$i] = chr(max(0, min(255, (int)round(ord($r[$i]) + $delta))));
                $g[$i] = chr(max(0, min(255, (int)round(ord($g[$i]) + $delta))));
                $b[$i] = chr(max(0, min(255, (int)round(ord($b[$i]) + $delta))));
            }
        }
        return [$r, $g, $b];
    }

    /** Bilateral smoothing of the fully opaque pixels; edge pixels are left to outline(). */
    private static function smooth(string $r, string $g, string $b, string $lum, string $op, int $w, int $h): array {
        $rad = self::RADIUS;
        $offsets = $spatial = [];
        for ($dy = -$rad; $dy <= $rad; $dy++) {
            for ($dx = -$rad; $dx <= $rad; $dx++) {
                $offsets[] = $dy * $w + $dx;
                $spatial[] = exp(-($dx * $dx + $dy * $dy) / (2 * self::SIGMA_SPACE ** 2)) / 255;
            }
        }
        $range = [];
        for ($d = 0; $d < 256; $d++) {
            $range[$d] = exp(-($d * $d) / (2 * self::SIGMA_RANGE ** 2));
        }
        $taps = count($offsets);

        // The shop's photos are neutral grey: one channel does for all three.
        if ($r === $g && $g === $b) {
            $out = $r;
            for ($y = $rad; $y < $h - $rad; $y++) {
                for ($x = $rad, $i = $y * $w + $rad; $x < $w - $rad; $x++, $i++) {
                    if ($op[$i] !== "\xFF") continue;
                    $c = ord($r[$i]);
                    $sv = $sw = 0.0;
                    for ($t = 0; $t < $taps; $t++) {
                        $j = $i + $offsets[$t];
                        $o = ord($op[$j]);
                        if ($o === 0) continue;
                        $v = ord($r[$j]);
                        $k = $spatial[$t] * $range[abs($v - $c)] * $o;
                        $sv += $k * $v;
                        $sw += $k;
                    }
                    $out[$i] = chr((int)round($sv / $sw));
                }
            }
            return [$out, $out, $out];
        }

        $outR = $r; $outG = $g; $outB = $b;
        for ($y = $rad; $y < $h - $rad; $y++) {
            for ($x = $rad, $i = $y * $w + $rad; $x < $w - $rad; $x++, $i++) {
                if ($op[$i] !== "\xFF") continue;
                $c = ord($lum[$i]);
                $sr = $sg = $sb = $sw = 0.0;
                for ($t = 0; $t < $taps; $t++) {
                    $j = $i + $offsets[$t];
                    $o = ord($op[$j]);
                    if ($o === 0) continue;
                    $k = $spatial[$t] * $range[abs(ord($lum[$j]) - $c)] * $o;
                    $sr += $k * ord($r[$j]);
                    $sg += $k * ord($g[$j]);
                    $sb += $k * ord($b[$j]);
                    $sw += $k;
                }
                $outR[$i] = chr((int)round($sr / $sw));
                $outG[$i] = chr((int)round($sg / $sw));
                $outB[$i] = chr((int)round($sb / $sw));
            }
        }
        return [$outR, $outG, $outB];
    }

    /**
     * Redraw the cut-out edge: blur the mask and tighten it again with a
     * smoothstep, which turns the upscaled stair steps into a smooth ~2px
     * edge. Pixels that become visible take the colour of the fabric next
     * to them. A photo without transparency (JPEG) has no edge to redraw.
     */
    private static function outline(string $r, string $g, string $b, string $op, int $w, int $h): array {
        $rad = self::EDGE_RADIUS;
        $n = $w * $h;

        // Pixels within reach of the edge: any partial pixel or any change of
        // opacity, widened by the blur's reach.
        $band = str_repeat("\0", $n);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0, $i = $y * $w; $x < $w; $x++, $i++) {
                $o = $op[$i];
                $edge = ($o !== "\xFF" && $o !== "\0")
                    || ($x + 1 < $w && $op[$i + 1] !== $o)
                    || ($y + 1 < $h && $op[$i + $w] !== $o);
                if (!$edge) continue;
                for ($yy = max(0, $y - $rad); $yy <= min($h - 1, $y + $rad + 1); $yy++) {
                    $row = $yy * $w;
                    for ($xx = max(0, $x - $rad); $xx <= min($w - 1, $x + $rad + 1); $xx++) {
                        $band[$row + $xx] = "\1";
                    }
                }
            }
        }

        $kernel = [];
        for ($dy = -$rad; $dy <= $rad; $dy++) {
            for ($dx = -$rad; $dx <= $rad; $dx++) {
                $kernel[] = [$dx, $dy, exp(-($dx * $dx + $dy * $dy) / (2 * self::EDGE_SIGMA ** 2))];
            }
        }

        $outR = $r; $outG = $g; $outB = $b; $outOp = $op;
        for ($i = strpos($band, "\1"); $i !== false; $i = strpos($band, "\1", $i + 1)) {
            $x = $i % $w;
            $y = intdiv($i, $w);
            $sk = $so = $sr = $sg = $sb = 0.0;
            foreach ($kernel as [$dx, $dy, $k]) {
                $xx = $x + $dx; $yy = $y + $dy;
                if ($xx < 0 || $yy < 0 || $xx >= $w || $yy >= $h) continue;
                $j = $yy * $w + $xx;
                $sk += $k;
                $o = ord($op[$j]);
                if ($o === 0) continue;
                $ko = $k * $o;
                $so += $ko;
                $sr += $ko * ord($r[$j]);
                $sg += $ko * ord($g[$j]);
                $sb += $ko * ord($b[$j]);
            }
            $t = max(0.0, min(1.0, ($so / $sk / 255 - 0.3) / 0.4));
            $outOp[$i] = chr((int)round($t * $t * (3 - 2 * $t) * 255));
            if ($op[$i] !== "\xFF" && $so > 0) {
                $outR[$i] = chr((int)round($sr / $so));
                $outG[$i] = chr((int)round($sg / $so));
                $outB[$i] = chr((int)round($sb / $so));
            }
        }
        return [$outR, $outG, $outB, $outOp];
    }
}
