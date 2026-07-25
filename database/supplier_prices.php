<?php
/**
 * Supplier pricing matrix + size lineup per SKU.
 * Edit values here, then run:
 *   php database/apply_supplier_prices.php --dry-run    (preview)
 *   php database/apply_supplier_prices.php --apply      (commit)
 *
 * Pricing keys:
 *   norm  = baseline price (when there is no WHITE/COLOR distinction)
 *   white = price for the white colour
 *   color = price for any non-white colour
 *   big   = price for BIG sizes (2XL and up) on the white side, or NORM-side
 *   cbig  = price for BIG sizes (2XL and up) for non-white colours
 *
 * "BIG" = 2XL, 3XL, 4XL, 5XL  (and "XXL"/"XXXL" spellings).
 *
 * sizes  = sizes this product is offered in.
 * colors = optional whitelist of colour names from `available_colors`.
 *          Omit to assign every available colour.
 * size_chart = image under /images/size-charts/ to use as the size guide.
 *
 * Per (size, color) the variant unit_price is picked as:
 *   sizeBig & colorWhite → big
 *   sizeBig & !colorWhite → cbig ?? big
 *   !sizeBig & colorWhite → white ?? norm
 *   !sizeBig & !colorWhite → color ?? norm
 *
 * products.base_price = white ?? norm.
 */

// Default colour set used when a product doesn't list specific colours.
$DEFAULT_COLORS = [
    'White', 'Black', 'Navy Blue', 'Royal Blue', 'Forest Green',
    'Gray', 'Charcoal', 'Maroon', 'Orange', 'Yellow',
    'Pink', 'Purple', 'Brown', 'Olive', 'Red',
];

// "Smaller palette" — used for the cheaper / no-extended-colour variants in
// the supplier sheet (#32, #29, #002 — "less colours than 32" etc.).
$BASIC_COLORS = [
    'White', 'Black', 'Navy Blue', 'Gray', 'Charcoal', 'Maroon', 'Red',
];

// Caps: typically only a small palette.
$CAP_COLORS = [
    'Black', 'White', 'Navy Blue', 'Gray', 'Red',
];

