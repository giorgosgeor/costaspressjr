<?php
declare(strict_types=1);

/**
 * Central pricing engine — the single source of truth for what a customer pays.
 *
 * The database stores the SUPPLIER (blank garment) cost in
 * products.base_price / product_variants.unit_price. This class turns that
 * cost into the customer-facing retail price by applying the quantity-tiered
 * profit margin, plus the fixed print add-ons.
 *
 * Formula (per unit):
 *     cost   = supplier_cost + ERROR + COST_OF_PRINT
 *     retail = cost / (1 - margin[category][quantityTier])
 *     retail += 3   if the design is printed front AND back
 *     retail += 1   per printed sleeve
 *
 * The margin (and therefore the price) depends on how many units are ordered:
 * larger orders take a smaller margin, so the per-unit price drops.
 */
final class Pricing
{
    /** Fixed handling/error buffer added to every garment's cost. */
    public const ERROR = 1.0;

    /** Cost of the single (base) print already included in the base price. */
    public const COST_OF_PRINT = 1.0;

    /** Flat add-on when a design is printed on both front and back. */
    public const FRONT_AND_BACK_EXTRA = 3.0;

    /** Flat add-on per printed sleeve. */
    public const SLEEVE_EXTRA = 1.0;

    /**
     * Profit-margin tiers per category: [minQty, maxQty, margin].
     * margin is a fraction (0.70 == 70%). Top tier is open-ended.
     */
    private const TIERS = [
        'tshirt' => [
            [0,   4,             0.70],
            [5,   14,            0.65],
            [15,  29,            0.60],
            [30,  49,            0.54],
            [50,  99,            0.50],
            [100, PHP_INT_MAX,   0.44],
        ],
        'hoodie' => [
            [0,   4,             0.45],
            [5,   14,            0.42],
            [15,  29,            0.38],
            [30,  49,            0.35],
            [50,  99,            0.30],
            [100, PHP_INT_MAX,   0.25],
        ],
    ];

    /**
     * Explicit slug/keyword → category overrides. Anything not matched here
     * or by the hoodie keyword falls through to the 'tshirt' tier table
     * (that covers tees, tanks, v-necks, polos, long-sleeves, and — until
     * you decide otherwise — jackets and mugs). Edit this map to re-assign
     * a product to a different margin table.
     */
    private const CATEGORY_OVERRIDES = [
        // 'polo-t-shirt' => 'hoodie',   // example override by slug
    ];

    /**
     * Resolve which margin table a product uses from its slug/name.
     */
    public static function categoryFor(string $slug, string $name = ''): string
    {
        $slugKey = strtolower(trim($slug));
        if (isset(self::CATEGORY_OVERRIDES[$slugKey])) {
            return self::CATEGORY_OVERRIDES[$slugKey];
        }

        $haystack = strtolower($slug . ' ' . $name);
        if (strpos($haystack, 'hoodie') !== false) {
            return 'hoodie';
        }
        // Tops (tee/tank/v-neck/polo/long-sleeve) and everything else default
        // to the T-shirt margin table.
        return 'tshirt';
    }

    /**
     * The profit margin (fraction) for a category at a given order quantity.
     */
    public static function marginFor(string $category, int $quantity): float
    {
        $tiers = self::TIERS[$category] ?? self::TIERS['tshirt'];
        $qty = max(1, $quantity);
        foreach ($tiers as [$min, $max, $margin]) {
            if ($qty >= $min && $qty <= $max) {
                return $margin;
            }
        }
        // Fallback to the largest-volume tier.
        return $tiers[count($tiers) - 1][2];
    }

    /**
     * Per-unit garment price for a fully-loaded unit cost — the spec formula,
     * verbatim:
     *
     *     PRICE = COST / (1 - PROFIT_MARGIN)
     *
     * The margin is flat across a whole quantity band, so the per-unit price is
     * flat across that band too.
     *
     * Note the consequence, which is inherent to the supplied tier table rather
     * than to this code: because a band change is a step, a quantity at the top
     * of a band can cost more in TOTAL than the first quantity of the next band
     * (29 x 10.35 = 300.15 against 30 x 9.00 = 270.00). An earlier version
     * capped the total to remove that, but the cap is not in the spec and it
     * made the per-unit price slide within a band, so it has been removed. The
     * customiser surfaces a "order N+ and pay X each" prompt instead, which
     * informs without altering what is charged.
     */
    private static function rawUnit(float $unitCost, string $category, int $quantity): float
    {
        $margin = self::marginFor($category, $quantity);
        return $unitCost / (1 - $margin);
    }

    /** Flat euro cost of the print add-ons (front+back, sleeves). Not marked up. */
    public static function printExtraCost(bool $frontAndBack, int $sleeves): float
    {
        $extra = 0.0;
        if ($frontAndBack) {
            $extra += self::FRONT_AND_BACK_EXTRA;
        }
        $extra += max(0, $sleeves) * self::SLEEVE_EXTRA;
        return $extra;
    }

    /**
     * Compute the per-unit retail price a customer pays.
     *
     * Print add-ons are passed as $extraPrintCost and added flat to the marked-up
     * garment price — they are not subject to the margin.
     *
     * @param float  $supplierCost   blank garment cost stored in the DB
     * @param string $category       'tshirt' | 'hoodie' (see categoryFor)
     * @param int    $quantity       units ordered of this line (drives the tier)
     * @param float  $extraPrintCost flat euro cost of print add-ons
     */
    public static function unitPrice(
        float $supplierCost,
        string $category,
        int $quantity,
        float $extraPrintCost = 0.0
    ): float {
        $qty = max(1, $quantity);

        // Spec, line by line:
        //   COST_PER_TSHIRT  = SUPPLIER + ERROR(1) + COST_OF_PRINT(1)
        //   PRICE_PER_TSHIRT = COST / (1 - PROFIT_MARGIN)
        // The margin applies to the GARMENT only. Print add-ons are flat euro
        // amounts on top of the base price ("SLEEVES ARE 1 EURO EXTRA EACH",
        // "FRONT AND BACK PRINT IS 3 EURO EXTRA TO BASE PRICE"), so they are
        // added after the division, never marked up through it.
        $unitCost    = $supplierCost + self::ERROR + self::COST_OF_PRINT;
        $garmentUnit = self::rawUnit($unitCost, $category, $qty);

        return round($garmentUnit + max(0.0, $extraPrintCost), 2);
    }

    /**
     * Full breakdown for a line, handy for display/debugging.
     *
     * @return array{category:string,quantity:int,margin:float,supplier_cost:float,unit_cost:float,extra_print_cost:float,unit_price:float,line_total:float}
     */
    public static function breakdown(
        float $supplierCost,
        string $category,
        int $quantity,
        float $extraPrintCost = 0.0
    ): array {
        $margin = self::marginFor($category, $quantity);
        $unit   = self::unitPrice($supplierCost, $category, $quantity, $extraPrintCost);

        return [
            'category'         => $category,
            'quantity'         => max(1, $quantity),
            'margin'           => $margin,
            'supplier_cost'    => round($supplierCost, 2),
            'unit_cost'        => round($supplierCost + self::ERROR + self::COST_OF_PRINT, 2),
            'extra_print_cost' => round(max(0.0, $extraPrintCost), 2),
            'unit_price'       => $unit,
            'line_total'       => round($unit * max(1, $quantity), 2),
        ];
    }
}
