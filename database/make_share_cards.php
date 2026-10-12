<?php
/**
 * Make the share cards (ShareCard): the picture a product or pre-made design
 * page shows when someone shares it, the photo on the shop's grey tile at
 * 1200×630. Run it after adding products or designs, or changing their
 * photos; a card that is already newer than its photo is kept.
 *
 *   php database/make_share_cards.php
 *
 * Needs PHP's GD extension, which hosts have as standard. Locally:
 *   php -d extension=gd database/make_share_cards.php
 */

require __DIR__ . '/../app/bootstrap.php';

if (!ShareCard::available()) {
    fwrite(STDERR, "GD is not loaded. Try: php -d extension=gd database/make_share_cards.php\n");
    exit(1);
}

$db = require __DIR__ . '/../app/config/database.php';

// The products and designs the shop shows, by their stored photo path:
// path => whether it is a garment photo (ShareCard::make()).
$images = array_fill_keys($db->query("SELECT image_path FROM premade_designs WHERE active = 1 AND image_path IS NOT NULL AND image_path <> ''")->fetchAll(PDO::FETCH_COLUMN), false)
        + array_fill_keys($db->query("SELECT image_path FROM products WHERE active = 1 AND image_path IS NOT NULL AND image_path <> ''")->fetchAll(PDO::FETCH_COLUMN), true);
// A card is remade when its photo, or the way cards are made, is newer.
$recipe = filemtime(__DIR__ . '/../app/services/ShareCard.php');

$made = $current = $missing = 0;
foreach ($images as $image => $garment) {
    $source = public_path(ltrim(web_path($image), '/'));
    $target = public_path(ShareCard::pathFor($image));
    if (!is_file($source)) {
        $missing++;
        continue;
    }
    if (is_file($target) && filemtime($target) >= max(filemtime($source), $recipe)) {
        $current++;
        continue;
    }
    if (ShareCard::make($source, $target, $garment)) {
        $made++;
    } else {
        $missing++;
        fwrite(STDERR, "Could not read $image\n");
    }
}

printf("%d share cards made, %d already up to date, %d photos missing or unreadable.\n", $made, $current, $missing);