return [
    // ---- T-shirts ----
    '#13'   => ['name' => 'T-Shirt',                       'slug' => 't-shirt',                       'existing_id' => 1, 'white' => 2.14, 'color' => 2.74, 'big' => 2.81, 'cbig' => 2.93,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/tshirt.png'],

    '#10'   => ['name' => 'Female T-Shirt',                'slug' => 'female-t-shirt',                'norm'  => 2.88, 'big'   => 3.52,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/tshirt-women.png'],

    '#6'    => ['name' => 'Tank Top',                      'slug' => 'tank-top',                      'white' => 3.24, 'color' => 4.32,
                'sizes' => ['M','L','XL','2XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/tanktop.png'],

    '#2'    => ['name' => 'Female Tank Top',               'slug' => 'female-tank-top',               'norm'  => 4.32,
                'sizes' => ['XS','S','M','L','XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/tanktop-women.png'],

    '#69'   => ['name' => 'V-Neck T-Shirt',                'slug' => 'v-neck-t-shirt',                'norm'  => 5.05,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/vneck.png'],

    '#69a'  => ['name' => 'Female V-Neck T-Shirt',         'slug' => 'female-v-neck-t-shirt',         'norm'  => 5.05,
                'sizes' => ['XS','S','M','L','XL','2XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/vneck-women.png'],

    '#180'  => ['name' => 'Polo T-Shirt',                  'slug' => 'polo-t-shirt',                  'norm'  => 7.50,
                'sizes' => ['S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/polo.png'],

    '#180a' => ['name' => 'Female Polo T-Shirt',           'slug' => 'female-polo-t-shirt',           'norm'  => 7.50,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/polo-women.png'],

    // ---- Long sleeves ----
    '#35a'  => ['name' => 'Long Sleeve T-Shirt',           'slug' => 'longsleeve-t-shirt',            'existing_id' => 4, 'white' => 3.61, 'color' => 4.89, 'big' => 4.75, 'cbig' => 6.12,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/longsleeve.png'],

    '#35b'  => ['name' => 'Long Sleeve T-Shirt (Extended Colors)', 'slug' => 'longsleeve-t-shirt-ext', 'norm' => 6.91, 'big' => 8.64,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/longsleeve.png'],

    '#18'   => ['name' => 'Female Long Sleeve T-Shirt',    'slug' => 'female-longsleeve-t-shirt',     'white' => 4.82, 'color' => 5.26, 'big' => 5.97, 'cbig' => 5.97,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/longsleeve-women.png'],

    // ---- Hoodies (no hood) ----
    '#32'   => ['name' => 'Hoodie (No Hood)',              'slug' => 'hoodie-no-hood',                'norm'  => 13.83,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    '#32a'  => ['name' => 'Hoodie (No Hood, Extended Colors)','slug' => 'hoodie-no-hood-ext',         'norm'  => 13.83, 'big' => 15.65,
                'sizes' => ['S','M','L','XL','2XL','3XL','4XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    '#32b'  => ['name' => 'Hoodie (No Hood, Variant B)',   'slug' => 'hoodie-no-hood-b',              'norm'  => 12.97,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    '#002'  => ['name' => 'Primary Hoodie (No Hood)',      'slug' => 'primary-hoodie-no-hood',        'norm'  => 10.81, 'big' => 12.25,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL','4XL','5XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-nohood.png'],

    '#34'   => ['name' => 'Primary Hoodie (No Hood, Zip)', 'slug' => 'primary-hoodie-no-hood-zip',    'norm'  => 25.20,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    // ---- Hoodies (with hood) ----
    '#003'  => ['name' => 'Hoodie (With Hood)',            'slug' => 'hoodie',                        'existing_id' => 3, 'norm' => 14.40, 'big' => 16.57,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#29'   => ['name' => 'Hoodie (With Hood, Premium)',   'slug' => 'hoodie-premium',                'norm'  => 18.43,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#29a'  => ['name' => 'Hoodie (With Hood, Premium Extended)','slug' => 'hoodie-premium-ext',      'norm'  => 18.43, 'big' => 20.83,
                'sizes' => ['S','M','L','XL','2XL','3XL','4XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#29b'  => ['name' => 'Hoodie (With Hood, Premium B)', 'slug' => 'hoodie-premium-b',              'norm'  => 16.90,
                'sizes' => ['S','M','L','XL','2XL','3XL'],
                'colors' => $BASIC_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#30'   => ['name' => 'Hoodie (With Hood, Zip)',       'slug' => 'hoodie-zip',                    'norm'  => 23.04, 'big' => 27.36,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL','4XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip.png'],

    '#31'   => ['name' => 'Female Hoodie (With Hood, Zip)','slug' => 'female-hoodie-zip',             'norm'  => 23.04, 'big' => 27.36,
                'sizes' => ['XS','S','M','L','XL','2XL','3XL'],
                'colors' => $DEFAULT_COLORS,
                'size_chart' => '/images/size-charts/hoodie-zip-women.png'],

    // ---- Caps (single one-size variant) ----
    '#99'   => ['name' => 'Cap (Foam Front / Mesh Back)',  'slug' => 'cap-foam-mesh',                 'norm'  => 3.61,
                'sizes' => ['One Size'],
                'colors' => $CAP_COLORS],

    '#100'  => ['name' => 'Cap (Cotton)',                  'slug' => 'cap-cotton',                    'norm'  => 3.61,
                'sizes' => ['One Size'],
                'colors' => $CAP_COLORS],

    '#85'   => ['name' => 'Cap (Cotton, Alt Style)',       'slug' => 'cap-cotton-alt',                'norm'  => 3.21,
                'sizes' => ['One Size'],
                'colors' => $CAP_COLORS],

    // ---- Existing Jacket (B&C SPIDER — keeps its db id, gets a chart) ----
    'JACKET' => ['name' => 'Jacket',                       'slug' => 'jacket',                        'existing_id' => 2, 'norm' => 45.00,
                 'sizes' => ['S','M','L','XL','2XL','3XL'],
                 'colors' => ['Black','Navy Blue','Gray','Charcoal'],
                 'size_chart' => '/images/size-charts/jacket.png'],
];
