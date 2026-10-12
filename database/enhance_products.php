<?php
/**
 * Clean up the product photos for tinting (ProductPhoto): smooth the fabric's
 * compression noise, shade flatly lit photos towards their sides, and redraw
 * the stepped cut-out outline. After a change to that recipe, run --redo so
 * every photo is rebuilt from its original. New admin uploads
 * are cleaned as they are saved; run this once for the photos already on the
 * site, and after copying photos in another way (DEPLOY.md step 2). Photos
 * already cleaned are skipped. Each original is kept in
 * storage/product-originals/ and the WebP copy is remade.
 *
 *   php database/enhance_products.php          clean what isn't yet
 *   php database/enhance_products.php --redo   redo every photo from its original
 *
 * About 4 seconds a photo. Needs PHP's GD extension. Locally:
 *   php -d extension=gd database/enhance_products.php
 */

require __DIR__ . '/../app/bootstrap.php';

if (!function_exists('imagecreatefromstring')) {
    fwrite(STDERR, "GD is not loaded. Try: php -d extension=gd database/enhance_products.php\n");
    exit(1);
}

$redo = in_array('--redo', $argv, true);
$files = glob(public_path('images/products') . '/*.{png,jpg,jpeg,PNG,JPG,JPEG}', GLOB_BRACE) ?: [];

$done = $current = $skipped = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (ProductPhoto::isEnhanced($file) && !($redo && ProductPhoto::hasOriginal($file))) {
        $current++;
        continue;
    }
    $fromOriginal = $redo && ProductPhoto::isEnhanced($file);
    $start = microtime(true);
    if (ProductPhoto::enhance($file, $fromOriginal)) {
        Webp::sibling($file, ProductPhoto::WEBP_QUALITY);
        printf("  %-28s %.1fs\n", $name, microtime(true) - $start);
        $done++;
    } else {
        printf("  %-28s left as it is (unreadable or larger than 1500x1500)\n", $name);
        $skipped++;
    }
}

printf("%d photos cleaned, %d already done, %d left as they are. Originals: storage/product-originals/\n",
    $done, $current, $skipped);
