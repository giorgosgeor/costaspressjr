<?php

/**
 * WebP copies of PNG and JPEG images (audit P1/P2): "x.png" gets "x.webp"
 * beside it, which public/.htaccess serves in its place to browsers that take
 * WebP. For product photos and design previews that is about a tenth of the
 * size. Without GD's WebP support nothing is written and the originals are
 * served as before; database/make_webp.php fills in what is missing.
 */
final class Webp {
    /** Whether this PHP can write WebP (GD with WebP support). */
    public static function available(): bool {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /**
     * Write or refresh the WebP copy of $file. Kept only when it is smaller
     * than the original, so it never makes a page heavier. Returns whether a
     * copy exists afterwards.
     */
    public static function sibling(string $file, int $quality = 80): bool {
        if (!self::available() || !preg_match('/\.(png|jpe?g)$/i', $file) || !is_file($file)) {
            return false;
        }
        $target = preg_replace('/\.(png|jpe?g)$/i', '.webp', $file);
        $image = @imagecreatefromstring((string)file_get_contents($file));
        if ($image === false) {
            return false;
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $written = @imagewebp($image, $tmp, $quality);
        if ($written && filesize($tmp) < filesize($file) && @rename($tmp, $target)) {
            return true;
        }
        @unlink($tmp);
        if (is_file($target)) {
            @unlink($target);   // an older copy of what is now a different image
        }
        return false;
    }
}
