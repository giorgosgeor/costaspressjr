<?php

/**
 * A shopper's cart (carts + cart_items + cart_item_uploads). Every user —
 * account or session guest — has at most one cart.
 */
class Cart {
    public function __construct(private PDO $db) {
    }

    /** The user's cart id, creating the cart on first use. */
    public function getOrCreateCartId(int $userId): int {
        $stmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if ($cart) return (int)$cart['id'];
        $stmt = $this->db->prepare("INSERT INTO carts (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        return (int)$this->db->lastInsertId();
    }

    public function addUpload(int $cartItemId, array $upload): bool {
        $stmt = $this->db->prepare("INSERT INTO cart_item_uploads (cart_item_id, original_filename, stored_file_path, placement, position_x, position_y, width, height) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $cartItemId,
            $upload['original_filename'],
            $upload['stored_file_path'],
            $upload['placement'] ?? 'front',
            $upload['position_x'] ?? 0,
            $upload['position_y'] ?? 0,
            $upload['width'] ?? 80,
            $upload['height'] ?? 80
        ]);
    }

    /**
     * The cart's lines as the cart and checkout pages show them — prices,
     * preview image, premade design overlay — and their total. Also keeps the
     * header's cart-count badge (in the session) in step with the cart.
     *
     * @return array{0: array, 1: float}
     */
    public function contents(int $userId): array {
        $cartId = $this->getOrCreateCartId($userId);
        $stmt = $this->db->prepare("
            SELECT ci.*,
                   p.name AS product_name,
                   p.image_path AS product_image,
                   p.base_price,
                   ps.size_name,
                   ac.color_name,
                   ac.color_hex AS color_hex,
                   (COALESCE(NULLIF(ci.unit_price, 0), p.base_price) + COALESCE(ci.custom_design_fee, 0)) AS unit_total,
                   ((COALESCE(NULLIF(ci.unit_price, 0), p.base_price) + COALESCE(ci.custom_design_fee, 0)) * ci.quantity) AS line_total
            FROM cart_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_sizes ps ON ci.size_id = ps.id
            LEFT JOIN available_colors ac ON ci.color_id = ac.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $cartItems = $stmt->fetchAll();

        // Keep header cart-count badge in sync with the actual cart contents
        $_SESSION['cart_count'] = (int)array_sum(array_column($cartItems, 'quantity'));

        // Fetch uploads for each cart item; decode premade design info
        foreach ($cartItems as &$item) {
            $stmt = $this->db->prepare("SELECT * FROM cart_item_uploads WHERE cart_item_id = ?");
            $stmt->execute([$item['id']]);
            $item['uploads'] = $stmt->fetchAll();

            if (!empty($item['design_data'])) {
                $dd = json_decode($item['design_data'], true);
                if (isset($dd['type']) && $dd['type'] === 'premade') {
                    $item['premade_design_id']    = $dd['premade_design_id'] ?? null;
                    $item['premade_design_name']  = $dd['premade_design_name'] ?? null;
                    $item['premade_design_image'] = $dd['premade_design_image'] ?? null;
                    $item['premade_pos_x']        = (float)($dd['pos_x']    ?? 0);
                    $item['premade_pos_y']        = (float)($dd['pos_y']    ?? 0);
                    $item['premade_pos_size']     = (float)($dd['pos_size'] ?? 55);
                }
            }

            // Resolve front preview image (pre-rendered composite of product + design)
            $item['front_preview'] = null;
            if (!empty($item['preview_images'])) {
                $decoded = json_decode($item['preview_images'], true);
                if (!empty($decoded['front'])) {
                    $item['front_preview'] = $decoded['front'];
                }
            }
            // Fallback: use custom_designs.preview_images ONLY when the cart item's
            // color matches the design's saved color — otherwise it would show the wrong color.
            if (empty($item['front_preview']) && !empty($item['design_id']) && empty($item['premade_design_image'])) {
                $pStmt = $this->db->prepare("SELECT preview_images, color_id FROM custom_designs WHERE id = ?");
                $pStmt->execute([$item['design_id']]);
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($pRow && !empty($pRow['preview_images']) && $pRow['color_id'] == $item['color_id']) {
                    $decoded = json_decode($pRow['preview_images'], true);
                    if (!empty($decoded['front'])) {
                        $item['front_preview'] = $decoded['front'];
                    }
                }
            }
            // Legacy fallback: overlay custom design upload on product image
            if (empty($item['front_preview']) && !empty($item['design_id']) && empty($item['premade_design_image'])) {
                $stmt = $this->db->prepare("
                    SELECT stored_file_path, position_x, position_y, width, height
                    FROM custom_design_uploads
                    WHERE design_id = ? AND (view_placement = 'front' OR view_placement IS NULL)
                    ORDER BY layer_order ASC
                    LIMIT 1
                ");
                $stmt->execute([$item['design_id']]);
                $customUpload = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($customUpload) {
                    $item['custom_design_overlay'] = $customUpload;
                }
            }
        }
        unset($item);

        // Calculate cart total
        $cartTotal = 0;
        foreach ($cartItems as $item) {
            $cartTotal += (float)($item['line_total'] ?? 0);
        }

        return [$cartItems, $cartTotal];
    }
}
