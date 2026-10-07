<?php
/**
 * Supplier pricing matrix + size/colour lineup per SKU.
 * Edit values here, then run:
 *   php database/apply_supplier_prices.php --dry-run    (preview)
 *   php database/apply_supplier_prices.php --apply      (commit)
 *
 * Sizes and colours follow the supplier's (B&C) colours-and-sizes sheet of
 * 2026-10-07; each entry names the B&C model it is. Prices are from the
 * supplier price list (WEBSITE_KEY_INFO/WEBSITE_CLOTHING_N_PRICES.txt).
 *
 * Pricing keys:
 *   norm  = baseline price (when there is no WHITE/COLOR distinction)
 *   white = price for the white colour
 *   color = price for any non-white colour
 *   big   = price for BIG sizes on the white side, or NORM-side
 *   cbig  = price for BIG sizes for non-white colours
 *
 * "BIG" = 3XL, 4XL, 5XL  (and "XXXL" spellings) — 2XL is a normal size.
 *
 * sizes       = sizes this product is offered in.
 * colors      = colour names from `available_colors` it is offered in.
 * size_colors = optional: sizes offered in only SOME of those colours, e.g.
 *               ['4XL' => ['White', 'Black']]. Every other size comes in
 *               every colour.
 * active      = optional: false hides the product from the shop.
 * size_chart  = image under /images/size-charts/ to use as the size guide.
 *
 * The apply script makes the database match: anything a product has that
 * isn't listed here is marked unavailable (never deleted — past orders and
 * saved designs still point at it).
 *
 * Per (size, color) the variant unit_price is picked as:
 *   sizeBig & colorWhite → big
 *   sizeBig & !colorWhite → cbig ?? big
 *   !sizeBig & colorWhite → white ?? norm
 *   !sizeBig & !colorWhite → color ?? norm
 *
 * products.base_price = white ?? norm.
 */

// The generic palette the shop started with. Only products that aren't in
// the supplier's colours-and-sizes sheet still use it (all of them hidden).
$DEFAULT_COLORS = [
    'White', 'Black', 'Navy Blue', 'Royal Blue', 'Forest Green',
    'Gray', 'Charcoal', 'Maroon', 'Orange', 'Yellow',
    'Pink', 'Purple', 'Brown', 'Olive', 'Red',
];
$BASIC_COLORS = [
    'White', 'Black', 'Navy Blue', 'Gray', 'Charcoal', 'Maroon', 'Red',
];
$CAP_COLORS = [
    'Black', 'White', 'Navy Blue', 'Gray', 'Red',
];

