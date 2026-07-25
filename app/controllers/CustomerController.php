<?php

class CustomerController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
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

    // Set selected product ID in session
    public function setSelectedProduct(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['product_id'] ?? null;
        if ($id) {
            $_SESSION['selected_product_id'] = $id;
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No product ID']);
        }
    }
    
    /**
     * My Account page - shows saved designs, order history, profile
     */
    public function account(): void {
        Auth::requireLogin();
        $userId = Auth::userId();
        
        // Get user info
        $stmt = $this->db->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get saved designs with product info
        $stmt = $this->db->prepare("
            SELECT cd.*, p.name as product_name, p.image_path as product_image, 
                   p.back_image_path as product_back_image, p.base_price
            FROM custom_designs cd
            LEFT JOIN products p ON cd.product_id = p.id
            WHERE cd.user_id = ?
            ORDER BY cd.created_at DESC
        ");
        $stmt->execute([$userId]);
        $savedDesigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load uploads for each design
        foreach ($savedDesigns as &$design) {
            try {
                $uploadStmt = $this->db->prepare("
                    SELECT stored_file_path, view_placement, position_x, position_y, 
                           width, height, rotation, layer_order
                    FROM custom_design_uploads 
                    WHERE design_id = ? 
                    ORDER BY layer_order
                ");
                $uploadStmt->execute([$design['id']]);
                $design['uploads'] = $uploadStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $design['uploads'] = [];
            }
            
            // Load text elements for each design
            try {
                $textStmt = $this->db->prepare("
                    SELECT text_content, font_family, font_size, text_color,
                           is_bold, is_italic, is_underline, view_placement,
                           position_x, position_y, layer_order
                    FROM custom_design_texts 
                    WHERE design_id = ? 
                    ORDER BY layer_order
                ");
                $textStmt->execute([$design['id']]);
                $design['texts'] = $textStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $design['texts'] = [];
            }
        }
        unset($design); // break reference
        
        // Get orders with payment info and item count
        $stmt = $this->db->prepare("
            SELECT o.id, o.status, o.total_price, o.total_products, o.created_at,
                   op.payment_method, op.card_brand, op.card_last4,
                   COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN order_payments op ON op.order_id = o.id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ?
            GROUP BY o.id, op.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/customer/account.php';
    }

    public function orderList(): void {
        Auth::requireLogin();
        $userId = Auth::userId();

        $stmt = $this->db->prepare("
            SELECT o.id, o.status, o.total_price, o.created_at,
                   COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/customer/order_list.php';
    }

    public function orderDetail(): void {
        Auth::requireLogin();
        $userId = Auth::userId();

        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$orderId) { http_response_code(404); echo '404 Not Found'; return; }

        $stmt = $this->db->prepare("
            SELECT o.*, op.payment_method, op.card_brand, op.card_last4, op.card_exp_month,
                   op.card_exp_year, op.amount as payment_amount, op.status as payment_status
            FROM orders o
            LEFT JOIN order_payments op ON op.order_id = o.id
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) { http_response_code(404); echo '404 - Order not found'; return; }

        $stmt = $this->db->prepare("
            SELECT oi.*, p.name as product_name, p.image_path as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orderItems as &$item) {
            $item['front_preview'] = null;
            if (!empty($item['preview_images'])) {
                $decoded = json_decode($item['preview_images'], true);
                if (!empty($decoded['front'])) $item['front_preview'] = $decoded['front'];
            }
            if (empty($item['front_preview']) && !empty($item['design_id'])) {
                $pStmt = $this->db->prepare("SELECT preview_images FROM custom_designs WHERE id = ?");
                $pStmt->execute([$item['design_id']]);
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($pRow && !empty($pRow['preview_images'])) {
                    $decoded = json_decode($pRow['preview_images'], true);
                    if (!empty($decoded['front'])) $item['front_preview'] = $decoded['front'];
                }
            }
        }
        unset($item);

        require __DIR__ . '/../views/customer/order_detail.php';
    }

    // Store or update design draft in session
    public function applyDesignChange(): void {
        session_start();
        $data = $_POST['design_item'] ?? null;
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No design item data']);
            return;
        }
        if (!isset($_SESSION['design_draft'])) {
            $_SESSION['design_draft'] = [];
        }
        // Add or update item in draft
        $_SESSION['design_draft'][] = $data;
        echo json_encode(['success' => true, 'draft' => $_SESSION['design_draft']]);
    }

    // Finalize and save design to DB
    public function finalizeDesign(): void {
        session_start();
        $draft = $_SESSION['design_draft'] ?? null;
        if (!$draft || !is_array($draft) || count($draft) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No design draft to save']);
            return;
        }
        require_once __DIR__ . '/../models/CustomDesign.php';
        $designId = \CustomDesign::saveDraft($draft); // You must implement saveDraft in CustomDesign.php
        unset($_SESSION['design_draft']);
        echo json_encode(['success' => true, 'design_id' => $designId]);
    }

    private function saveCartBase64Upload(array $upload): ?string {
        $base64 = (string)($upload['base64'] ?? '');
        if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $base64, $matches)) {
            return null;
        }

        $encoded = substr($base64, strpos($base64, ',') + 1);
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        if (strlen($decoded) > Upload::DEFAULT_MAX_BYTES) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($decoded);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            return null;
        }

        $mime = strtolower((string)$imageInfo['mime']);
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($mimeToExt[$mime])) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $finfoMime = strtolower((string)finfo_buffer($finfo, $decoded));
                finfo_close($finfo);
                if ($finfoMime !== '' && $finfoMime !== $mime) {
                    return null;
                }
            }
        }

        $uploadDir = __DIR__ . '/../../public/uploads/cart';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            error_log('Could not create cart upload directory.');
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $mimeToExt[$mime];
        $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($fullPath, $decoded) === false) {
            return null;
        }

        @chmod($fullPath, 0644);
        return 'uploads/cart/' . $filename;
    }

    private function safeExistingCartUploadPath(?string $path): string {
        $path = ltrim((string)$path, '/');
        if (!preg_match('#^uploads/cart/[A-Za-z0-9._-]+\.(png|jpe?g|gif|webp)$#i', $path)) {
            return '';
        }
        return $path;
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

    /**
     * Authoritative per-unit RETAIL price for a cart/order line: the supplier
     * cost marked up by the quantity-tiered margin (Pricing), plus any premade
     * design price. Print-placement extras (front+back / sleeves) are carried
     * separately in custom_design_fee, so they are NOT included here. Returns
     * null when the supplier cost cannot be resolved.
     */
    private function computeUnitPrice(?int $productId, ?int $variantId, ?int $sizeId, ?int $colorId, int $quantity, float $premadePrice = 0.0, float $extraPrintCost = 0.0): ?float {
        $cost = $this->resolveSupplierCost($variantId, $productId, $sizeId, $colorId);
        if ($cost === null) return null;
        $category = $this->priceCategoryFor($productId);
        // Print add-ons are marked up through the margin (passed as pre-margin cost).
        $retail = Pricing::unitPrice($cost, $category, max(1, $quantity), $extraPrintCost);
        return round($retail + $premadePrice, 2);
    }

    /**
     * Raw pre-margin euro cost of a custom design's print add-ons, derived from
     * its view-keyed elements: front+back print and each printed sleeve.
     */
    private function printExtraCostFor($elements): float {
        if (!is_array($elements)) return 0.0;
        $count = function ($view) use ($elements) {
            return isset($elements[$view]) && is_array($elements[$view]) ? count($elements[$view]) : 0;
        };
        $frontAndBack = $count('front') > 0 && $count('back') > 0;
        $sleeves = ($count('left-sleeve') > 0 ? 1 : 0) + ($count('right-sleeve') > 0 ? 1 : 0);
        return Pricing::printExtraCost($frontAndBack, $sleeves);
    }

    public function cartAdd(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
            return;
        }
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
        $userId = Auth::userId();
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
        // Fetch custom design if design_id provided
        $design = null;
        if (!empty($data['design_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM custom_designs WHERE id = ? LIMIT 1");
            $stmt->execute([$data['design_id']]);
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
        }
        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
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
        $printExtraCost = $this->printExtraCostFor($elements);
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
        $resolvedPrice = $this->computeUnitPrice(
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
            // Handle uploads (base64 or file info in $data['uploads'])
            if (!empty($data['uploads']) && is_array($data['uploads'])) {
                require_once __DIR__ . '/../models/Cart.php';
                $cartModel = new \Cart($this->db);
                foreach ($data['uploads'] as $upload) {
                    // Save base64 image to file if needed
                    if (!empty($upload['base64']) && !empty($upload['original_filename'])) {
                        $storedPath = $this->saveCartBase64Upload($upload);
                    } else {
                        $storedPath = $this->safeExistingCartUploadPath($upload['stored_file_path'] ?? '');
                    }

                    if (!$storedPath) {
                        continue;
                    }

                    $cartModel->addUpload($cartItemId, [
                        'original_filename' => $upload['original_filename'] ?? '',
                        'stored_file_path' => $storedPath,
                        'placement' => $upload['placement'] ?? 'front',
                        'position_x' => $upload['position_x'] ?? 0,
                        'position_y' => $upload['position_y'] ?? 0,
                        'width' => $upload['width'] ?? 80,
                        'height' => $upload['height'] ?? 80
                    ]);
                }
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'cart_item_id' => $cartItemId]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to add to cart.']);
        }
    }
    
    public function cartSavePreviews(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
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
        
        $userId = Auth::userId();
        $cartItemId = (int)$data['cart_item_id'];
        
        // Verify this cart item belongs to the user's cart
        require_once __DIR__ . '/../models/Cart.php';
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

        // Resolve a per-cart-item folder token. Stored on the row so the
        // folder name is unguessable and stable across saves.
        $folder = $this->cartItemPathToken($cartItemId);
        $previewDir = __DIR__ . '/../../public/images/designs/previews/' . $folder;
        if (!is_dir($previewDir)) {
            mkdir($previewDir, 0755, true);
        }

        $previewPaths = [];
        $validViews = ['front', 'back', 'left-sleeve', 'right-sleeve', 'front_design'];
        $allowedImageTypes = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        foreach ($data['previews'] as $view => $base64Data) {
            if (!in_array($view, $validViews)) continue;
            if (empty($base64Data)) continue;

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $imageType = $matches[1];
                if (!in_array(strtolower($imageType), $allowedImageTypes)) continue;
                $rawData = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
            } else {
                continue;
            }

            if ($rawData === false) continue;

            $imageInfo = @getimagesizefromstring($rawData);
            if ($imageInfo === false) continue;

            $ext = $imageType === 'jpeg' ? 'jpg' : $imageType;
            $filename = str_replace('-', '_', $view) . '.' . $ext;
            $fullPath = $previewDir . '/' . $filename;
            $relativePath = 'images/designs/previews/' . $folder . '/' . $filename;
            
            // Delete old previews for this view
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExt) {
                $oldFile = $previewDir . '/' . str_replace('-', '_', $view) . '.' . $oldExt;
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
            
            if (file_put_contents($fullPath, $rawData) !== false) {
                $previewPaths[$view] = $relativePath;
            }
        }
        
        // Update cart_items with preview paths
        if (!empty($previewPaths)) {
            $stmt = $this->db->prepare("UPDATE cart_items SET preview_images = ? WHERE id = ?");
            $stmt->execute([json_encode($previewPaths), $cartItemId]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'previews' => $previewPaths]);
    }
    
    public function cartRemove(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
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
        $userId = Auth::userId();
        
        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
        
        // Verify the cart item belongs to this user's cart
        $stmt = $this->db->prepare("SELECT id FROM cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$cartItemId, $cartId]);
        if (!$stmt->fetch()) {
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
    
    public function cartUpdateQuantity(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
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
        $userId = Auth::userId();
        
        if ($quantity < 1) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Quantity must be at least 1']);
            return;
        }
        
        require_once __DIR__ . '/../models/Cart.php';
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
                    $printExtraCost = $this->printExtraCostFor($dd);
                }
            }
        }
        $recomputed = $this->computeUnitPrice(
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
            // print-placement fee, matching the Stripe charge in createPaymentIntent().
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

    public function createPaymentIntent(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Login required']);
            return;
        }

        $userId = Auth::userId();
        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartId    = $cartModel->getOrCreateCartId($userId);

        $stmt = $this->db->prepare("
            SELECT SUM((ci.unit_price + ci.custom_design_fee) * ci.quantity) AS total
            FROM cart_items ci
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $total = (float)($stmt->fetchColumn() ?: 0);

        if ($total <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cart is empty']);
            return;
        }

        try {
            $intent = \Stripe::createPaymentIntent(
                (int)round($total * 100),
                'eur',
                ['user_id' => $userId, 'cart_id' => $cartId]
            );
            echo json_encode(['clientSecret' => $intent['client_secret']]);
        } catch (\Throwable $e) {
            error_log('Stripe createPaymentIntent error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Payment initialisation failed. Please try again.']);
        }
    }

    public function checkout(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request data']);
            return;
        }

        $paymentMethod = trim($data['payment_method'] ?? 'card');
        // Only 'card' (Stripe) actually collects and verifies a payment. The
        // Revolut/PayPal/Apple Pay/Google Pay buttons in the UI are not wired to
        // any payment gateway yet, so accepting them here would create a fully
        // valid, "paid" order without a single cent changing hands. Until those
        // flows are implemented, reject anything but the verified card flow.
        $allowedMethods = ['card'];
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'This payment method is not available yet. Please pay by card.']);
            return;
        }

        // Card payments must be verified through Stripe
        $cardName   = '';
        $cardLast4  = '';
        $cardBrand  = '';
        $expMonth   = 0;
        $expYear    = 0;
        $billingZip = '';

        if ($paymentMethod === 'card') {
            $piId = trim($data['stripe_payment_intent_id'] ?? '');
            if (!$piId || !preg_match('/^pi_[A-Za-z0-9_]+$/', $piId)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing Stripe payment reference']);
                return;
            }

            try {
                $pi = \Stripe::retrievePaymentIntent($piId);
            } catch (\Throwable $e) {
                error_log('Stripe retrieve error: ' . $e->getMessage());
                http_response_code(502);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Could not verify payment. Please contact support.']);
                return;
            }

            if (($pi['status'] ?? '') !== 'succeeded') {
                http_response_code(402);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Payment was not completed']);
                return;
            }

            // Replay guard: a succeeded PaymentIntent may only ever back one
            // order. Without this, a customer could re-post the same verified
            // pi_… (after re-filling the cart to the same total) and get extra
            // orders fulfilled for a single payment. The UNIQUE index on
            // order_payments.payment_intent_id is the hard backstop below;
            // this check returns a clean error for the common case.
            try {
                $dupe = $this->db->prepare("SELECT COUNT(*) FROM order_payments WHERE payment_intent_id = ?");
                $dupe->execute([$piId]);
                if ((int)$dupe->fetchColumn() > 0) {
                    http_response_code(409);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'This payment has already been processed.']);
                    return;
                }
            } catch (PDOException $e) {
                // Column missing (migration not yet applied) — log and continue;
                // the amount check still applies. Run database/migrate.php.
                error_log('payment_intent_id dedup check skipped: ' . $e->getMessage());
            }

            // Pull card details from the charge attached to the intent
            $charge    = $pi['latest_charge'] ?? [];
            $cardDetails = $charge['payment_method_details']['card'] ?? [];
            $cardBrand  = $cardDetails['brand'] ?? '';
            $cardLast4  = $cardDetails['last4'] ?? '';
            $expMonth   = (int)($cardDetails['exp_month'] ?? 0);
            $expYear    = (int)($cardDetails['exp_year'] ?? 0);
            $cardName   = $charge['billing_details']['name'] ?? '';
            $billingZip = $charge['billing_details']['address']['postal_code'] ?? '';
        }

        $userId = Auth::userId();
        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);

        $stmt = $this->db->prepare("
            SELECT ci.*, 
                   p.base_price,
                   ps.size_name, 
                   ac.color_name,
                   ac.color_hex AS color_hex
            FROM cart_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_sizes ps ON ci.size_id = ps.id
            LEFT JOIN available_colors ac ON ci.color_id = ac.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cartItems)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Cart is empty']);
            return;
        }

        $totalProducts = 0;
        $totalPrice = 0.0;
        foreach ($cartItems as $item) {
            $qty = (int)($item['quantity'] ?? 0);
            // Retail unit_price is authoritative; base_price (supplier cost) is
            // only a fallback for legacy rows that never stored a unit_price.
            $unitBase = (float)($item['unit_price'] ?? 0) ?: (float)($item['base_price'] ?? 0);
            $fee = (float)($item['custom_design_fee'] ?? 0);
            $totalProducts += $qty;
            $totalPrice += ($unitBase + $fee) * $qty;
        }

        // Verify the Stripe charge amount matches the cart total (within 1 cent rounding)
        if ($paymentMethod === 'card' && isset($pi)) {
            $expectedCents = (int)round($totalPrice * 100);
            $chargedCents  = (int)($pi['amount'] ?? 0);
            if (abs($chargedCents - $expectedCents) > 1) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Payment amount does not match cart total']);
                return;
            }
        }

        $orderStatus = ($paymentMethod === 'card') ? 'paid' : 'pending';

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO orders (user_id, status, total_price, total_products) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $orderStatus, $totalPrice, $totalProducts]);
            $orderId = (int)$this->db->lastInsertId();

            $orderItemStmt = $this->db->prepare("
                INSERT INTO order_items 
                (order_id, product_id, variant_id, size_name, color_name, color_hex, quantity, unit_price, custom_design_fee, is_custom_design, design_id, preview_images) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $orderDesignStmt = $this->db->prepare("
                INSERT INTO order_item_designs (order_item_id, design_data) VALUES (?, ?)
            ");

            $orderUploadStmt = $this->db->prepare("
                INSERT INTO order_item_uploads (order_item_id, original_filename, stored_file_path, placement, position_x, position_y, width, height)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($cartItems as $item) {
                $unitBase = (float)($item['unit_price'] ?? 0) ?: (float)($item['base_price'] ?? 0);
                $fee = (float)($item['custom_design_fee'] ?? 0);
                $isCustom = !empty($item['is_custom_design']) || !empty($item['design_data']);
                
                // Use preview_images from cart_items (generated at add-to-cart time with the cart-selected color)
                // Falls back to custom_designs previews if cart item has none
                $designId = $item['design_id'] ?? null;
                $previewImages = $item['preview_images'] ?? null;
                if (!$previewImages && $designId) {
                    $previewStmt = $this->db->prepare("SELECT preview_images FROM custom_designs WHERE id = ?");
                    $previewStmt->execute([$designId]);
                    $designRow = $previewStmt->fetch(PDO::FETCH_ASSOC);
                    $previewImages = $designRow['preview_images'] ?? null;
                }

                $orderItemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $item['size_name'] ?? null,
                    $item['color_name'] ?? null,
                    $item['color_hex'] ?? null,
                    $item['quantity'],
                    $unitBase,
                    $fee,
                    $isCustom ? 1 : 0,
                    $designId,
                    $previewImages
                ]);

                $orderItemId = (int)$this->db->lastInsertId();

                if (!empty($item['design_data'])) {
                    $orderDesignStmt->execute([$orderItemId, $item['design_data']]);
                }

                $uploadsStmt = $this->db->prepare("SELECT * FROM cart_item_uploads WHERE cart_item_id = ?");
                $uploadsStmt->execute([$item['id']]);
                $uploads = $uploadsStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($uploads as $upload) {
                    $orderUploadStmt->execute([
                        $orderItemId,
                        $upload['original_filename'] ?? null,
                        $upload['stored_file_path'] ?? ($upload['file_path'] ?? ''),
                        $upload['placement'] ?? 'front',
                        $upload['position_x'] ?? 0,
                        $upload['position_y'] ?? 0,
                        $upload['width'] ?? 80,
                        $upload['height'] ?? 80
                    ]);
                }
            }

            $paymentStmt = $this->db->prepare("
                INSERT INTO order_payments
                (order_id, payment_method, card_brand, card_holder, card_last4, card_exp_month, card_exp_year, billing_zip, amount, status, payment_intent_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)
            ");
            $paymentStmt->execute([
                $orderId,
                $paymentMethod,
                $paymentMethod === 'card' ? $cardBrand : null,
                $paymentMethod === 'card' ? $cardName : null,
                $paymentMethod === 'card' ? $cardLast4 : null,
                $paymentMethod === 'card' ? $expMonth : null,
                $paymentMethod === 'card' ? $expYear : null,
                $paymentMethod === 'card' ? $billingZip : null,
                $totalPrice,
                $paymentMethod === 'card' ? ($piId ?? null) : null
            ]);

            $stmt = $this->db->prepare("DELETE FROM cart_item_uploads WHERE cart_item_id IN (SELECT id FROM cart_items WHERE cart_id = ?)");
            $stmt->execute([$cartId]);
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cartId]);

            $this->db->commit();
            $_SESSION['cart_count'] = 0;

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'order_id' => $orderId]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Checkout error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            // Never surface raw DB/exception detail to the client.
            echo json_encode(['error' => 'We could not complete your order. Please try again or contact support.']);
        }
    }
    
        public function cart(): void {
        if (!Auth::check()) {
            $cartItems = [];
            $cartTotal = 0;
            require __DIR__ . '/../views/customer/cart.php';
            return;
        }
        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId(Auth::userId());
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
        
        require __DIR__ . '/../views/customer/cart.php';
        }

    public function home(): void {
        $user = null;
        $hasOrders = false;

        if (Auth::check()) {
            $userId = Auth::userId();
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
            $stmt->execute([$userId]);
            $hasOrders = (int)$stmt->fetchColumn() > 0;
        }

        // Get featured products (active products, limit 4)
        $stmt = $this->db->query("SELECT id, name, slug, base_price, image_path, active FROM products WHERE active = 1 ORDER BY id DESC LIMIT 4");
        $featuredProducts = $stmt->fetchAll();

        require __DIR__ . '/../views/customer/home.php';
    }

    public function shop(): void {
        // Shop landing page - choose between premade and custom
        require __DIR__ . '/../views/customer/shop_landing.php';
    }

    public function shopPremade(): void {
        // Show category sections (Anime, Coming Soon, etc.)
        require __DIR__ . '/../views/customer/shop.php';
    }

    public function shopAnime(): void {

        // Get anime designs with first associated product images and base price
        $stmt = $this->db->prepare("
            SELECT d.*,
                (SELECT p.image_path FROM products p
                 JOIN design_products dp ON dp.product_id = p.id
                 WHERE dp.design_id = d.id AND p.active = 1
                 ORDER BY dp.id ASC LIMIT 1) AS product_image_path,
                (SELECT p.back_image_path FROM products p
                 JOIN design_products dp ON dp.product_id = p.id
                 WHERE dp.design_id = d.id AND p.active = 1
                 ORDER BY dp.id ASC LIMIT 1) AS product_back_image_path,
                (SELECT p.base_price FROM products p
                 JOIN design_products dp ON dp.product_id = p.id
                 WHERE dp.design_id = d.id AND p.active = 1
                 ORDER BY dp.id ASC LIMIT 1) AS product_base_price
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            WHERE s.slug = 'anime' AND d.active = 1
            ORDER BY d.name
        ");
        $stmt->execute();
        $designs = $stmt->fetchAll();

        require __DIR__ . '/../views/customer/shop_anime.php';
    }
    // Removed duplicate declaration
    public function customProduct(): void {
        $id = $_SESSION['selected_product_id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo "No product ID provided";
            return;
        }
        // Get product details
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo "Product not found";
            return;
        }
        // Get all available colors for this product
        $stmt = $this->db->prepare("
            SELECT ac.id, ac.color_name AS name, ac.color_hex AS hex
            FROM available_colors ac
            JOIN product_variants pv ON pv.color_id = ac.id
            WHERE pv.product_id = ?
            GROUP BY ac.id
            ORDER BY ac.id
        ");
        $stmt->execute([$id]);
        $colors = $stmt->fetchAll();
        // Get all available sizes for this product
        $stmt = $this->db->prepare("
            SELECT ps.id, ps.size_name
            FROM product_sizes ps
            WHERE ps.product_id = ?
            ORDER BY ps.size_order
        ");
        $stmt->execute([$id]);
        $sizes = $stmt->fetchAll();
        // Build color-to-size matrix
        $stmt = $this->db->prepare("
            SELECT pv.color_id, pv.size_id
            FROM product_variants pv
            WHERE pv.product_id = ?
        ");
        $stmt->execute([$id]);
        $matrixRows = $stmt->fetchAll();
        $colorSizeMatrix = [];
        foreach ($matrixRows as $row) {
            $colorSizeMatrix[$row['color_id']][] = $row['size_id'];
        }
        // Get product thumbnails (if you have a table or logic for this)
        $thumbnails = [];
        // Example: if you have a product_images table:
        // $stmt = $this->db->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order");
        // $stmt->execute([$id]);
        // $thumbnails = array_column($stmt->fetchAll(), 'image_path');
        require __DIR__ . '/../views/customer/custom_product.php';
    }

    public function viewDesign(): void {
        // Get design ID from URL
        $uri = $_SERVER['REQUEST_URI'];
        $parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
        $designId = (int) end($parts);

        // Get design details
        $stmt = $this->db->prepare("
            SELECT d.*, s.name as section_name, s.slug as section_slug, s.icon as section_icon
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            WHERE d.id = ? AND d.active = 1
        ");
        $stmt->execute([$designId]);
        $design = $stmt->fetch();

        if (!$design) {
            http_response_code(404);
            echo "Design not found";
            return;
        }

        // Get products this design is available on (with their sizes and colors).
        // Skip products that don't have a mockup image yet — the design page
        // can't render a preview without one, and selecting them would leave
        // the previous product's image on screen.
        $stmt = $this->db->prepare("
            SELECT p.*,
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id) as size_count
            FROM products p
            JOIN design_products dp ON p.id = dp.product_id
            WHERE dp.design_id = ?
              AND p.active = 1
              AND p.image_path IS NOT NULL
              AND p.image_path <> ''
            ORDER BY p.name
        ");
        $stmt->execute([$designId]);
        $availableProducts = $stmt->fetchAll();

        // Get sizes for each product
        foreach ($availableProducts as &$product) {
            $stmt = $this->db->prepare("
                SELECT ps.*, 
                       GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names,
                       GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes,
                       GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids
                FROM product_sizes ps
                LEFT JOIN product_variants pv ON ps.id = pv.size_id
                LEFT JOIN available_colors ac ON pv.color_id = ac.id
                WHERE ps.product_id = ?
                GROUP BY ps.id
                ORDER BY ps.size_order
            ");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);

        require __DIR__ . '/../views/customer/view_design.php';
    }
        

    public function shopCustom(): void {
        // Check if loading an existing design (requires login)
        $loadDesign = null;
        if (!empty($_GET['load']) && Auth::check()) {
            $designId = (int)$_GET['load'];
            $userId = Auth::userId();
            
            // Fetch the design (only if it belongs to the current user)
            $stmt = $this->db->prepare("
                SELECT cd.*, p.name as product_name, p.image_path, p.back_image_path,
                       p.left_sleeve_image_path, p.right_sleeve_image_path,
                       ac.color_hex as saved_color_hex, ac.color_name
                FROM custom_designs cd
                LEFT JOIN products p ON cd.product_id = p.id
                LEFT JOIN available_colors ac ON cd.color_id = ac.id
                WHERE cd.id = ? AND cd.user_id = ?
            ");
            $stmt->execute([$designId, $userId]);
            $loadDesign = $stmt->fetch();
            
            // Also fetch the uploads and texts separately for proper image paths
            if ($loadDesign) {
                try {
                    $stmt = $this->db->prepare("SELECT * FROM custom_design_uploads WHERE design_id = ? ORDER BY layer_order");
                    $stmt->execute([$designId]);
                    $loadDesign['uploads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $loadDesign['uploads'] = [];
                }
                
                try {
                    $stmt = $this->db->prepare("SELECT * FROM custom_design_texts WHERE design_id = ? ORDER BY layer_order");
                    $stmt->execute([$designId]);
                    $loadDesign['texts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $loadDesign['texts'] = [];
                }
            }
        }
        
        // Get all active products with their sizes and colors. Same image-path
        // filter as viewDesign — products without a mockup PNG can't be
        // rendered in the studio, so they're hidden until artwork is uploaded.
        $stmt = $this->db->query("
            SELECT p.*,
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id) as size_count
            FROM products p
            WHERE p.active = 1
              AND p.image_path IS NOT NULL
              AND p.image_path <> ''
            ORDER BY p.name
        ");
        $products = $stmt->fetchAll();

        // Get sizes and colors for each product
        foreach ($products as &$product) {
            $stmt = $this->db->prepare("
                SELECT ps.*, 
                       GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names,
                       GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes,
                       GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids
                FROM product_sizes ps
                LEFT JOIN product_variants pv ON ps.id = pv.size_id
                LEFT JOIN available_colors ac ON pv.color_id = ac.id
                WHERE ps.product_id = ?
                GROUP BY ps.id
                ORDER BY ps.size_order
            ");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);

        require __DIR__ . '/../views/customer/shop_custom.php';
    }

    public function product(?int $id = null): void {
        if (!$id) {
            header('Location: /shop');
            exit;
        }

        // Get product details
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            http_response_code(404);
            echo "Product not found";
            return;
        }

        // Get product variants. product_variants only stores colour/size as FKs,
        // so resolve them here - the view renders swatches and a size dropdown and
        // needs the hex and the size name, not the ids.
        $stmt = $this->db->prepare("
            SELECT pv.id, pv.product_id, pv.stock_quantity, pv.is_available,
                   ac.color_hex     AS color,
                   ac.color_name    AS color_name,
                   ps.size_name     AS size,
                   ps.size_order,
                   COALESCE(ps.price_modifier, 0) AS price_modifier
            FROM product_variants pv
            LEFT JOIN available_colors ac ON ac.id = pv.color_id
            LEFT JOIN product_sizes   ps ON ps.id = pv.size_id
            WHERE pv.product_id = ?
              AND pv.is_available = 1
              AND ac.color_hex IS NOT NULL
              AND ps.size_name IS NOT NULL
            ORDER BY ps.size_order, ac.id
        ");
        $stmt->execute([$id]);
        $variants = $stmt->fetchAll();

        require __DIR__ . '/../views/customer/product.php';
    }

    public function about(): void {
        require __DIR__ . '/../views/customer/about.php';
    }

    public function contact(): void {
        require __DIR__ . '/../views/customer/contact.php';
    }

    public function contactSubmit(): void {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name || !$email || !$subject || !$message) {
            $_SESSION['flash_error'] = I18n::t('contact.error_required');
            header('Location: /contact');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = I18n::t('contact.error_email');
            header('Location: /contact');
            return;
        }

        $toAddress = Env::get('CONTACT_EMAIL', Env::get('MAIL_FROM_ADDRESS', 'no-reply@costaspressjr.com'));
        $htmlBody  = '<p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>'
                   . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
                   . '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>'
                   . '<p><strong>Message:</strong><br>' . nl2br(htmlspecialchars($message)) . '</p>';

        Mailer::send($toAddress, '[Contact] ' . $subject, $htmlBody);

        $_SESSION['flash_success'] = I18n::t('contact.success');
        header('Location: /contact');
    }

    /**
     * Render a static informational page from app/views/customer/info/{slug}.php.
     * Slug is strictly allowlisted; no user input ever touches the filesystem path.
     */
    public function infoPage(string $slug): void {
        static $allowed = [
            'terms'       => 'terms.php',
            'privacy'     => 'privacy.php',
            'cookies'     => 'cookies.php',
            'faq'         => 'faq.php',
            'shipping'    => 'shipping.php',
            'returns'     => 'returns.php',
            'sizing'      => 'sizing.php',
            'track-order' => 'track_order.php',
        ];

        if (!isset($allowed[$slug])) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        require __DIR__ . '/../views/customer/info/' . $allowed[$slug];
    }

    public function cookieConsent(): void {
        Auth::requireLogin();
        $userId = Auth::userId();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
            $val = ($_POST['accept'] == '1') ? 1 : 2;
            $stmt = $this->db->prepare("UPDATE users SET cookie_accepted = ? WHERE id = ?");
            $stmt->execute([$val, $userId]);
            echo 'OK';
        } else {
            http_response_code(400);
            echo 'Invalid request';
        }
    }
    public function shopSelectProduct(): void {
        // Hide products without a mockup image — they can't be previewed in
        // the customizer flow that this picker leads into.
        $stmt = $this->db->query("
            SELECT p.*, (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id) as size_count
              FROM products p
             WHERE p.active = 1
               AND p.image_path IS NOT NULL
               AND p.image_path <> ''
             ORDER BY p.name");
        $products = $stmt->fetchAll();
        // Get sizes and colors for each product
        foreach ($products as &$product) {
            $stmt = $this->db->prepare("SELECT ps.*, GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names, GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes, GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids FROM product_sizes ps LEFT JOIN product_variants pv ON ps.id = pv.size_id LEFT JOIN available_colors ac ON pv.color_id = ac.id WHERE ps.product_id = ? GROUP BY ps.id ORDER BY ps.size_order");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);
        require __DIR__ . '/../views/customer/shop_select_product.php';
    }

    /**
     * API endpoint to get product variants with sizes and colors for cart modal
     */
    public function getProductVariants(): void {
        header('Content-Type: application/json');
        
        // Get product ID from URL
        $uri = $_SERVER['REQUEST_URI'];
        preg_match('/\/api\/product-variants\/(\d+)/', $uri, $matches);
        $productId = $matches[1] ?? null;
        
        if (!$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID required']);
            return;
        }

        // Get all sizes for this product
        $stmt = $this->db->prepare("
            SELECT ps.id, ps.size_name as name, ps.size_order
            FROM product_sizes ps
            WHERE ps.product_id = ?
            ORDER BY ps.size_order
        ");
        $stmt->execute([$productId]);
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all colors available for this product
        $stmt = $this->db->prepare("
            SELECT DISTINCT ac.id, ac.color_name as name, ac.color_hex as hex
            FROM available_colors ac
            INNER JOIN product_variants pv ON ac.id = pv.color_id
            WHERE pv.product_id = ?
            ORDER BY ac.color_name
        ");
        $stmt->execute([$productId]);
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all variants with availability
        $stmt = $this->db->prepare("
            SELECT pv.id, pv.size_id, pv.color_id, pv.stock_quantity, pv.is_available
            FROM product_variants pv
            WHERE pv.product_id = ?
        ");
        $stmt->execute([$productId]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sizes' => $sizes,
            'colors' => $colors,
            'variants' => $variants
        ]);
    }
}
