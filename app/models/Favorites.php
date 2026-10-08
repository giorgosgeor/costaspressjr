<?php

/**
 * The products and premade designs a customer has saved (user_favorites).
 */
class Favorites {
    public function __construct(private PDO $db) {
    }

    /**
     * Saved products and premade designs, newest first. Inactive products are
     * still listed (with a flag) rather than dropped — silently losing a saved
     * item looks like a bug to the person who saved it.
     */
    public function forUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT f.id, f.created_at, 'product' AS kind,
                   p.id AS item_id, p.name, p.slug, p.image_path, p.base_price, p.active
            FROM user_favorites f
            JOIN products p ON p.id = f.product_id
            WHERE f.user_id = ?
            UNION ALL
            SELECT f.id, f.created_at, 'design' AS kind,
                   d.id AS item_id, d.name, NULL AS slug, d.image_path, d.price AS base_price, d.active
            FROM user_favorites f
            JOIN premade_designs d ON d.id = f.design_id
            WHERE f.user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Products store the SUPPLIER cost, so show the same qty-1 retail price
        // the shop does. A design's price is only its fee on top of a garment,
        // so show what it costs on its cheapest garment, as the design page's
        // "From" does (it used to show the bare fee: €12 for a €25.80 tee).
        foreach ($rows as &$r) {
            $r['display_price'] = $r['kind'] === 'product'
                ? Pricing::unitPrice((float)$r['base_price'], Pricing::categoryFor($r['slug'] ?? '', $r['name'] ?? ''), 1)
                : $this->cheapestGarment((int)$r['item_id']) + (float)$r['base_price'];
        }
        unset($r);
        return $rows;
    }

    /** Qty-1 retail price of the cheapest active garment a design is offered on (0 if none). */
    private function cheapestGarment(int $designId): float {
        $stmt = $this->db->prepare("
            SELECT p.base_price, p.slug, p.name
            FROM design_products dp
            JOIN products p ON p.id = dp.product_id AND p.active = 1
            WHERE dp.design_id = ?
        ");
        $stmt->execute([$designId]);
        $prices = array_map(
            static fn($p) => Pricing::unitPrice((float)$p['base_price'], Pricing::categoryFor($p['slug'] ?? '', $p['name'] ?? ''), 1),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
        return $prices ? min($prices) : 0.0;
    }

    /**
     * Ids the current user has favourited ('product' or 'design'), for
     * rendering hearts in their filled state. Guests have none — the heart
     * still shows, and clicking it sends them to log in.
     */
    public function idsForCurrentUser(string $kind): array {
        if (!Auth::check()) return [];
        $column = $kind === 'product' ? 'product_id' : 'design_id';
        $stmt = $this->db->prepare("SELECT {$column} FROM user_favorites WHERE user_id = ? AND {$column} IS NOT NULL");
        $stmt->execute([(int)Auth::userId()]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
