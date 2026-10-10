<?php
/**
 * Make the WebP copies of product photos and design previews (audit P1/P2)
 * that public/.htaccess sends in place of the PNGs to browsers that take
 * WebP: about a tenth of the size. New admin uploads and new previews get
 * theirs as they are saved (Webp::sibling); run this for images that arrived
 * another way, such as the product photos uploaded at deployment
 * (DEPLOY.md step 2). A copy that is already newer than its image is kept.
 *
 *   php database/make_webp.php
 *
 * Needs PHP's GD extension with WebP, which hosts have as standard. Locally:
 *   php -d extension=gd database/make_webp.php
 */

require __DIR__ . '/../app/bootstrap.php';

if (!Webp::available()) {
    fwrite(STDERR, "GD with WebP support is not loaded. Try: php -d extension=gd database/make_webp.php\n");
    exit(1);
}

$public = dirname(__DIR__) . '/public';
$files = array_merge(
    glob("$public/images/products/*.{png,jpg,jpeg,PNG,JPG,JPEG}", GLOB_BRACE) ?: [],
    glob("$public/images/designs/previews/*/*.{png,jpg,jpeg,PNG,JPG,JPEG}", GLOB_BRACE) ?: []
);

$made = $current = $skipped = 0;
$before = $after = 0;
foreach ($files as $file) {
    $webp = preg_replace('/\.(png|jpe?g)$/i', '.webp', $file);
    if (is_file($webp) && filemtime($webp) >= filemtime($file)) {
        $current++;
        continue;
    }
    $isProduct = str_contains(str_replace('\\', '/', $file), '/images/products/');
    if ($isProduct ? Webp::sibling($file, ProductPhoto::WEBP_QUALITY) : Webp::sibling($file)) {
        $made++;
        $before += filesize($file);
        $after  += filesize($webp);
    } else {
        $skipped++;   // WebP wouldn't be smaller, or the image couldn't be read
    }
}

$mb = fn(int $bytes) => number_format($bytes / 1048576, 1) . ' MB';
printf("%d WebP copies made (%s of PNG/JPEG -> %s), %d already up to date, %d left as they are.\n",
    $made, $mb($before), $mb($after), $current, $skipped);
