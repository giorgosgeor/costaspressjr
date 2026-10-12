<?php

/**
 * The picture a product or design page shows when it is shared (og:image),
 * at the 1200×630 those sites ask for. The photos are transparent cut-outs,
 * which WhatsApp, Facebook and the rest would lay on a colour of their own;
 * a card is opaque. A garment shows white on black, as in the shop (see
 * make()); a design's artwork sits on the grey tile (studio.css --st-tile).
 *
 * database/make_share_cards.php makes them into public/images/share/,
 * mirroring the photo's path: images/products/1.png → images/share/products/1.jpg.
 * A page whose card isn't there yet shares the branded og-card.png instead.
 */
class ShareCard
{
    public const WIDTH  = 1200;
    public const HEIGHT = 630;
    public const DEFAULT = '/images/og-card.png';
    private const TILE    = [0xF4, 0xF3, 0xF0];
    private const QUALITY = 85;

    /** The card's path under public/ for a stored image path ('images/products/1.png'). */
    public static function pathFor(string $image): string
    {
        $rel = preg_replace('#^images/#', '', ltrim(web_path($image), '/'));
        return 'images/share/' . preg_replace('/\.[A-Za-z0-9]+$/', '', $rel) . '.jpg';
    }

    /** The web path to share for an image: its card, or the branded one while there is none. */
    public static function urlFor(?string $image): string
    {
        if ($image !== null && $image !== '') {
            $card = self::pathFor($image);
            if (is_file(public_path($card))) {
                return '/' . $card;
            }
        }
        return self::DEFAULT;
    }

    /** Whether GD can make cards here (database/make_share_cards.php checks first). */
    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /**
     * Make (or remake) the card for an image file. False when GD can't read it.
     *
     * $garment: a product photo. Those are grey (they take any colour), and the
     * shop shows them white wherever no colour is chosen, on black
     * (studio.css: the filter on images/products/ and .garment-stage). The card
     * shows them the same way. Anything else (a design's artwork) goes on the
     * grey tile as it is.
     */
    public static function make(string $source, string $target, bool $garment = false): bool
    {
        $img = @imagecreatefromstring((string)@file_get_contents($source));
        if (!$img) {
            return false;
        }
        $card = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($card, 0, 0, imagecolorallocate($card, ...($garment ? [0, 0, 0] : self::TILE)));

        // The picture fills 88% of the card's height (or width), centred.
        $w     = imagesx($img);
        $h     = imagesy($img);
        $scale = min(self::HEIGHT * 0.88 / $h, self::WIDTH * 0.88 / $w);
        $dw    = (int)round($w * $scale);
        $dh    = (int)round($h * $scale);

        // Scaled first, keeping its transparency, so the white filter below
        // runs on the card's pixels rather than the full-size photo's.
        $pic = imagecreatetruecolor($dw, $dh);
        imagealphablending($pic, false);
        imagesavealpha($pic, true);
        imagefill($pic, 0, 0, imagecolorallocatealpha($pic, 0, 0, 0, 127));
        imagecopyresampled($pic, $img, 0, 0, 0, 0, $dw, $dh, $w, $h);
        if ($garment) {
            self::whiten($pic);
        }
        imagealphablending($card, true);
        imagecopy($card, $pic, intdiv(self::WIDTH - $dw, 2), intdiv(self::HEIGHT - $dh, 2), 0, 0, $dw, $dh);

        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
            return false;
        }
        return imagejpeg($card, $target, self::QUALITY);
    }

    /**
     * The white a grey garment photo shows in the shop, worked per pixel as
     * the browser works studio.css's grayscale(1) brightness(2.2)
     * contrast(0.85): in sRGB, each step clamped to 0..1. Transparency kept.
     */
    private static function whiten(\GdImage $im): void
    {
        $w = imagesx($im);
        $h = imagesy($im);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a === 127) {
                    continue;
                }
                $grey = (0.2126 * (($c >> 16) & 0xFF) + 0.7152 * (($c >> 8) & 0xFF) + 0.0722 * ($c & 0xFF)) / 255;
                $v    = (int)round((min(1.0, $grey * 2.2) * 0.85 + 0.075) * 255);
                imagesetpixel($im, $x, $y, ($a << 24) | ($v << 16) | ($v << 8) | $v);
            }
        }
    }
}
