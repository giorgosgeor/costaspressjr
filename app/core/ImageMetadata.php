<?php

/**
 * Removes the metadata from an uploaded image (audit S12). A phone photo's
 * EXIF can say where and when it was taken and with which phone, and
 * customers' uploads are served publicly. Only metadata blocks are removed:
 * the pixels are not re-encoded, so nothing is lost for printing, and what
 * the image needs to look right stays: a JPEG's colour profile and its
 * Orientation tag (without which a portrait phone photo shows, and prints,
 * sideways).
 *
 * JPEG, PNG and WebP, the formats photos come in. Anything that can't be
 * parsed is returned unchanged: the metadata at stake is the uploader's own.
 */
final class ImageMetadata {
    // PNG chunks kept besides the critical ones: what decoding, colour,
    // transparency and animation need. Text, EXIF and timestamps go.
    private const PNG_KEEP = ['tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'bKGD', 'pHYs', 'hIST', 'cICP', 'mDCv', 'cLLi', 'acTL', 'fcTL', 'fdAT'];
    private const WEBP_KEEP = ['VP8 ', 'VP8L', 'VP8X', 'ICCP', 'ANIM', 'ANMF', 'ALPH'];

    public static function strip(string $bytes, string $mime): string {
        $stripped = match ($mime) {
            'image/jpeg' => self::jpeg($bytes),
            'image/png'  => self::png($bytes),
            'image/webp' => self::webp($bytes),
            default      => null,
        };
        return $stripped ?? $bytes;
    }

    private static function jpeg(string $s): ?string {
        if (substr($s, 0, 2) !== "\xFF\xD8") return null;
        $out = "\xFF\xD8";
        $orientation = 1;
        $len = strlen($s);
        $pos = 2;
        $insertAt = 2;   // where an Orientation block goes: after SOI, or after APP0 (JFIF)
        while ($pos < $len) {
            if ($s[$pos] !== "\xFF") return null;
            while ($pos < $len && $s[$pos] === "\xFF") $pos++;          // fill bytes
            if ($pos >= $len) return null;
            $marker = ord($s[$pos++]);
            if ($marker === 0xD9) {                                     // EOI: anything after it is dropped
                $out .= "\xFF\xD9";
                break;
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $out .= "\xFF" . chr($marker);                          // no length
                continue;
            }
            if ($pos + 2 > $len) return null;
            $size = unpack('n', substr($s, $pos, 2))[1];
            if ($size < 2 || $pos + $size > $len) return null;
            $segment = "\xFF" . chr($marker) . substr($s, $pos, $size);
            $data = substr($s, $pos + 2, $size - 2);
            $pos += $size;

            if ($marker === 0xDA) {
                // Start of scan: the entropy-coded data runs to the next marker
                // that isn't a restart marker or a stuffed 0xFF00.
                $end = $pos;
                while (($end = strpos($s, "\xFF", $end)) !== false && $end + 1 < $len) {
                    $next = ord($s[$end + 1]);
                    if ($next !== 0x00 && !($next >= 0xD0 && $next <= 0xD7)) break;
                    $end += 2;
                }
                if ($end === false || $end + 1 >= $len) return null;
                $out .= $segment . substr($s, $pos, $end - $pos);
                $pos = $end;
                continue;
            }
            $isApp = $marker >= 0xE0 && $marker <= 0xEF;
            if ($marker === 0xE1 && str_starts_with($data, "Exif\0\0")) {
                $orientation = self::exifOrientation(substr($data, 6)) ?? $orientation;
            }
            $keep = !$isApp && $marker !== 0xFE                            // COM goes
                || $marker === 0xE0                                         // JFIF
                || ($marker === 0xE2 && str_starts_with($data, "ICC_PROFILE\0"))
                || ($marker === 0xEE && str_starts_with($data, 'Adobe'));   // colour transform
            if ($keep) {
                $out .= $segment;
                if ($marker === 0xE0 && $insertAt === 2) $insertAt = strlen($out);
            }
        }
        if (!str_ends_with($out, "\xFF\xD9")) return null;
        if ($orientation !== 1) {
            // A minimal EXIF block with only the Orientation tag (big-endian TIFF).
            $tiff = "MM\x00\x2A" . pack('N', 8) . pack('n', 1) . pack('nnN', 0x0112, 3, 1) . pack('n', $orientation) . "\x00\x00" . pack('N', 0);
            $app1 = "\xFF\xE1" . pack('n', 8 + strlen($tiff)) . "Exif\0\0" . $tiff;
            $out = substr($out, 0, $insertAt) . $app1 . substr($out, $insertAt);
        }
        return $out;
    }

    /** The Orientation tag (1–8) from a TIFF/EXIF block, or null. */
    private static function exifOrientation(string $tiff): ?int {
        $order = substr($tiff, 0, 2);
        if ($order !== 'II' && $order !== 'MM') return null;
        $u16 = fn(int $at) => strlen($tiff) >= $at + 2 ? unpack($order === 'II' ? 'v' : 'n', substr($tiff, $at, 2))[1] : null;
        $u32 = fn(int $at) => strlen($tiff) >= $at + 4 ? unpack($order === 'II' ? 'V' : 'N', substr($tiff, $at, 4))[1] : null;
        $ifd = $u32(4);
        $count = $ifd === null ? null : $u16($ifd);
        for ($i = 0; $count !== null && $i < $count; $i++) {
            $entry = $ifd + 2 + 12 * $i;
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);
                return $value !== null && $value >= 1 && $value <= 8 ? $value : null;
            }
        }
        return null;
    }

    private static function png(string $s): ?string {
        if (substr($s, 0, 8) !== "\x89PNG\r\n\x1a\n") return null;
        $out = substr($s, 0, 8);
        $len = strlen($s);
        for ($pos = 8; $pos + 12 <= $len; ) {
            $size = unpack('N', substr($s, $pos, 4))[1];
            $type = substr($s, $pos + 4, 4);
            if ($pos + 12 + $size > $len) return null;
            // A critical chunk (upper-case first letter) is always kept.
            if (ctype_upper($type[0]) || in_array($type, self::PNG_KEEP, true)) {
                $out .= substr($s, $pos, 12 + $size);
            }
            $pos += 12 + $size;
            if ($type === 'IEND') return $out;
        }
        return null;
    }

    private static function webp(string $s): ?string {
        if (substr($s, 0, 4) !== 'RIFF' || substr($s, 8, 4) !== 'WEBP') return null;
        $body = '';
        $len = min(strlen($s), 8 + unpack('V', substr($s, 4, 4))[1]);
        for ($pos = 12; $pos + 8 <= $len; ) {
            $type = substr($s, $pos, 4);
            $size = unpack('V', substr($s, $pos + 4, 4))[1];
            $padded = 8 + $size + ($size & 1);
            if ($pos + 8 + $size > $len) return null;
            if (in_array($type, self::WEBP_KEEP, true)) {
                $chunk = substr($s, $pos, $padded);
                if ($type === 'VP8X') {
                    $chunk[8] = chr(ord($chunk[8]) & ~0x0C);   // clear the EXIF and XMP flags
                }
                $body .= $chunk;
            }
            $pos += $padded;
        }
        return 'RIFF' . pack('V', 4 + strlen($body)) . 'WEBP' . $body;
    }
}
