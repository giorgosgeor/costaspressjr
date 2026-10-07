<?php

/**
 * Prices cart and order lines from the database: finds the supplier cost of
 * the chosen variant and marks it up with Pricing's quantity-tiered margins.
 * (Pricing itself is pure arithmetic; this is the part that needs the DB.)
 */
class CartPricing {
    public function __construct(private PDO $db) {
    }

    /**
     * Authoritative per-unit RETAIL price for a cart/order line: the supplier
     * cost marked up by the quantity-tiered margin (Pricing), plus any premade
     * design price. Print-placement extras (front+back / sleeves) are carried
     * separately in custom_design_fee, so they are NOT included here. Returns
     * null when the supplier cost cannot be resolved.
     */
    public function unitPrice(?int $productId, ?int $variantId, ?int $sizeId, ?int $colorId, int $quantity, float $premadePrice = 0.0, float $extraPrintCost = 0.0): ?float {
        $cost = $this->resolveSupplierCost($variantId, $productId, $sizeId, $colorId);
        if ($cost === null) return null;
        $category = $this->priceCategoryFor($productId);
        // Print add-ons are flat euro amounts added on top of the marked-up
        // garment price — the margin does not apply to them.
        $retail = Pricing::unitPrice($cost, $category, max(1, $quantity), $extraPrintCost);
        return round($retail + $premadePrice, 2);
    }

    /**
     * Flat euro cost of a custom design's print add-ons, derived from its
     * view-keyed elements: front+back print and each printed sleeve.
     */
    public static function printExtraCostFor($elements): float {
        if (!is_array($elements)) return 0.0;
        $count = function ($view) use ($elements) {
            return isset($elements[$view]) && is_array($elements[$view]) ? count($elements[$view]) : 0;
        };
        $frontAndBack = $count('front') > 0 && $count('back') > 0;
        $sleeves = ($count('left-sleeve') > 0 ? 1 : 0) + ($count('right-sleeve') > 0 ? 1 : 0);
        return Pricing::printExtraCost($frontAndBack, $sleeves);
    }

    /**
     * Resolve the SUPPLIER (blank garment) cost stored in the DB for a
     * product/variant selection. Prefers the variant-level unit_price, then
     * products.base_price + size modifier, then base_price alone. Returns
     * null when nothing can be resolved.
     */
    private function resolveSupplierCost(?int $variantId, ?int $productId, ?int $sizeId, ?int $colorId): ?float {
        if (!empty($variantId)) {
            $stmt = $this->db->prepare("
                SELECT COALESCE(v.unit_price, p.base_price + COALESCE(s.price_modifier, 0)) AS unit_price
                  FROM product_variants v
                  JOIN products       p ON p.id = v.product_id
                  LEFT JOIN product_sizes s ON s.id = v.size_id
                 WHERE v.id = ?
                 LIMIT 1
            ");
            $stmt->execute([$variantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return (float)$row['unit_price'];
        }
        if (!empty($productId) && !empty($sizeId) && !empty($colorId)) {
            $stmt = $this->db->prepare("
                SELECT COALESCE(v.unit_price, p.base_price + COALESCE(s.price_modifier, 0)) AS unit_price
                  FROM products p
                  LEFT JOIN product_variants v
                    ON v.product_id = p.id AND v.size_id = ? AND v.color_id = ?
                  LEFT JOIN product_sizes s ON s.id = ?
                 WHERE p.id = ?
                 LIMIT 1
            ");
            $stmt->execute([$sizeId, $colorId, $sizeId, $productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return (float)$row['unit_price'];
        }
        if (!empty($productId)) {
            $stmt = $this->db->prepare("SELECT base_price FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) return (float)($product['base_price'] ?? 0);
        }
        return null;
    }

    /** Which margin table (tshirt|hoodie) a product uses. */
    private function priceCategoryFor(?int $productId): string {
        if (empty($productId)) return 'tshirt';
        $stmt = $this->db->prepare("SELECT slug, name FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return Pricing::categoryFor($p['slug'] ?? '', $p['name'] ?? '');
    }
}
