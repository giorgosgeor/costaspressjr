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

    /** The raw (uncapped) per-unit price for a fully-loaded unit cost. */
    private static function rawUnit(float $unitCost, string $category, int $quantity): float
    {
        $margin = self::marginFor($category, $quantity);
        return $unitCost / (1 - $margin);
    }

    /**
     * The order total for a fully-loaded unit cost, guaranteed monotonic: a
     * smaller order is never charged more than a larger one. When crossing into
     * a cheaper tier would make a bigger order cost less overall, the smaller
     * order is capped at that better total ("you may as well order the higher
     * quantity"). Print add-ons must already be baked into $unitCost so they are
     * capped together with the garment and cannot reintroduce the anomaly.
     */
    private static function cappedTotal(float $unitCost, string $category, int $quantity): float
    {
        $qty = max(1, $quantity);
        $total = self::rawUnit($unitCost, $category, $qty) * $qty;

        // Totals rise within a tier, so the only cheaper totals sit at the
        // start quantity of a higher-volume (lower-margin) tier.
        $tiers = self::TIERS[$category] ?? self::TIERS['tshirt'];
        foreach ($tiers as [$min, , ]) {
            if ($min > $qty) {
                $candidate = self::rawUnit($unitCost, $category, $min) * $min;
                if ($candidate < $total) {
                    $total = $candidate;
                }
            }
        }
        return $total;
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

        // The margin applies to the GARMENT only. Print add-ons are flat euro
        // amounts added to the base price (sleeves €1 each, front+back €3) and
        // are NOT marked up — dividing them by (1 - margin) turned a €3 add-on
        // into €10 at the top tier. Matches public/js/pricing.js, which is what
        // the customer is quoted in the customiser.
        $unitCost    = $supplierCost + self::ERROR + self::COST_OF_PRINT;
        $garmentUnit = self::cappedTotal($unitCost, $category, $qty) / $qty;

        // Adding a flat per-unit amount preserves monotonicity: the capped
        // garment total is already non-decreasing in quantity, and extras * qty
        // is strictly increasing, so a smaller order still never costs more.
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
