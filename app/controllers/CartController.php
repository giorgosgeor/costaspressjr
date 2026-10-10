<?php

/**
 * The cart: the cart page and the actions that add, change and remove lines.
 * Guests have carts too (see Controller::effectiveUserId()).
 */
class CartController extends Controller {
    public function show(): void {
        // Read path: never mint a guest row just for viewing — crawlers hit
        // /cart constantly. No session user of either kind = empty cart.
        $userId = $this->effectiveUserId(false);
        [$cartItems, $cartTotal] = $userId ? (new Cart($this->db))->contents($userId) : [[], 0];
        $this->render('cart/show', ['cartItems' => $cartItems, 'cartTotal' => $cartTotal]);
    }

    public function add(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo 'Invalid data';
            return;
        }
        // Counted before any guest row is created, so that is capped too.
        if (!WriteLimit::allow($this->db, 'cart')) {
            WriteLimit::refuse();
            return;
        }
        // Guests may buy without an account — a guest user row is created on
        // first add-to-cart. Saving DESIGNS still requires a real login.
        $userId = $this->effectiveUserId(true);
        if (!$userId) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Could not start a shopping session. Please try again.']);
            return;
        }
        // Validate required fields
        $required = ['product_id', 'size_id', 'color_id', 'quantity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo 'Missing required field: ' . $field;
                return;
            }
        }
        if (empty($data['design_id']) && empty($data['premade_design_id'])) {
            http_response_code(400);
            echo 'Missing required field: design_id or premade_design_id';
            return;
        }
        if (!is_numeric($data['quantity']) || $data['quantity'] < 1) {
            http_response_code(400);
            echo 'Invalid quantity';
            return;
        }
        // Fetch custom design if design_id provided. Owner-scoped: without the
        // user_id predicate anyone could attach another customer's design to
        // their own cart by guessing ids.
        $design = null;
        if (!empty($data['design_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM custom_designs WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$data['design_id'], $userId]);
            $design = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$design) {
                http_response_code(400);
                echo 'Invalid design_id';
                return;
            }
        }
        // Fetch premade design if premade_design_id provided
        $premadeDesign = null;
        if (!empty($data['premade_design_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM premade_designs WHERE id = ? AND active = 1 LIMIT 1");
            $stmt->execute([$data['premade_design_id']]);
            $premadeDesign = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$premadeDesign) {
                http_response_code(400);
                echo 'Invalid premade_design_id';
                return;
            }
            // Placement is per garment. Override the design-level coordinates
            // with this product's own, so the cart/order snapshot records where
            // the print actually goes on the item being bought.
            if (!empty($data['product_id'])) {
                $stmt = $this->db->prepare("
                    SELECT design_pos_x, design_pos_y, design_pos_size,
                           design_pos_back_x, design_pos_back_y, design_pos_back_size
                    FROM design_products WHERE design_id = ? AND product_id = ? LIMIT 1
                ");
                $stmt->execute([$premadeDesign['id'], $data['product_id']]);
                if ($link = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    foreach ($link as $col => $val) {
                        if ($val !== null) $premadeDesign[$col] = $val;
                    }
                }
            }
        }
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
        if ($cartModel->lineCount($cartId) >= Cart::MAX_LINES) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['error' => I18n::t('limit.cart_full', ['count' => Cart::MAX_LINES])]);
            return;
        }
        // Lookup variant_id
        $variantId = null;
        // Use the user's selection from the modal, fall back to saved design's values
        $sizeId = $data['size_id'] ?? ($design['size_id'] ?? null);
        $colorId = $data['color_id'] ?? ($design['color_id'] ?? null);
        if ($sizeId && $colorId && $data['product_id']) {
            $stmt = $this->db->prepare("SELECT id, is_available, stock_quantity FROM product_variants WHERE product_id = ? AND size_id = ? AND color_id = ? LIMIT 1");
            $stmt->execute([$data['product_id'], $sizeId, $colorId]);
            $variant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($variant) {
                $variantId = $variant['id'];

                // Availability / stock gate. A variant flagged unavailable can
                // never be added. Stock is only enforced when it's actually being
                // tracked (> 0); stock_quantity == 0 means "not tracked" here, so
                // we don't block sales for a store that hasn't entered stock levels.
                if ((int)$variant['is_available'] !== 1) {
                    http_response_code(409);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'This size/colour is currently unavailable.']);
                    return;
                }
                $stock = (int)$variant['stock_quantity'];
                if ($stock > 0 && (int)$data['quantity'] > $stock) {
                    http_response_code(409);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Only ' . $stock . ' left in stock for this option.']);
                    return;
                }
            }
        }
        // Prepare cart item data
        // Print-placement add-ons (front+back = €3, each sleeve = €1) are marked
        // up through the margin, so they are folded into unit_price below rather
        // than charged as a separate flat fee. custom_design_fee stays 0.
        $elements = $design ? json_decode($design['elements_json'], true) : ($data['elements'] ?? []);
        $printExtraCost = CartPricing::printExtraCostFor($elements);
        $customDesignFee = 0;
        $cartItem = [
            'product_id' => $data['product_id'],
            'variant_id' => $variantId,
            'size_id' => $sizeId,
            'color_id' => $colorId,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'] ?? 0,
            'custom' => !empty($data['custom']),
            'elements' => $elements,
            'custom_design_fee' => $customDesignFee,
            'design_id' => $data['design_id'] ?? null,
            'premade_design_id' => $premadeDesign['id'] ?? null,
            'premade_design_name' => $premadeDesign['name'] ?? null,
        ];

        // Compute unit_price authoritatively — never trust a client-sent value.
        // The DB stores the SUPPLIER cost; computeUnitPrice() applies the
        // quantity-tiered profit margin (Pricing), marks up the print add-ons
        // through that margin, and adds any premade design price.
        $premadePrice = $premadeDesign ? (float)($premadeDesign['price'] ?? 0) : 0.0;
        $resolvedPrice = (new CartPricing($this->db))->unitPrice(
            $cartItem['product_id'] ? (int)$cartItem['product_id'] : null,
            $cartItem['variant_id'] ? (int)$cartItem['variant_id'] : null,
            $cartItem['size_id'] ? (int)$cartItem['size_id'] : null,
            $cartItem['color_id'] ? (int)$cartItem['color_id'] : null,
            (int)$cartItem['quantity'],
            $premadePrice,
            $printExtraCost
        );
        if ($resolvedPrice !== null) {
            $cartItem['unit_price'] = $resolvedPrice;
        } else {
            // We could not resolve an authoritative supplier cost for this
            // selection, so we must not fall back to a client-supplied (or zero)
            // price — that would let a buyer set their own price. Reject instead.
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'This product/variant is not available for purchase right now.']);
            return;
        }

        // Build design_data JSON
        $designDataJson = $premadeDesign
            ? json_encode([
                'type' => 'premade',
                'premade_design_id' => $premadeDesign['id'],
                'premade_design_name' => $premadeDesign['name'],
                'premade_design_image' => $premadeDesign['image_path'] ?? null,
                'pos_x'    => $premadeDesign['design_pos_x']    ?? 0,
                'pos_y'    => $premadeDesign['design_pos_y']    ?? 0,
                'pos_size' => $premadeDesign['design_pos_size'] ?? 55,
                'design_positions' => $data['design_positions'] ?? null,
              ])
            : json_encode($cartItem['elements']);

        // Try to insert cart item - handle different schema versions
        try {
            // Try the full schema with all columns from the screenshot
            $stmt = $this->db->prepare("
                INSERT INTO cart_items
                (cart_id, product_id, variant_id, size_id, color_id, quantity, unit_price, custom_design_fee, is_custom_design, design_data, design_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ok = $stmt->execute([
                $cartId,
                $cartItem['product_id'],
                $cartItem['variant_id'],
                $cartItem['size_id'],
                $cartItem['color_id'],
                $cartItem['quantity'],
                $cartItem['unit_price'],
                $cartItem['custom_design_fee'],
                $cartItem['custom'] ? 1 : 0,
                $designDataJson,
                $cartItem['design_id']
            ]);
        } catch (PDOException $e) {
            // Try without design_data column but keep design_id
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO cart_items 
                    (cart_id, product_id, variant_id, size_id, color_id, quantity, unit_price, custom_design_fee, is_custom_design, design_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([
                    $cartId,
                    $cartItem['product_id'],
                    $cartItem['variant_id'],
                    $cartItem['size_id'],
                    $cartItem['color_id'],
                    $cartItem['quantity'],
                    $cartItem['unit_price'],
                    $cartItem['custom_design_fee'],
                    $cartItem['custom'] ? 1 : 0,
                    $cartItem['design_id']
                ]);
            } catch (PDOException $e2) {
                // Fallback to basic schema
                try {
                    $stmt = $this->db->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price) VALUES (?, ?, ?, ?, ?)");
                    $ok = $stmt->execute([
                        $cartId,
                        $cartItem['product_id'],
                        $cartItem['variant_id'],
                        $cartItem['quantity'],
                        $cartItem['unit_price']
                    ]);
                } catch (PDOException $e3) {
                    http_response_code(500);
                    error_log('Cart add error: ' . $e3->getMessage());
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Failed to add to cart: Database error']);
                    return;
                }
            }
        }
        
        if ($ok) {
            $cartItemId = (int)$this->db->lastInsertId();
            // Update session cart count
            $countStmt = $this->db->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart_items WHERE cart_id = ?");
            $countStmt->execute([$cartId]);
            $_SESSION['cart_count'] = (int)($countStmt->fetch()['total'] ?? 0);
            // A cart line's artwork lives in its design (design_id). Image data
            // sent here is ignored: no page sends any, and accepting it let a
            // script store ~12 MB per request that nothing ever deleted.
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'cart_item_id' => $cartItemId]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to add to cart.']);
        }
    }

    public function updateQuantity(): void {
        $userId = $this->effectiveUserId(false);
        if (!$userId) {
            header('Content-Type: application/json');
            http_response_code(409);
            echo json_encode(['error' => 'Your session has expired. Please refresh the page.']);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['cart_item_id']) || !isset($data['quantity'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing cart_item_id or quantity']);
            return;
        }
        
        $cartItemId = (int)$data['cart_item_id'];
        $quantity = (int)$data['quantity'];
        
        if ($quantity < 1) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Quantity must be at least 1']);
            return;
        }
        
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
        
        // Verify the cart item belongs to this user's cart and get price info
        $stmt = $this->db->prepare("
            SELECT ci.*, p.base_price
            FROM cart_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            WHERE ci.id = ? AND ci.cart_id = ?
        ");
        $stmt->execute([$cartItemId, $cartId]);
        $item = $stmt->fetch();
        
        if (!$item) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Cart item not found']);
            return;
        }
        
        // Re-tier the unit price for the new quantity — bulk pricing is
        // quantity-based, so changing the quantity can move the line into a
        // different margin tier and change the per-unit price. Print add-ons are
        // marked up through that margin, so they must be recomputed here too.
        $premadePrice = 0.0;
        $printExtraCost = 0.0;
        if (!empty($item['design_data'])) {
            $dd = json_decode((string)$item['design_data'], true);
            if (is_array($dd)) {
                if (($dd['type'] ?? '') === 'premade' && !empty($dd['premade_design_id'])) {
                    $pst = $this->db->prepare("SELECT price FROM premade_designs WHERE id = ? LIMIT 1");
                    $pst->execute([$dd['premade_design_id']]);
                    $premadePrice = (float)($pst->fetchColumn() ?: 0);
                } else {
                    // Custom design: design_data holds the view-keyed elements.
                    $printExtraCost = CartPricing::printExtraCostFor($dd);
                }
            }
        }
        $recomputed = (new CartPricing($this->db))->unitPrice(
            !empty($item['product_id']) ? (int)$item['product_id'] : null,
            !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
            !empty($item['size_id'])    ? (int)$item['size_id']    : null,
            !empty($item['color_id'])   ? (int)$item['color_id']   : null,
            $quantity,
            $premadePrice,
            $printExtraCost
        );

        if ($recomputed !== null) {
            $stmt = $this->db->prepare("UPDATE cart_items SET quantity = ?, unit_price = ? WHERE id = ?");
            $ok = $stmt->execute([$quantity, $recomputed, $cartItemId]);
            $item['unit_price'] = $recomputed;
        } else {
            $stmt = $this->db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $ok = $stmt->execute([$quantity, $cartItemId]);
        }

        if ($ok) {
            // Line/cart totals use the stored retail unit_price plus the flat
            // print-placement fee, matching the Stripe charge in
            // CheckoutController::createCheckoutSession().
            $unitTotal = (float)$item['unit_price'] + (float)($item['custom_design_fee'] ?? 0);
            $lineTotal = $unitTotal * $quantity;

            // Get updated cart total and count
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(SUM(ci.quantity), 0) as total_qty,
                    COALESCE(SUM((ci.unit_price + COALESCE(ci.custom_design_fee, 0)) * ci.quantity), 0) as cart_total
                FROM cart_items ci
                WHERE ci.cart_id = ?
            ");
            $stmt->execute([$cartId]);
            $result = $stmt->fetch();
            $_SESSION['cart_count'] = (int)($result['total_qty'] ?? 0);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'cart_total' => (float)($result['cart_total'] ?? 0),
                'cart_count' => (int)($result['total_qty'] ?? 0)
            ]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to update quantity']);
        }
    }

    public function remove(): void {
        $userId = $this->effectiveUserId(false);
        if (!$userId) {
            header('Content-Type: application/json');
            http_response_code(409);
            echo json_encode(['error' => 'Your session has expired. Please refresh the page.']);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['cart_item_id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing cart_item_id']);
            return;
        }
        
        $cartItemId = (int)$data['cart_item_id'];

        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
        
        // Verify the cart item belongs to this user's cart
        $stmt = $this->db->prepare("SELECT id, path_token FROM cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$cartItemId, $cartId]);
        $line = $stmt->fetch();
        if (!$line) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Cart item not found']);
            return;
        }

        // Delete related uploads first
        $stmt = $this->db->prepare("DELETE FROM cart_item_uploads WHERE cart_item_id = ?");
        $stmt->execute([$cartItemId]);

        // Delete the cart item
        $stmt = $this->db->prepare("DELETE FROM cart_items WHERE id = ?");
        $ok = $stmt->execute([$cartItemId]);

        if ($ok) {
            Cart::deletePreviews($cartItemId, $line['path_token'] ?? null);

            // Get updated cart count
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cartId]);
            $result = $stmt->fetch();
            $newCount = (int)($result['total'] ?? 0);
            $_SESSION['cart_count'] = $newCount;

            // Get updated cart total
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM((COALESCE(NULLIF(ci.unit_price,0), p.base_price) + COALESCE(ci.custom_design_fee,0)) * ci.quantity), 0) as total
                FROM cart_items ci
                LEFT JOIN products p ON ci.product_id = p.id
                WHERE ci.cart_id = ?
            ");
            $stmt->execute([$cartId]);
            $totalResult = $stmt->fetch();
            $newTotal = (float)($totalResult['total'] ?? 0);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'cart_count' => $newCount, 'cart_total' => $newTotal]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to remove item']);
        }
    }

    public function savePreviews(): void {
        // Guests own carts too; a missing id just means the session died.
        $userId = $this->effectiveUserId(false);
        if (!$userId) {
            header('Content-Type: application/json');
            http_response_code(409);
            echo json_encode(['error' => 'Your session has expired. Please refresh the page.']);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['cart_item_id']) || empty($data['previews'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing cart_item_id or previews']);
            return;
        }
        
        $cartItemId = (int)$data['cart_item_id'];

        // Verify this cart item belongs to the user's cart
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
        
        $stmt = $this->db->prepare("SELECT id FROM cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$cartItemId, $cartId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not authorized']);
            return;
        }
        if (!WriteLimit::allow($this->db, 'preview')) {
            WriteLimit::refuse();
            return;
        }

        // Resolve a per-cart-item folder token. Stored on the row so the
        // folder name is unguessable and stable across saves.
        $folder = $this->cartItemPathToken($cartItemId);
        $previewPaths = PreviewImages::store($folder, (array)$data['previews']);

        // Update cart_items with preview paths
        if (!empty($previewPaths)) {
            $stmt = $this->db->prepare("UPDATE cart_items SET preview_images = ? WHERE id = ?");
            $stmt->execute([json_encode($previewPaths), $cartItemId]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'previews' => $previewPaths]);
    }

    /**
     * Random folder token for a cart item, used so the on-disk preview path
     * isn't a predictable function of the cart_item_id. Persists to
     * cart_items.path_token so subsequent saves go to the same folder.
     * Falls back to the legacy cart_{id} folder if the column doesn't exist
     * (migration not yet applied).
     */
    private function cartItemPathToken(int $cartItemId): string {
        try {
            $stmt = $this->db->prepare("SELECT path_token FROM cart_items WHERE id = ?");
            $stmt->execute([$cartItemId]);
            $token = $stmt->fetchColumn();
            if (is_string($token) && strlen($token) === 32 && ctype_xdigit($token)) {
                return $token;
            }
        } catch (\PDOException $e) {
            return 'cart_' . $cartItemId;
        }

        try {
            $token = bin2hex(random_bytes(16));
            $upd = $this->db->prepare("UPDATE cart_items SET path_token = ? WHERE id = ? AND (path_token IS NULL OR path_token = '')");
            $upd->execute([$token, $cartItemId]);
            $stmt = $this->db->prepare("SELECT path_token FROM cart_items WHERE id = ?");
            $stmt->execute([$cartItemId]);
            $persisted = $stmt->fetchColumn();
            return is_string($persisted) && $persisted !== '' ? $persisted : $token;
        } catch (\PDOException $e) {
            return 'cart_' . $cartItemId;
        }
    }
}