return [
    // ---- Tank tops ----
    // B&C Athletic | Men | Single Jersey
    '#6'    => ['name' => 'Tank Top',                      'slug' => 'tank-top',                      'white' => 3.24, 'color' => 4.32,
                'sizes'  => ['M','L','XL','2XL'],
                'colors' => ['White', 'Black', 'Navy Blue', 'Royal Blue', 'Red', 'Sport Grey'],
                'size_chart' => '/images/size-charts/tanktop.png'],

    // B&C Patti Classic | Women | Single Jersey
    '#2'    => ['name' => 'Female Tank Top',               'slug' => 'female-tank-top',               'norm'  => 4.32,
                'sizes'  => ['S','M','L','XL'],
                'colors' => ['Black', 'Fuchsia'],
                'size_chart' => '/images/size-charts/tanktop-women.png'],

    // ---- T-shirts ----
    // B&C #E150 | Single Jersey
    '#13'   => ['name' => 'T-Shirt',                       'slug' => 't-shirt',                       'existing_id' => 1, 'white' => 2.14, 'color' => 2.74, 'big' => 2.81, 'cbig' => 2.93,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => ['White', 'Black', 'Sport Grey', 'Royal Blue', 'Navy Blue', 'Red', 'Ash', 'Gold',
                             'Bottle Green', 'Kelly Green', 'Sky Blue', 'Atoll', 'Orange', 'Navy',
                             'Pistachio', 'Urban Purple', 'Denim', 'Fuchsia', 'Khaki', 'Burgundy', 'Sand',
                             'Real Turquoise', 'Turquoise', 'Dark Grey', 'Natural', 'Millennial Khaki',
                             'Millennial Pink', 'Orchid Green'],
                'size_colors' => [
                    '4XL' => ['White', 'Black', 'Sport Grey', 'Royal Blue', 'Navy Blue', 'Red', 'Ash', 'Gold', 'Orange'],
                    '5XL' => ['White', 'Black', 'Sport Grey', 'Royal Blue', 'Navy Blue', 'Red', 'Ash', 'Gold', 'Orange'],
                ],
                'size_chart' => '/images/size-charts/tshirt.png'],

    // B&C #E150 Women | Single Jersey
    '#10'   => ['name' => 'Female T-Shirt',                'slug' => 'female-t-shirt',                'norm'  => 2.88, 'big'   => 3.52,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => ['White', 'Black', 'Sport Grey', 'Royal Blue', 'Navy', 'Sky Blue', 'Atoll',
                             'Orange', 'Red', 'Gold', 'Pistachio', 'Natural', 'Fuchsia', 'Khaki', 'Burgundy',
                             'Real Turquoise', 'Urban Purple', 'Turquoise'],
                'size_colors' => [
                    '3XL' => ['White', 'Black'],
                ],
                'size_chart' => '/images/size-charts/tshirt-women.png'],

    // B&C Inspire V/T | Men | Single Jersey
    '#69'   => ['name' => 'V-Neck T-Shirt',                'slug' => 'v-neck-t-shirt',                'norm'  => 5.05,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => ['White', 'Black'],
                'size_chart' => '/images/size-charts/vneck.png'],

    // B&C Inspire V/T | Women | Single Jersey
    '#69a'  => ['name' => 'Female V-Neck T-Shirt',         'slug' => 'female-v-neck-t-shirt',         'norm'  => 5.05,
                'sizes'  => ['XS','S','M','L','XL','2XL'],
                'colors' => ['White', 'Black'],
                'size_chart' => '/images/size-charts/vneck-women.png'],

    // ---- Polo shirts ----
    // B&C My Polo 180
    '#180'  => ['name' => 'Polo T-Shirt',                  'slug' => 'polo-t-shirt',                  'norm'  => 7.50,
                'sizes'  => ['S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => ['White', 'Mastic', 'Roasted Coffee', 'Solar Yellow', 'Pure Orange',
                             'Blush Pink', 'Lotus Pink', 'Meta Fuchsia', 'Radiant Purple', 'Blush Mint',
                             'Meta Turquoise', 'Apple Green', 'Camo Green', 'Burgundy', 'Dark Forest', 'Black'],
                'size_colors' => [
                    '4XL' => ['White', 'Black'],
                    '5XL' => ['White', 'Black'],
                ],
                'size_chart' => '/images/size-charts/polo.png'],

    // B&C My Polo Woman 180 (the sheet shows these three colours)
    '#180a' => ['name' => 'Female Polo T-Shirt',           'slug' => 'female-polo-t-shirt',           'norm'  => 7.50,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => ['White', 'Black', 'Navy Blue'],
                'size_chart' => '/images/size-charts/polo-women.png'],

    // ---- Long sleeves ----
    // B&C Exact LSL 150 g/m² | Single Jersey
    '#35a'  => ['name' => 'Long Sleeve T-Shirt',           'slug' => 'longsleeve-t-shirt',            'existing_id' => 4, 'white' => 3.61, 'color' => 4.89, 'big' => 4.75, 'cbig' => 6.12,
                'sizes'  => ['S','M','L','XL','2XL','3XL','4XL'],
                'colors' => ['Orange', 'White', 'Black', 'Sport Grey', 'Bottle Green', 'Royal Blue', 'Navy Blue'],
                'size_colors' => [
                    '4XL' => ['White', 'Black', 'Sport Grey', 'Royal Blue', 'Navy Blue'],
                ],
                'size_chart' => '/images/size-charts/longsleeve.png'],

    // B&C LSL 190 g/m² | Single Jersey (the sheet shows Khaki only). Was
    // "Long Sleeve T-Shirt (Extended Colors)"; the slug stays for its URLs.
    '#35b'  => ['name' => 'Long Sleeve T-Shirt (Heavyweight)', 'slug' => 'longsleeve-t-shirt-ext',     'norm' => 6.91, 'big' => 8.64,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => ['Khaki'],
                'size_chart' => '/images/size-charts/longsleeve.png'],

    // Not in the supplier's colours-and-sizes sheet; hidden.
    '#18'   => ['name' => 'Female Long Sleeve T-Shirt',    'slug' => 'female-long-sleeve-t-shirt',    'white' => 4.82, 'color' => 5.26, 'big' => 5.97, 'cbig' => 5.97,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/longsleeve-women.png'],

    // ---- Sweatshirts / crew necks ("Hoodie (No Hood)") ----
    // B&C Set In 280 g/m² | Brushed Fleece Inside. The sheet says 3XL comes
    // in "the starred colours" without saying which, so 3XL isn't offered
    // until that is known.
    '#32'   => ['name' => 'Hoodie (No Hood)',              'slug' => 'hoodie-no-hood',                'norm'  => 13.83,
                'sizes'  => ['S','M','L','XL','2XL'],
                'colors' => ['White', 'Black', 'Navy Blue', 'Royal Blue', 'Heather Grey', 'Pumpkin Orange'],
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    // B&C King Crew Neck
    '#32a'  => ['name' => 'Hoodie (No Hood, Extended Colors)', 'slug' => 'hoodie-no-hood-extended-colors', 'norm' => 13.83, 'big' => 15.65,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL','4XL'],
                'colors' => ['White', 'Black', 'Yellow Fizz', 'Pure Orange', 'Bottle Green', 'Sky Blue', 'Khaki'],
                'size_colors' => [
                    '4XL' => ['White', 'Black'],
                ],
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    // B&C Organic Crew Neck
    '#32b'  => ['name' => 'Hoodie (No Hood, Variant B)',   'slug' => 'hoodie-no-hood-variant-b',      'norm'  => 12.97,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => ['White', 'Burgundy'],
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    // B&C ID.002 | Brushed Fleece Inside — the "primary hoodie"
    '#002'  => ['name' => 'Primary Hoodie (No Hood)',      'slug' => 'primary-hoodie-no-hood',        'norm'  => 10.81, 'big' => 12.25,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => ['White', 'Black', 'Khaki', 'Navy Blue', 'Red', 'Heather Grey',
                             'Kelly Green', 'Royal Blue', 'Orange', 'Light Blue'],
                'size_colors' => [
                    '5XL' => ['Black', 'Heather Grey'],
                ],
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    // B&C Spider Men | PST — the "primary hoodie with zip"
    '#34'   => ['name' => 'Primary Hoodie (No Hood, Zip)', 'slug' => 'primary-hoodie-no-hood-zip',    'norm'  => 25.20,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => ['Black', 'Navy Blue'],
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    // ---- Hoodies (with hood) ----
    // B&C ID.003 | Brushed Fleece Inside
    '#003'  => ['name' => 'Hoodie (With Hood)',            'slug' => 'hoodie',                        'existing_id' => 3, 'norm' => 14.40, 'big' => 16.57,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => ['White', 'Black', 'Heather Grey', 'Navy Blue', 'Royal Blue', 'Red'],
                'size_colors' => [
                    '5XL' => ['Black', 'Heather Grey', 'Navy Blue'],
                ],
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    // Not in the supplier's colours-and-sizes sheet; hidden.
    '#29'   => ['name' => 'Hoodie (With Hood, Premium)',   'slug' => 'hoodie-premium',                'norm'  => 18.43, 'active' => false,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#29a'  => ['name' => 'Hoodie (With Hood, Premium Extended)', 'slug' => 'hoodie-with-hood-premium-extended', 'norm' => 18.43, 'big' => 20.83,
                'sizes'  => ['S','M','L','XL','2XL','3XL','4XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#29b'  => ['name' => 'Hoodie (With Hood, Premium B)', 'slug' => 'hoodie-with-hood-premium-b',    'norm'  => 16.90,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    // ---- Zipped hoods ----
    // B&C King | Zipped Hood Men
    '#30'   => ['name' => 'Hoodie (With Hood, Zip)',       'slug' => 'hoodie-with-hood-zip',          'norm'  => 23.04, 'big' => 27.36,
                'sizes'  => ['XS','S','M','L','XL','2XL','3XL','4XL'],
                'colors' => ['White', 'Black', 'Heather Grey', 'Navy Blue', 'Navy', 'Red'],
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    // B&C Queen | Zipped Hood Women
    '#31'   => ['name' => 'Female Hoodie (With Hood, Zip)','slug' => 'female-hoodie-with-hood-zip',   'norm'  => 23.04, 'big' => 27.36,
                'sizes'  => ['S','M','L','XL','2XL','3XL'],
                'colors' => ['Black', 'Heather Grey', 'Red', 'Navy Blue', 'Navy'],
                'size_chart' => '/images/size-charts/hoodie-zip-women.png'],

    // ---- Caps (single one-size variant; not in the clothing sheet) ----
    '#99'   => ['name' => 'Cap (Foam Front / Mesh Back)',  'slug' => 'cap-foam-front-mesh-back',      'norm'  => 3.61,
                'sizes'  => ['One Size'],
                'colors' => $CAP_COLORS],

    '#100'  => ['name' => 'Cap (Cotton)',                  'slug' => 'cap-cotton',                    'norm'  => 3.61,
                'sizes'  => ['One Size'],
                'colors' => $CAP_COLORS],

    '#85'   => ['name' => 'Cap (Cotton, Alt Style)',       'slug' => 'cap-cotton-alt',                'norm'  => 3.21,
                'sizes'  => ['One Size'],
                'colors' => $CAP_COLORS],

    // ---- Existing Jacket (keeps its db id, gets a chart; hidden) ----
    'JACKET' => ['name' => 'Jacket',                       'slug' => 'jacket',                        'existing_id' => 2, 'norm' => 45.00,
                 'sizes'  => ['S','M','L','XL','2XL','3XL'],
                 'colors' => ['Black','Navy Blue','Gray','Charcoal'],
                 'size_chart' => '/images/size-charts/jacket.png'],
];
