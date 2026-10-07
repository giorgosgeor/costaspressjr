<?php

class CustomerController extends Controller {
    /**
     * Date $days business days from today, formatted for display.
     * Used for delivery estimates so the page never shows a frozen literal date.
     */
    private function businessDaysFromNow(int $days): string {
        $d = new DateTimeImmutable('today');
        while ($days > 0) {
            $d = $d->modify('+1 day');
            if ((int)$d->format('N') < 6) {   // 6 = Sat, 7 = Sun
                $days--;
            }
        }
        return $d->format('D, j M');
    }

    /** @see Auth::effectiveUserId() — kept as a thin wrapper for call sites here. */
    private function effectiveUserId(bool $createGuest = false): ?int {
        return Auth::effectiveUserId($this->db, $createGuest);
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
                   p.back_image_path as product_back_image, p.base_price, p.active AS product_active,
                   p.da_front_x, p.da_front_y, p.da_front_w, p.da_front_h
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

        // My Uploads: every distinct artwork this user has put on a design.
        // Grouped by CONTENT hash, not path — the same picture uploaded twice
        // used to be two files, and the library should show one item either way.
        // Rows predating the hash column fall back to their path so they still
        // group sensibly instead of collapsing together under a NULL key.
        $stmt = $this->db->prepare("
            SELECT MIN(u.stored_file_path)  AS stored_file_path,
                   MIN(u.original_filename) AS original_filename,
                   MAX(u.file_size)         AS file_size,
                   MIN(u.mime_type)         AS mime_type,
                   MIN(u.created_at)        AS created_at,
                   COUNT(DISTINCT u.design_id) AS design_count,
                   GROUP_CONCAT(DISTINCT cd.name ORDER BY cd.name SEPARATOR ', ') AS design_names
            FROM custom_design_uploads u
            JOIN custom_designs cd ON cd.id = u.design_id
            WHERE cd.user_id = ?
            GROUP BY COALESCE(u.file_hash, u.stored_file_path)
            ORDER BY MIN(u.created_at) DESC
        ");
        $stmt->execute([$userId]);
        $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $favorites = $this->favoritesFor($userId);

        $this->render('customer/account', get_defined_vars());
    }

    /**
     * Saved products and premade designs, newest first. Inactive products are
     * still listed (with a flag) rather than dropped — silently losing a saved
     * item looks like a bug to the person who saved it.
     */
    private function favoritesFor(int $userId): array {
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
        // the shop does. Designs already carry a customer-facing price.
        foreach ($rows as &$r) {
            $r['display_price'] = $r['kind'] === 'product'
                ? Pricing::unitPrice((float)$r['base_price'], Pricing::categoryFor($r['slug'] ?? '', $r['name'] ?? ''), 1)
                : (float)$r['base_price'];
        }
        unset($r);
        return $rows;
    }

    /**
     * Ids the current user has favourited, for rendering hearts in their filled
     * state. Guests have none — the heart still shows, and clicking it sends
     * them to log in.
     */
    private function favoriteIds(string $kind): array {
        if (!Auth::check()) return [];
        $column = $kind === 'product' ? 'product_id' : 'design_id';
        $stmt = $this->db->prepare("SELECT {$column} FROM user_favorites WHERE user_id = ? AND {$column} IS NOT NULL");
        $stmt->execute([(int)Auth::userId()]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Add or remove a favourite. Returns the resulting state so the button can
     * settle on what the server actually recorded rather than assuming.
     */
    public function toggleFavorite(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'login_required']);
            return;
        }
        $userId = (int)Auth::userId();
        $data   = json_decode(file_get_contents('php://input'), true) ?: [];
        $kind   = $data['kind'] ?? '';
        $id     = (int)($data['id'] ?? 0);

        if ($id <= 0 || !in_array($kind, ['product', 'design'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'bad_request']);
            return;
        }

        $column = $kind === 'product' ? 'product_id' : 'design_id';
        $table  = $kind === 'product' ? 'products' : 'premade_designs';

        // Confirm the target exists before storing a reference to it.
        $chk = $this->db->prepare("SELECT 1 FROM {$table} WHERE id = ? LIMIT 1");
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'not_found']);
            return;
        }

        $find = $this->db->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND {$column} = ? LIMIT 1");
        $find->execute([$userId, $id]);
        $existing = $find->fetchColumn();

        if ($existing) {
            $this->db->prepare("DELETE FROM user_favorites WHERE id = ?")->execute([$existing]);
            echo json_encode(['favorited' => false]);
            return;
        }

        $this->db->prepare("INSERT INTO user_favorites (user_id, {$column}) VALUES (?, ?)")
                 ->execute([$userId, $id]);
        echo json_encode(['favorited' => true]);
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

        $this->render('customer/order_list', get_defined_vars());
    }

    /** The branded 404 page — unknown URLs (via Router) and missing records. */
    public function notFound(): void {
        http_response_code(404);
        $this->render('customer/not_found');
    }

    public function orderDetail(): void {
        Auth::requireLogin();
        $userId = Auth::userId();

        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$orderId) { $this->notFound(); return; }

        $stmt = $this->db->prepare("
            SELECT o.*, op.payment_method, op.card_brand, op.card_last4, op.card_exp_month,
                   op.card_exp_year, op.amount as payment_amount, op.status as payment_status
            FROM orders o
            LEFT JOIN order_payments op ON op.order_id = o.id
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) { $this->notFound(); return; }

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

        $this->render('customer/order_detail', get_defined_vars());
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
                // No finfo_close(): a no-op since PHP 8.1 and deprecated in 8.5.
                $finfoMime = strtolower((string)finfo_buffer($finfo, $decoded));
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
        // Print add-ons are flat euro amounts added on top of the marked-up
        // garment price — the margin does not apply to them.
        $retail = Pricing::unitPrice($cost, $category, max(1, $quantity), $extraPrintCost);
        return round($retail + $premadePrice, 2);
    }

    /**
     * Flat euro cost of a custom design's print add-ons, derived from its
     * view-keyed elements: front+back print and each printed sleeve.
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

    /** What the checkout can offer right now. */
    private function checkoutOptions(): array {
        return [
            'acsAvailable' => Pickup::acsAvailable($this->db),
            'acsFee'       => Pickup::acsFee(),
            'storeAddress' => Pickup::storeAddress(),
        ];
    }

    /** The logged-in customer's email; null for guests, who type one at checkout. */
    private function accountEmail(): ?string {
        if (!Auth::check()) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([Auth::userId()]);
        $email = (string)$stmt->fetchColumn();
        return $email !== '' ? $email : null;
    }

    /** GET /api/pickup-points — the ACS points for the checkout map. */
    public function pickupPoints(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=300');
        $points = Pickup::acsAvailable($this->db) ? Pickup::activePoints($this->db) : [];
        echo json_encode(['points' => $points], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Payment step: validate the collection choice, price the cart, and create
     * the PaymentIntent that the Payment Element confirms.
     *
     * Everything needed to place the order is written to pending_checkouts
     * here, BEFORE the customer pays. A redirect payment (Revolut Pay, PayPal)
     * can come back in a different browser, and the webhook has no session.
     */
    public function createPaymentIntent(): void {
        header('Content-Type: application/json');
        // Guests check out too. No guest row yet means no cart — nothing to pay.
        $userId = $this->effectiveUserId(false);
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_empty')]);
            return;
        }

        $data   = json_decode(file_get_contents('php://input'), true);
        $data   = is_array($data) ? $data : [];
        $choice = Pickup::validateChoice($this->db, $data, $this->accountEmail());
        if (isset($choice['error'])) {
            http_response_code(422);
            echo json_encode(['error' => $choice['error']]);
            return;
        }

        $cartModel = new \Cart($this->db);
        $cartId    = $cartModel->getOrCreateCartId($userId);

        // A NULL unit_price row would silently drop out of SUM() here while the
        // order still counts the item — the charge would then never match the
        // order total. Refuse to create the intent instead.
        $bad = $this->db->prepare("SELECT COUNT(*) FROM cart_items WHERE cart_id = ? AND (unit_price IS NULL OR unit_price <= 0)");
        $bad->execute([$cartId]);
        if ((int)$bad->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => I18n::t('checkout.errors.bad_price')]);
            return;
        }

        $stmt = $this->db->prepare("SELECT SUM((unit_price + custom_design_fee) * quantity) FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cartId]);
        $itemsTotal = (float)($stmt->fetchColumn() ?: 0);
        if ($itemsTotal <= 0) {
            http_response_code(400);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_empty')]);
            return;
        }
        $amountCents = (int)round(($itemsTotal + $choice['fee']) * 100);

        // The customer is looking at a total. If the cart changed in another
        // tab since the page loaded, stop rather than charge a different sum.
        if (isset($data['expected_amount']) && (int)$data['expected_amount'] !== $amountCents) {
            http_response_code(409);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_changed'), 'reload' => true]);
            return;
        }

        try {
            $intent = \Stripe::createPaymentIntent($amountCents, 'eur', [
                'user_id'  => $userId,
                'cart_id'  => $cartId,
                'checkout' => OrderPlacement::TAG,
                'delivery' => $choice['method'],
            ]);
        } catch (\Throwable $e) {
            error_log('Stripe createPaymentIntent error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.payment_init')]);
            return;
        }

        try {
            $this->db->prepare("
                INSERT INTO pending_checkouts
                    (payment_intent_id, user_id, cart_id, delivery_method, pickup_point_id, pickup_point,
                     contact_name, contact_phone, contact_email, shipping_fee, amount_cents)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $intent['id'], $userId, $cartId, $choice['method'],
                $choice['point'] ? (int)$choice['point']['id'] : null, Pickup::snapshot($choice['point']),
                $choice['name'], $choice['phone'], $choice['email'], $choice['fee'], $amountCents,
            ]);
            // Unpaid intents leave rows behind; paid ones are deleted when the
            // order is placed. A week outlasts Stripe's webhook retries.
            if (random_int(1, 20) === 1) {
                $this->db->exec("DELETE FROM pending_checkouts WHERE created_at < NOW() - INTERVAL 7 DAY");
            }
        } catch (PDOException $e) {
            // Nothing has been charged yet — the intent is simply abandoned.
            error_log('pending checkout insert failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.generic')]);
            return;
        }

        echo json_encode(['clientSecret' => $intent['client_secret'], 'amount' => $amountCents]);
    }

    /**
     * GET /checkout/complete — where every payment ends. Stripe.js redirects
     * here after confirming (cards and wallets straight away, Revolut Pay and
     * PayPal after their own page) with ?payment_intent=…&payment_intent_client_secret=….
     */
    public function checkoutComplete(): void {
        $piId   = (string)($_GET['payment_intent'] ?? '');
        $secret = (string)($_GET['payment_intent_client_secret'] ?? '');
        if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $piId) || $secret === '') {
            header('Location: /cart');
            return;
        }

        $result = null;
        try {
            $pi = \Stripe::retrievePaymentIntent($piId);
        } catch (\Throwable $e) {
            error_log('checkout complete: retrieve failed: ' . $e->getMessage());
            $state   = 'failed';
            $message = I18n::t('checkout.errors.verify');
            $this->render('customer/checkout_complete', get_defined_vars());
            return;
        }

        // The client secret proves this browser started the payment. That is
        // what lets the order be shown even when the customer's banking app
        // returns them in a browser with no session.
        if (!hash_equals((string)($pi['client_secret'] ?? ''), $secret)) {
            header('Location: /cart');
            return;
        }

        $status = $pi['status'] ?? '';
        if ($status === 'succeeded') {
            $result = (new OrderPlacement($this->db))->place($piId, null, $pi);
            if ($result['status'] === 'failed') {
                $state   = 'failed';
                $message = $result['error'];
            } else {
                $state = 'placed';
                if ((int)$result['user_id'] === (int)$this->effectiveUserId(false)) {
                    $_SESSION['cart_count'] = 0;
                }
                $placed = $this->orderForConfirmation((int)$result['order_id']);
            }
        } elseif ($status === 'processing') {
            $state = 'processing';
        } else {
            // Back from the bank without paying (cancelled or declined).
            $state = 'not_paid';
        }

        $this->render('customer/checkout_complete', get_defined_vars());
    }

    /**
     * What the confirmation page shows: the order, its lines and how it was
     * paid. Null if it can't be read — the page still shows the number.
     */
    private function orderForConfirmation(int $orderId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT o.id, o.tracking_token, o.total_price, o.shipping_fee, o.delivery_method, o.pickup_point,
                       op.payment_method, op.card_brand, op.card_last4
                FROM orders o LEFT JOIN order_payments op ON op.order_id = o.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return null;
            }
            $stmt = $this->db->prepare("
                SELECT oi.quantity, oi.size_name, oi.color_name, oi.unit_price, oi.custom_design_fee,
                       oi.preview_images, p.name AS product_name, p.image_path AS product_image
                FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
                ORDER BY oi.id
            ");
            $stmt->execute([$orderId]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $point = $order['pickup_point'] ? json_decode($order['pickup_point'], true) : null;
            $order['point'] = is_array($point) ? $point : null;
            return $order;
        } catch (PDOException $e) {
            error_log('order confirmation: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * POST /stripe/webhook — the backstop. If the customer paid and closed the
     * tab before coming back, this still places the order (or refunds it).
     * It also keeps order_payments.status in step with refunds and disputes
     * made outside the site.
     * Configure in Stripe: events payment_intent.succeeded, charge.refunded,
     * charge.dispute.created and charge.dispute.closed; signing secret in
     * STRIPE_WEBHOOK_SECRET.
     */
    public function stripeWebhook(): void {
        header('Content-Type: application/json');
        $payload = (string)file_get_contents('php://input');
        $event   = \Stripe::verifyWebhook($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
        if (!$event) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid signature']);
            return;
        }

        // The payload is only trusted for the intent id; everything else is
        // re-fetched from the API.
        $type   = (string)($event['type'] ?? '');
        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
        $retry  = false;

        if ($type === 'payment_intent.succeeded') {
            $piId = is_string($object['id'] ?? null) ? $object['id'] : '';
            if (preg_match('/^pi_[A-Za-z0-9_]+$/', $piId)) {
                $result = (new OrderPlacement($this->db))->place($piId, null);
                // Retry while the payment is neither an order nor refunded:
                // the Stripe API or the database was unreachable, or the
                // refund itself failed.
                $refunded = $result['refunded'] ?? null;
                $retry = $result['status'] === 'failed'
                    && ($refunded === false || ($refunded === null && ($result['http'] ?? 0) >= 500));
            }
        } elseif (in_array($type, ['charge.refunded', 'charge.dispute.created', 'charge.dispute.closed'], true)) {
            // Charges and disputes both carry the intent they belong to.
            $piId = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : '';
            if (preg_match('/^pi_[A-Za-z0-9_]+$/', $piId)) {
                try {
                    $retry = !(new OrderPlacement($this->db))->syncPaymentStatus($piId);
                } catch (\PDOException $e) {
                    error_log('payment status sync: database error: ' . $e->getMessage());
                    $retry = true;
                }
            }
        }

        if ($retry) {
            http_response_code(500);
            echo json_encode(['retry' => true]);
            return;
        }
        echo json_encode(['received' => true]);
    }
    
    public function cart(): void {
        // Read path: never mint a guest row just for viewing — crawlers hit
        // /cart constantly. No session user of either kind = empty cart.
        $userId = $this->effectiveUserId(false);
        [$cartItems, $cartTotal] = $userId ? $this->cartContents($userId) : [[], 0];
        $this->render('customer/cart', get_defined_vars());
    }

    /**
     * GET /checkout — the payment page. Everything is on one page: the
     * customer's details, how they collect, and the Stripe Payment Element,
     * with the order summary beside it. Nothing to pay for → back to the cart.
     */
    public function checkoutPage(): void {
        $userId = $this->effectiveUserId(false);
        [$cartItems, $cartTotal] = $userId ? $this->cartContents($userId) : [[], 0];
        if (!$cartItems) {
            header('Location: /cart');
            return;
        }

        $checkout     = $this->checkoutOptions();
        $accountEmail = $this->accountEmail();
        $accountPhone = null;
        if (Auth::check()) {
            $stmt = $this->db->prepare("SELECT phone FROM users WHERE id = ?");
            $stmt->execute([Auth::userId()]);
            $accountPhone = ((string)$stmt->fetchColumn()) ?: null;
        }
        $this->render('customer/checkout', get_defined_vars());
    }

    /**
     * The cart's lines as the cart and checkout pages show them — prices,
     * preview image, premade design overlay — and their total.
     *
     * @return array{0: array, 1: float}
     */
    private function cartContents(int $userId): array {
        $cartModel = new \Cart($this->db);
        $cartId = $cartModel->getOrCreateCartId($userId);
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

        // Featured products: the best sellers, by units actually ordered.
        //
        // The whole catalogue is fetched, not the top eight, because the home
        // page now expands the grid in place instead of sending people to
        // /shop. Eight are shown until the shopper asks for the rest. The 60
        // cap is a guard, not a feature: it is far above the current
        // fourteen, and if the catalogue ever approaches it this should
        // become a paged or lazily-loaded grid rather than a bigger number.
        // Cancelled orders don't count. Products without a mockup PNG are excluded
        // — the card is all image, so one without artwork is just a broken tile.
        // Ties (including everything at zero on a fresh install) fall back to
        // newest first, so the section is never empty.
        $stmt = $this->db->query("
            SELECT p.id, p.name, p.slug, p.base_price, p.image_path, p.active,
                   SUM(CASE WHEN o.status IS NOT NULL AND o.status <> 'cancelled'
                            THEN oi.quantity ELSE 0 END) AS ordered_qty
            FROM products p
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o       ON o.id = oi.order_id
            WHERE p.active = 1
              AND p.image_path IS NOT NULL
              AND p.image_path <> ''
            GROUP BY p.id, p.name, p.slug, p.base_price, p.image_path, p.active
            ORDER BY ordered_qty DESC, p.id DESC
            LIMIT 60
        ");
        $featuredProducts = $stmt->fetchAll();

        // products.base_price is the SUPPLIER cost of the blank garment, not a
        // customer price — printing it raw advertised a €2.14 t-shirt. Run it
        // through the pricing engine for the single-unit retail price (larger
        // orders drop into cheaper margin tiers).
        foreach ($featuredProducts as &$fp) {
            $fp['retail_price'] = Pricing::unitPrice(
                (float)$fp['base_price'],
                Pricing::categoryFor($fp['slug'] ?? '', $fp['name'] ?? ''),
                1
            );
        }
        unset($fp);

        // Headline bulk-discount figure for the home page.
        //
        // Computed, not typed. A per-product rate card was tried here and
        // pulled: the shop sells tees around EUR 13 and hoodies around EUR 37,
        // so one garment's ladder read as THE price list. A percentage is the
        // one number that is honest for the whole catalogue.
        //
        // The saving depends only on the margin bands, not on what the blank
        // costs: price = cost / (1 - margin), so the cost cancels out of
        // price(100) / price(1) and what is left is (1 - m1) / (1 - m100).
        // Taking the best category and rounding DOWN to a multiple of five
        // keeps the "up to" claim true even after the tier table is edited.
        $bulkSaving = 0.0;
        foreach (['tshirt', 'hoodie'] as $cat) {
            $m1   = Pricing::marginFor($cat, 1);
            $m100 = Pricing::marginFor($cat, 100);
            if ($m100 < 1.0) {
                $bulkSaving = max($bulkSaving, 1 - (1 - $m1) / (1 - $m100));
            }
        }
        $bulkSavingPct = (int)(floor($bulkSaving * 20) * 5);

        $this->render('customer/home', get_defined_vars());
    }

    public function shop(): void {
        // Shop landing page - choose between premade and custom
        $this->render('customer/shop_landing', get_defined_vars());
    }

    public function shopPremade(): void {
        // Show category sections (Anime, Coming Soon, etc.)
        $this->render('customer/shop', get_defined_vars());
    }

    public function shopAnime(): void {

        // Each card previews the design on its first associated product. That
        // link row is resolved once (dpf) so the image, price and PLACEMENT all
        // describe the same garment — placement is per product, so a position
        // taken from a different one would put the print in the wrong spot.
        $stmt = $this->db->prepare("
            SELECT d.*,
                   pf.image_path      AS product_image_path,
                   pf.back_image_path AS product_back_image_path,
                   pf.base_price      AS product_base_price,
                   pf.name            AS product_name,
                   dpf.design_pos_x         AS link_pos_x,
                   dpf.design_pos_y         AS link_pos_y,
                   dpf.design_pos_size      AS link_pos_size,
                   dpf.design_pos_back_x    AS link_pos_back_x,
                   dpf.design_pos_back_y    AS link_pos_back_y,
                   dpf.design_pos_back_size AS link_pos_back_size
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            LEFT JOIN design_products dpf ON dpf.id = (
                SELECT dp.id FROM design_products dp
                JOIN products p ON p.id = dp.product_id
                WHERE dp.design_id = d.id AND p.active = 1
                ORDER BY dp.id ASC LIMIT 1
            )
            LEFT JOIN products pf ON pf.id = dpf.product_id
            WHERE s.slug = 'anime' AND d.active = 1
            ORDER BY d.name
        ");
        $stmt->execute();
        $designs = $stmt->fetchAll();

        // Where that product has its own placement, it wins over the design's.
        foreach ($designs as &$d) {
            foreach (['x', 'y', 'size', 'back_x', 'back_y', 'back_size'] as $k) {
                if (isset($d["link_pos_$k"])) {
                    $d["design_pos_$k"] = $d["link_pos_$k"];
                }
            }
        }
        unset($d);

        $favoriteDesignIds = $this->favoriteIds('design');

        $this->render('customer/shop_anime', get_defined_vars());
    }
    // Removed duplicate declaration
    public function customProduct(): void {
        $id = $_SESSION['selected_product_id'] ?? null;
        if (!$id) {
            // Direct hit without picking a product first — send them to the
            // picker rather than a bare 400.
            header('Location: /shop/select_product');
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
            JOIN product_variants pv ON pv.color_id = ac.id AND pv.is_available = 1
            WHERE pv.product_id = ?
            GROUP BY ac.id
            ORDER BY ac.id
        ");
        $stmt->execute([$id]);
        $colors = $stmt->fetchAll();
        // Get all available sizes for this product — a size counts only while
        // it is offered and comes in at least one colour.
        $stmt = $this->db->prepare("
            SELECT ps.id, ps.size_name
            FROM product_sizes ps
            WHERE ps.product_id = ?
              AND ps.is_available = 1
              AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.size_id = ps.id AND pv.is_available = 1)
            ORDER BY ps.size_order
        ");
        $stmt->execute([$id]);
        $sizes = $stmt->fetchAll();
        // Build color-to-size matrix
        $stmt = $this->db->prepare("
            SELECT pv.color_id, pv.size_id
            FROM product_variants pv
            WHERE pv.product_id = ?
              AND pv.is_available = 1
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

        // Delivery estimate. Was a hard-coded "Mon, Feb 2" in the view, shown to
        // every customer forever. The shipping page states items print in 3–5
        // business days, so quote the far end of that window, skipping weekends.
        $deliveryEstimate = $this->businessDaysFromNow(5);

        // Single-unit retail price, for the sticky action bar on phones.
        // products.base_price is the SUPPLIER cost, so it has to go through
        // the pricing engine — rendering it raw would advertise a EUR 2.14
        // t-shirt. price-tiers.js takes over as soon as it has run; this is
        // what the bar shows on the first paint, so that it never flashes a
        // zero before the script catches up.
        $retailPrice = Pricing::unitPrice(
            (float)($product['base_price'] ?? 0),
            Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? ''),
            1
        );

        $this->render('customer/custom_product', get_defined_vars());
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
        // The placement columns come off the LINK row: the same design sits in a
        // different spot on a tee than on a hoodie. Fall back to the design's own
        // position for links the admin hasn't positioned yet.
        $stmt = $this->db->prepare("
            SELECT p.*,
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id AND is_available = 1) as size_count,
                   COALESCE(dp.design_pos_x,         d.design_pos_x,         0)  AS design_pos_x,
                   COALESCE(dp.design_pos_y,         d.design_pos_y,         0)  AS design_pos_y,
                   COALESCE(dp.design_pos_size,      d.design_pos_size,      55) AS design_pos_size,
                   COALESCE(dp.design_pos_back_x,    d.design_pos_back_x,    0)  AS design_pos_back_x,
                   COALESCE(dp.design_pos_back_y,    d.design_pos_back_y,    0)  AS design_pos_back_y,
                   COALESCE(dp.design_pos_back_size, d.design_pos_back_size, 55) AS design_pos_back_size
            FROM products p
            JOIN design_products dp ON p.id = dp.product_id
            JOIN premade_designs d  ON d.id = dp.design_id
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
                JOIN product_variants pv ON ps.id = pv.size_id AND pv.is_available = 1
                LEFT JOIN available_colors ac ON pv.color_id = ac.id
                WHERE ps.product_id = ? AND ps.is_available = 1
                GROUP BY ps.id
                ORDER BY ps.size_order
            ");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);

        // base_price is the SUPPLIER cost — attach the qty-1 retail price for
        // the initial render. The page's JS recomputes with Pricing.unitPrice
        // (pricing.js) as quantity/product change; this keeps the first paint
        // consistent with those later updates.
        foreach ($availableProducts as &$product) {
            $product['retail_price'] = Pricing::unitPrice(
                (float)$product['base_price'],
                Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? ''),
                1
            );
        }
        unset($product);

        $this->render('customer/view_design', get_defined_vars());
    }

public function shopCustom(): void {
        // Check if loading an existing design (requires login)
        $loadDesign = null;
        if (!empty($_GET['load']) && Auth::check()) {
            $designId = (int)$_GET['load'];
            $userId = Auth::userId();
            
            // Fetch the design (only if it belongs to the current user)
            // Carries the design's OWN product data — base price, slug and the
            // design-area boxes. The editor previously looked the product up in
            // productsData, which only lists active products that have artwork,
            // so opening a design whose product was later deactivated silently
            // failed and nothing rendered. Everything the editor needs now
            // travels with the design itself.
            $stmt = $this->db->prepare("
                SELECT cd.*, p.name as product_name, p.slug as product_slug,
                       p.base_price, p.size_chart_image,
                       p.image_path, p.back_image_path,
                       p.left_sleeve_image_path, p.right_sleeve_image_path,
                       p.active AS product_active,
                       p.da_front_x, p.da_front_y, p.da_front_w, p.da_front_h,
                       p.da_back_x,  p.da_back_y,  p.da_back_w,  p.da_back_h,
                       p.da_lsleeve_x, p.da_lsleeve_y, p.da_lsleeve_w, p.da_lsleeve_h,
                       p.da_rsleeve_x, p.da_rsleeve_y, p.da_rsleeve_w, p.da_rsleeve_h,
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
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id AND is_available = 1) as size_count
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
                JOIN product_variants pv ON ps.id = pv.size_id AND pv.is_available = 1
                LEFT JOIN available_colors ac ON pv.color_id = ac.id
                WHERE ps.product_id = ? AND ps.is_available = 1
                GROUP BY ps.id
                ORDER BY ps.size_order
            ");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);

        $this->render('customer/shop_custom', get_defined_vars());
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
                   COALESCE(ps.price_modifier, 0) AS price_modifier,
                   COALESCE(pv.unit_price, p.base_price + COALESCE(ps.price_modifier, 0)) AS supplier_cost
            FROM product_variants pv
            JOIN products p               ON p.id = pv.product_id
            LEFT JOIN available_colors ac ON ac.id = pv.color_id
            LEFT JOIN product_sizes   ps ON ps.id = pv.size_id
            WHERE pv.product_id = ?
              AND pv.is_available = 1
              AND ps.is_available = 1
              AND ac.color_hex IS NOT NULL
              AND ps.size_name IS NOT NULL
            ORDER BY ps.size_order, ac.id
        ");
        $stmt->execute([$id]);
        $variants = $stmt->fetchAll();

        // Attach customer-facing prices. supplier_cost mirrors the exact
        // expression resolveSupplierCost() uses at add-to-cart, so the price
        // shown per size equals the price charged for qty 1.
        $category = Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? '');
        $product['retail_price'] = Pricing::unitPrice((float)$product['base_price'], $category, 1);
        foreach ($variants as &$v) {
            $v['retail_price'] = Pricing::unitPrice((float)$v['supplier_cost'], $category, 1);
        }
        unset($v);

        $this->render('customer/product', get_defined_vars());
    }

    public function about(): void {
        $this->render('customer/about', get_defined_vars());
    }

    public function contact(): void {
        $this->render('customer/contact', get_defined_vars());
    }

    public function contactSubmit(): void {
        $name    = trim((string)($_POST['name']    ?? ''));
        $email   = trim((string)($_POST['email']   ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        // A filled honeypot is a bot. It gets the success message so it has
        // no reason to try again, and nothing is sent.
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            $_SESSION['flash_success'] = I18n::t('contact.success');
            header('Location: /contact');
            return;
        }

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

        if ($this->isContactRateLimited()) {
            $_SESSION['flash_error'] = I18n::t('contact.error_rate');
            header('Location: /contact');
            return;
        }

        $name    = mb_substr($name, 0, 100);
        $subject = mb_substr($subject, 0, 150);
        $message = mb_substr($message, 0, 5000);

        // CONTACT_EMAIL is the inbox someone reads; MAIL_FROM_ADDRESS is the
        // no-reply sender and only a last resort. Reply-To is the customer, so
        // answering the message answers them.
        $toAddress = Env::get('CONTACT_EMAIL', '') ?: Env::get('MAIL_FROM_ADDRESS', 'no-reply@costaspressjr.com');
        $htmlBody  = '<p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>'
                   . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
                   . '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>'
                   . '<p><strong>Message:</strong><br>' . nl2br(htmlspecialchars($message)) . '</p>';

        if (!Mailer::send($toAddress, '[Contact] ' . $subject, $htmlBody, '', $email)) {
            $_SESSION['flash_error'] = I18n::t('contact.error_send');
            header('Location: /contact');
            return;
        }

        $_SESSION['flash_success'] = I18n::t('contact.success');
        header('Location: /contact');
    }

    /**
     * At most 5 contact messages per visitor per 15 minutes. Shares the
     * login_attempts table (and its hashed-IP scheme) with AuthController's
     * throttles under a "contact" identifier; old rows are pruned there.
     */
    private function isContactRateLimited(): bool {
        $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|costaspressjr');
        try {
            // created_at is MySQL's clock, so the window is measured on it too.
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND identifier = 'contact' AND created_at > NOW() - INTERVAL 15 MINUTE");
            $stmt->execute([$ipHash]);
            if ((int)$stmt->fetchColumn() >= 5) {
                return true;
            }
            $this->db->prepare("INSERT INTO login_attempts (ip_hash, identifier) VALUES (?, 'contact')")->execute([$ipHash]);
        } catch (PDOException $e) {
            error_log('Contact rate-limit check failed: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Render a static informational page from views/customer/info/{slug}.php.
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

        $this->render('customer/info/' . basename($allowed[$slug], '.php'), get_defined_vars());
    }

    /**
     * GET /track-order[?code=CP-XXXX...] — public order status lookup by
     * tracking number. Works for guests and accounts alike; the token itself
     * (12 chars, ~59 random bits) is the capability, so no login and no email
     * cross-check is needed. Only status-level info is shown — never the
     * shipping address or contact details.
     */
    public function trackOrder(): void {
        $trackQuery  = trim((string)($_GET['code'] ?? ''));
        $trackResult = null;

        if ($trackQuery !== '') {
            // Accept "cp-abcd-..." style input: strip separators, uppercase.
            $code = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $trackQuery));
            $order = null;
            if (strlen($code) >= 8 && strlen($code) <= 16) {
                $stmt = $this->db->prepare("
                    SELECT id, status, tracking_token, total_price, total_products, created_at, updated_at
                    FROM orders
                    WHERE tracking_token = ?
                    LIMIT 1
                ");
                $stmt->execute([$code]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($order) {
                    $itemsStmt = $this->db->prepare("
                        SELECT oi.quantity, oi.size_name, oi.color_name, p.name AS product_name
                        FROM order_items oi
                        LEFT JOIN products p ON p.id = oi.product_id
                        WHERE oi.order_id = ?
                        ORDER BY oi.id
                    ");
                    $itemsStmt->execute([(int)$order['id']]);
                    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            $trackResult = ['query' => $trackQuery, 'order' => $order];
        }

        $this->render('customer/info/track_order', get_defined_vars());
    }

    /**
     * Shop assistant endpoint.
     *
     * Public on purpose: most of these questions ("how much for 50 shirts?",
     * "do you deliver?") are asked BEFORE someone has an account, and putting
     * them behind a login would mean the bot only ever talks to people who
     * are already customers.
     *
     * Public also means it needs its own limits, since nothing upstream is
     * rate-limiting an anonymous visitor: a CSRF token ties the request to a
     * real session, the question is length-capped, and a per-session counter
     * caps the rate. Answering costs nothing here, but the endpoint should
     * still not be usable as a free amplifier, and the same limits keep the
     * cost bounded if this is ever pointed at a paid language model.
     */
    public function assistantAsk(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $raw  = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }

        // Csrf::tokenFromRequest() would re-read php://input, which has
        // already been consumed above, so the token is taken from the header
        // (or the parsed body) and handed to Csrf::check() directly.
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? '');
        if (!Csrf::check(is_string($token) ? $token : '')) {
            http_response_code(419);
            echo json_encode(['error' => 'csrf']);
            return;
        }

        // A ROLLING WINDOW, not a lifetime counter.
        //
        // This was a running total that never reset, so the 30th question
        // locked a visitor out of the assistant for the rest of their session
        // with no way back — including anyone who was simply curious and
        // clicked a few suggested questions. A window recovers on its own:
        // ask a lot, wait a few minutes, carry on.
        //
        // The cap is deliberately generous because answering is nearly free:
        // the matcher handles most questions on this server at no cost, and
        // the model calls behind it have their own, tighter limit in
        // ShopAssistant. This one exists only to stop automated abuse.
        $now    = time();
        $window = 600;   // 10 minutes
        $hits   = $_SESSION['assistant_hits'] ?? [];
        // Previously an int; drop any old value rather than crash on it.
        if (!is_array($hits)) {
            $hits = [];
        }
        $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - $window));
        $hits[] = $now;
        $_SESSION['assistant_hits'] = $hits;

        if (count($hits) > 40) {
            http_response_code(429);
            header('Retry-After: ' . $window);
            echo json_encode([
                'intent' => 'rate_limited',
                'text'   => t('assistant.rate_limited', false),
                'links'  => [['label' => t('assistant.link.contact', false), 'href' => '/contact']],
                'suggestions' => [],
            ]);
            return;
        }

        $question = (string)($body['q'] ?? '');
        // Cut rather than reject: someone pasting a long order description
        // should still get an answer, not an error.
        if (mb_strlen($question) > 500) {
            $question = mb_substr($question, 0, 500);
        }

        $assistant = new ShopAssistant($this->db, I18n::locale());
        echo json_encode($assistant->answer($question), JSON_UNESCAPED_UNICODE);
    }

    public function cookieConsent(): void {
        Auth::requireLogin();
        $userId = Auth::userId();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
            $val = ($_POST['accept'] == '1') ? 1 : 2;
            $stmt = $this->db->prepare("UPDATE users SET cookie_accepted = ? WHERE id = ?");
            $stmt->execute([$val, $userId]);
            // Mirror the choice into the session. The footer needs this value
            // on EVERY page, and most controller actions never load the user
            // row, so reading it from $user alone meant the banner reappeared
            // on every page that happened not to fetch it.
            $_SESSION['cookie_accepted'] = $val;
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
            SELECT p.*, (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id AND is_available = 1) as size_count
              FROM products p
             WHERE p.active = 1
               AND p.image_path IS NOT NULL
               AND p.image_path <> ''
             ORDER BY p.name");
        $products = $stmt->fetchAll();
        // Get sizes and colors for each product
        foreach ($products as &$product) {
            $stmt = $this->db->prepare("SELECT ps.*, GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names, GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes, GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids FROM product_sizes ps JOIN product_variants pv ON ps.id = pv.size_id AND pv.is_available = 1 LEFT JOIN available_colors ac ON pv.color_id = ac.id WHERE ps.product_id = ? AND ps.is_available = 1 GROUP BY ps.id ORDER BY ps.size_order");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);
        $favoriteProductIds = $this->favoriteIds('product');

        $this->render('customer/shop_select_product', get_defined_vars());
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

        // Get the sizes this product is offered in (and that come in a colour)
        $stmt = $this->db->prepare("
            SELECT ps.id, ps.size_name as name, ps.size_order
            FROM product_sizes ps
            WHERE ps.product_id = ?
              AND ps.is_available = 1
              AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.size_id = ps.id AND pv.is_available = 1)
            ORDER BY ps.size_order
        ");
        $stmt->execute([$productId]);
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all colors available for this product
        $stmt = $this->db->prepare("
            SELECT DISTINCT ac.id, ac.color_name as name, ac.color_hex as hex
            FROM available_colors ac
            INNER JOIN product_variants pv ON ac.id = pv.color_id AND pv.is_available = 1
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
