<?php

class AdminController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private function requireAdmin(): void {
        Auth::requireAdmin();
    }

    public function dashboard(): void {
        $this->requireAdmin();

        $userCount = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $productCount = $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $orderCount = $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

        require __DIR__ . '/../views/admin/dashboard.php';
    }

    public function users(): void {
        $this->requireAdmin();

        $users = $this->db->query("SELECT id, username, email, phone, role, created_at FROM users ORDER BY id DESC")->fetchAll();

        require __DIR__ . '/../views/admin/users.php';
    }

    // ==================== PRODUCTS ====================

    public function products(): void {
        $this->requireAdmin();

        $products = $this->db->query("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id) as size_count,
                   (SELECT COUNT(*) FROM product_colors WHERE product_id = p.id AND is_available = 1) as color_count
            FROM products p 
            ORDER BY p.id ASC
        ")->fetchAll();

        require __DIR__ . '/../views/admin/products.php';
    }

    public function showAddProduct(): void {
        $this->requireAdmin();

        $colors = $this->db->query("SELECT id, color_name as name, color_hex as hex_code FROM available_colors WHERE is_active = 1 ORDER BY color_name")->fetchAll();

        require __DIR__ . '/../views/admin/products_add.php';
    }

    public function addProduct(): void {
        $this->requireAdmin();

        // Create slug from name
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name']));
        $slug = trim($slug, '-');

        // Insert product
        $stmt = $this->db->prepare("INSERT INTO products (name, slug, description, base_price, active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([
            $_POST['name'],
            $slug,
            $_POST['description'] ?? null,
            $_POST['base_price']
        ]);
        
        $productId = $this->db->lastInsertId();
        
        // Handle image upload
        $this->handleProductImageUpload($productId);
        
        // Handle sizes and their colors (variants)
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            $sizeStmt = $this->db->prepare("INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier, is_available) VALUES (?, ?, ?, ?, 1)");
            $variantStmt = $this->db->prepare("INSERT INTO product_variants (product_id, size_id, color_id, is_available) VALUES (?, ?, ?, 1)");
            $colorStmt = $this->db->prepare("INSERT IGNORE INTO product_colors (product_id, color_id, is_available) VALUES (?, ?, 1)");
            
            $order = 1;
            foreach ($_POST['sizes'] as $size) {
                if (empty($size['name'])) continue;
                
                // Insert size
                $sizeStmt->execute([
                    $productId,
                    $size['name'],
                    $order++,
                    $size['price_modifier'] ?? 0
                ]);
                $sizeId = $this->db->lastInsertId();
                
                // Insert variants (size + color combinations)
                if (!empty($size['colors'])) {
                    $colorIds = array_filter(explode(',', $size['colors']));
                    foreach ($colorIds as $colorId) {
                        $variantStmt->execute([$productId, $sizeId, $colorId]);
                        // Also track in product_colors
                        $colorStmt->execute([$productId, $colorId]);
                    }
                }
            }
        }

        header('Location: /admin/products');
    }

    public function showEditProduct(): void {
        $this->requireAdmin();

        $productId = $this->getIdFromUrl();
        
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            header('Location: /admin/products');
            exit;
        }

        // Get product sizes
        $stmt = $this->db->prepare("SELECT * FROM product_sizes WHERE product_id = ? ORDER BY size_order");
        $stmt->execute([$productId]);
        $sizes = $stmt->fetchAll();

        // Get all available colors for the modal
        $colors = $this->db->query("SELECT id, color_name as name, color_hex as hex_code FROM available_colors WHERE is_active = 1 ORDER BY color_name")->fetchAll();

        // Get existing variants (size+color combinations)
        $stmt = $this->db->prepare("SELECT * FROM product_variants WHERE product_id = ?");
        $stmt->execute([$productId]);
        $variants = $stmt->fetchAll();

        require __DIR__ . '/../views/admin/products_edit.php';
    }

    public function updateProduct(): void {
        $this->requireAdmin();

        $productId = $this->getIdFromUrl();

        // Update slug if name changed
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name']));
        $slug = trim($slug, '-');

        // Update product
        try {
            $stmt = $this->db->prepare("UPDATE products SET name = ?, slug = ?, description = ?, base_price = ?, active = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $slug,
                $_POST['description'] ?? null,
                $_POST['base_price'],
                isset($_POST['is_active']) ? 1 : 0,
                $productId
            ]);
        } catch (PDOException $e) {
            error_log("updateProduct SQL error (products): " . $e->getMessage());
        }

        // Handle front image removal
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            $this->removeProductImage($productId);
        } else {
            $this->handleProductImageUpload($productId);
        }

        // Handle back image removal
        if (isset($_POST['remove_back_image']) && $_POST['remove_back_image'] === '1') {
            $this->removeProductBackImage($productId);
        } else {
            $this->handleBackImageUpload($productId);
        }

        // Handle left sleeve image removal
        if (isset($_POST['remove_left_sleeve_image']) && $_POST['remove_left_sleeve_image'] === '1') {
            $this->removeProductSleeveImage($productId, 'left');
        } else {
            $this->handleSleeveImageUpload($productId, 'left');
        }

        // Handle right sleeve image removal
        if (isset($_POST['remove_right_sleeve_image']) && $_POST['remove_right_sleeve_image'] === '1') {
            $this->removeProductSleeveImage($productId, 'right');
        } else {
            $this->handleSleeveImageUpload($productId, 'right');
        }


        // Only delete variants and color links, not sizes
        try {
            $this->db->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$productId]);
            $this->db->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$productId]);
        } catch (PDOException $e) {
            error_log("updateProduct SQL error (delete variants/colors): " . $e->getMessage());
        }

        // Add/update color variants for existing sizes
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            try {
                // Prepare update and insert for sizes
                $updateSizeStmt = $this->db->prepare("UPDATE product_sizes SET size_order = ?, price_modifier = ?, is_available = 1 WHERE id = ? AND product_id = ?");
                $insertSizeStmt = $this->db->prepare("INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier, is_available) VALUES (?, ?, ?, ?, 1)");
                $variantStmt = $this->db->prepare("INSERT INTO product_variants (product_id, size_id, color_id, is_available) VALUES (?, ?, ?, 1)");
                $colorStmt = $this->db->prepare("INSERT IGNORE INTO product_colors (product_id, color_id, is_available) VALUES (?, ?, 1)");

                $order = 1;
                foreach ($_POST['sizes'] as $size) {
                    if (empty($size['name'])) continue;

                    $sizeId = null;
                    if (!empty($size['id'])) {
                        // Try to update existing size — only claim the id if a row actually matched
                        try {
                            $updateSizeStmt->execute([
                                $order,
                                $size['price_modifier'] ?? 0,
                                $size['id'],
                                $productId
                            ]);
                            if ($updateSizeStmt->rowCount() > 0) {
                                $sizeId = (int)$size['id'];
                            } else {
                                // Either name+order didn't change (rowCount=0 on no-op) or the row
                                // doesn't belong to this product. Verify existence before trusting it.
                                $check = $this->db->prepare("SELECT id FROM product_sizes WHERE id = ? AND product_id = ?");
                                $check->execute([$size['id'], $productId]);
                                if ($check->fetchColumn()) {
                                    $sizeId = (int)$size['id'];
                                }
                            }
                        } catch (PDOException $e) {
                            error_log("updateProduct SQL error (update size): " . $e->getMessage());
                        }
                    }
                    if (!$sizeId) {
                        // Insert new size if not found
                        try {
                            $insertSizeStmt->execute([
                                $productId,
                                $size['name'],
                                $order,
                                $size['price_modifier'] ?? 0
                            ]);
                            $sizeId = $this->db->lastInsertId();
                        } catch (PDOException $e) {
                            error_log("updateProduct SQL error (insert size): " . $e->getMessage());
                            continue;
                        }
                    }
                    $order++;

                    // Insert variants (size + color combinations).
                    // $size['colors'] is a hidden CSV like "3,7,12". Coerce each to int and
                    // drop blanks/zeros so we never call the variant insert with an empty FK.
                    if (!empty($size['colors'])) {
                        $colorIds = [];
                        foreach (explode(',', (string)$size['colors']) as $raw) {
                            $cid = (int)trim($raw);
                            if ($cid > 0) $colorIds[] = $cid;
                        }
                        $colorIds = array_unique($colorIds);
                        foreach ($colorIds as $colorId) {
                            try {
                                $variantStmt->execute([$productId, (int)$sizeId, $colorId]);
                                $colorStmt->execute([$productId, $colorId]);
                            } catch (PDOException $e) {
                                error_log("updateProduct variant insert failed (product=$productId size=$sizeId color=$colorId): " . $e->getMessage());
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("updateProduct SQL error (prepare/insert): " . $e->getMessage());
            }
        }

        header('Location: /admin/products');
    }

    public function deleteProduct(): void {
        $this->requireAdmin();

        $productId = $this->getIdFromUrl();
        
        // Delete product (sizes and colors will cascade delete)
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        // Delete all product images
        $uploadDir = __DIR__ . '/../../public/images/products/';
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $suffixes = ['', '_back', '_left_sleeve', '_right_sleeve'];
        
        foreach ($suffixes as $suffix) {
            foreach ($extensions as $ext) {
                $file = $uploadDir . $productId . $suffix . '.' . $ext;
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        header('Location: /admin/products');
    }

    // ==================== COLORS ====================

    public function colors(): void {
        $this->requireAdmin();

        $colors = $this->db->query("SELECT * FROM available_colors ORDER BY color_name")->fetchAll();

        require __DIR__ . '/../views/admin/colors.php';
    }

    public function addColor(): void {
        $this->requireAdmin();

        $stmt = $this->db->prepare("INSERT INTO available_colors (color_name, color_hex, is_active) VALUES (?, ?, 1)");
        $stmt->execute([
            $_POST['color_name'],
            $_POST['color_hex']
        ]);

        header('Location: /admin/colors');
    }

    public function deleteColor(): void {
        $this->requireAdmin();

        $colorId = $this->getIdFromUrl();
        
        $stmt = $this->db->prepare("DELETE FROM available_colors WHERE id = ?");
        $stmt->execute([$colorId]);

        header('Location: /admin/colors');
    }

    // ==================== ORDERS ====================

    public function orders(): void {
        $this->requireAdmin();

        $orders = $this->db->query("
            SELECT o.id, o.status, o.total_price, o.total_products, o.created_at,
                   u.username, u.email,
                   op.card_brand, op.card_last4, op.status as payment_status
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN order_payments op ON op.order_id = o.id
            ORDER BY o.id DESC
        ")->fetchAll();

        require __DIR__ . '/../views/admin/orders.php';
    }

    public function orderDetail(): void {
        $this->requireAdmin();
        $orderId = $this->getIdFromUrl();

        // Order + customer info
        $stmt = $this->db->prepare("
            SELECT o.*, u.username, u.email, u.phone
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            http_response_code(404);
            echo '404 - Order not found';
            return;
        }

        // Payment info
        $stmt = $this->db->prepare("SELECT * FROM order_payments WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch();

        // Order items with product info (including all product view images)
        $stmt = $this->db->prepare("
            SELECT oi.*, 
                   p.name as product_name, 
                   p.image_path as product_image,
                   p.back_image_path as product_back_image,
                   p.left_sleeve_image_path as product_left_sleeve_image,
                   p.right_sleeve_image_path as product_right_sleeve_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll();

        // For each item, fetch uploads, designs, texts, and preview images
        foreach ($orderItems as &$item) {
            // Uploads
            $stmt = $this->db->prepare("SELECT * FROM order_item_uploads WHERE order_item_id = ? ORDER BY id");
            $stmt->execute([$item['id']]);
            $item['uploads'] = $stmt->fetchAll();

            // Design data
            $stmt = $this->db->prepare("SELECT * FROM order_item_designs WHERE order_item_id = ?");
            $stmt->execute([$item['id']]);
            $item['design'] = $stmt->fetch();

            // Text elements
            $stmt = $this->db->prepare("SELECT * FROM order_item_texts WHERE order_item_id = ? ORDER BY id");
            $stmt->execute([$item['id']]);
            $item['texts'] = $stmt->fetchAll();
            
            // Parse preview_images JSON from order_items
            $item['parsed_previews'] = [];
            if (!empty($item['preview_images'])) {
                $decoded = json_decode($item['preview_images'], true);
                if (is_array($decoded)) {
                    $item['parsed_previews'] = $decoded;
                }
            }

            // Fallback: if no previews on order_item but design_id exists, try fetching from custom_designs
            if (empty($item['parsed_previews']) && !empty($item['design_id'])) {
                $stmt = $this->db->prepare("SELECT preview_images FROM custom_designs WHERE id = ?");
                $stmt->execute([$item['design_id']]);
                $designRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($designRow && !empty($designRow['preview_images'])) {
                    $decoded = json_decode($designRow['preview_images'], true);
                    if (is_array($decoded)) {
                        $item['parsed_previews'] = $decoded;
                    }
                }
            }

            // Decode design_data (stored in order_item_designs) and surface
            // premade-design fields so the admin view can render a preview
            // of premade items the same way the cart does.
            $item['premade_design_id']    = null;
            $item['premade_design_name']  = null;
            $item['premade_design_image'] = null;
            $item['premade_pos_x']        = 0.0;
            $item['premade_pos_y']        = 0.0;
            $item['premade_pos_size']     = 55.0;
            if (!empty($item['design']) && !empty($item['design']['design_data'])) {
                $dd = json_decode($item['design']['design_data'], true);
                if (is_array($dd) && isset($dd['type']) && $dd['type'] === 'premade') {
                    $item['premade_design_id']    = $dd['premade_design_id'] ?? null;
                    $item['premade_design_name']  = $dd['premade_design_name'] ?? null;
                    $item['premade_design_image'] = $dd['premade_design_image'] ?? null;
                    $item['premade_pos_x']        = (float)($dd['pos_x']    ?? 0);
                    $item['premade_pos_y']        = (float)($dd['pos_y']    ?? 0);
                    $item['premade_pos_size']     = (float)($dd['pos_size'] ?? 55);
                }
            }
            // If we know the premade design id but the image path wasn't in
            // the snapshot (older orders), pull it from the premade_designs table.
            if (!empty($item['premade_design_id']) && empty($item['premade_design_image'])) {
                $pStmt = $this->db->prepare("SELECT name, image_path FROM premade_designs WHERE id = ?");
                $pStmt->execute([$item['premade_design_id']]);
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($pRow) {
                    if (empty($item['premade_design_name'])) $item['premade_design_name'] = $pRow['name'] ?? null;
                    $item['premade_design_image'] = $pRow['image_path'] ?? null;
                }
            }
        }
        unset($item);

        require __DIR__ . '/../views/admin/order_detail.php';
    }

    public function updateOrderStatus(): void {
        $this->requireAdmin();
        $orderId = $this->getIdFromUrl();

        $newStatus = $_POST['status'] ?? '';
        $allowed = ['pending', 'processing', 'in-transit', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $allowed)) {
            header('Location: /admin/orders/' . $orderId);
            return;
        }

        $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        header('Location: /admin/orders/' . $orderId);
    }

    // ==================== TOOLS ====================

    public function backgroundRemover(): void {
        $this->requireAdmin();
        require __DIR__ . '/../views/admin/background_remover.php';
    }

    public function imageCropper(): void {
        $this->requireAdmin();
        require __DIR__ . '/../views/admin/image_cropper.php';
    }

    public function designAreaEditor(): void {
        $this->requireAdmin();
        $stmt = $this->db->query("
            SELECT id, name, image_path, back_image_path, left_sleeve_image_path, right_sleeve_image_path,
                   da_front_x, da_front_y, da_front_w, da_front_h,
                   da_back_x,  da_back_y,  da_back_w,  da_back_h,
                   da_lsleeve_x, da_lsleeve_y, da_lsleeve_w, da_lsleeve_h,
                   da_rsleeve_x, da_rsleeve_y, da_rsleeve_w, da_rsleeve_h
            FROM products WHERE active = 1 ORDER BY name
        ");
        $products = $stmt->fetchAll();
        require __DIR__ . '/../views/admin/design_area_editor.php';
    }

    public function saveDesignArea(): void {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['product_id']) || empty($data['areas'])) {
            echo json_encode(['success' => false, 'error' => 'Missing data']);
            return;
        }

        $id = (int)$data['product_id'];
        $a  = $data['areas'];

        $stmt = $this->db->prepare("
            UPDATE products SET
                da_front_x=:fx,   da_front_y=:fy,   da_front_w=:fw,   da_front_h=:fh,
                da_back_x=:bx,    da_back_y=:by,    da_back_w=:bw,    da_back_h=:bh,
                da_lsleeve_x=:lx, da_lsleeve_y=:ly, da_lsleeve_w=:lw, da_lsleeve_h=:lh,
                da_rsleeve_x=:rx, da_rsleeve_y=:ry, da_rsleeve_w=:rw, da_rsleeve_h=:rh
            WHERE id = :id
        ");
        $stmt->execute([
            ':fx' => $a['front']['x'],   ':fy' => $a['front']['y'],   ':fw' => $a['front']['w'],   ':fh' => $a['front']['h'],
            ':bx' => $a['back']['x'],    ':by' => $a['back']['y'],    ':bw' => $a['back']['w'],    ':bh' => $a['back']['h'],
            ':lx' => $a['lsleeve']['x'], ':ly' => $a['lsleeve']['y'], ':lw' => $a['lsleeve']['w'], ':lh' => $a['lsleeve']['h'],
            ':rx' => $a['rsleeve']['x'], ':ry' => $a['rsleeve']['y'], ':rw' => $a['rsleeve']['w'], ':rh' => $a['rsleeve']['h'],
            ':id' => $id,
        ]);

        echo json_encode(['success' => true]);
    }

    // ==================== HELPER METHODS ====================

    private function getIdFromUrl(): int {
        $uri = $_SERVER['REQUEST_URI'];
        $parts = explode('/', trim($uri, '/'));
        return (int) end($parts);
    }

    private function handleProductImageUpload(int $productId): void {
        $fileField = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileField = 'image';
        } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileField = 'product_image';
        }
        if (!$fileField) return;

        $publicRoot = __DIR__ . '/../../public';
        $result = Upload::saveImage(
            $_FILES[$fileField],
            $publicRoot . '/images/products/',
            (string)$productId,
            Upload::DEFAULT_MAX_BYTES,
            $publicRoot
        );

        if (isset($result['error'])) {
            error_log("Product image upload rejected for product $productId: " . $result['error']);
            return;
        }

        $stmt = $this->db->prepare("UPDATE products SET image_path = ? WHERE id = ?");
        $stmt->execute([$result['path'], $productId]);
    }

    private function removeProductImage(int $productId): void {
        $uploadDir = __DIR__ . '/../../public/images/products/';
        
        // Delete any existing images for this product
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $uploadDir . $productId . '.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Clear image_path in database
        $stmt = $this->db->prepare("UPDATE products SET image_path = NULL WHERE id = ?");
        $stmt->execute([$productId]);
    }

    private function handleBackImageUpload(int $productId): void {
        if (!isset($_FILES['back_image']) || $_FILES['back_image']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $publicRoot = __DIR__ . '/../../public';
        $result = Upload::saveImage(
            $_FILES['back_image'],
            $publicRoot . '/images/products/',
            $productId . '_back',
            Upload::DEFAULT_MAX_BYTES,
            $publicRoot
        );

        if (isset($result['error'])) {
            error_log("Back image upload rejected for product $productId: " . $result['error']);
            return;
        }

        $stmt = $this->db->prepare("UPDATE products SET back_image_path = ? WHERE id = ?");
        $stmt->execute([$result['path'], $productId]);
    }

    private function removeProductBackImage(int $productId): void {
        $uploadDir = __DIR__ . '/../../public/images/products/';
        
        // Delete any existing back images for this product
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $uploadDir . $productId . '_back.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Clear back_image_path in database
        $stmt = $this->db->prepare("UPDATE products SET back_image_path = NULL WHERE id = ?");
        $stmt->execute([$productId]);
    }

    private function handleSleeveImageUpload(int $productId, string $side): void {
        if (!in_array($side, ['left', 'right'], true)) return;
        $fieldName = $side . '_sleeve_image';
        $dbColumn = $side . '_sleeve_image_path';

        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $publicRoot = __DIR__ . '/../../public';
        $result = Upload::saveImage(
            $_FILES[$fieldName],
            $publicRoot . '/images/products/',
            $productId . '_' . $side . '_sleeve',
            Upload::DEFAULT_MAX_BYTES,
            $publicRoot
        );

        if (isset($result['error'])) {
            error_log("Sleeve image upload rejected for product $productId ($side): " . $result['error']);
            return;
        }

        $stmt = $this->db->prepare("UPDATE products SET $dbColumn = ? WHERE id = ?");
        $stmt->execute([$result['path'], $productId]);
    }

    private function removeProductSleeveImage(int $productId, string $side): void {
        $uploadDir = __DIR__ . '/../../public/images/products/';
        $dbColumn = $side . '_sleeve_image_path';
        
        // Delete any existing sleeve images for this product
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $uploadDir . $productId . '_' . $side . '_sleeve.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Clear sleeve image path in database
        $stmt = $this->db->prepare("UPDATE products SET $dbColumn = NULL WHERE id = ?");
        $stmt->execute([$productId]);
    }

    // ==================== API ENDPOINTS ====================

    public function apiGetColors(): void {
        $this->requireAdmin();
        
        header('Content-Type: application/json');
        
        $colors = $this->db->query("SELECT id, color_name as name, color_hex as hex_code FROM available_colors WHERE is_active = 1 ORDER BY color_name")->fetchAll();
        
        echo json_encode($colors);
    }

    public function apiGetProduct(): void {
        $this->requireAdmin();
        
        header('Content-Type: application/json');
        
        try {
            $productId = $this->getIdFromUrl();
            
            // Get product
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
                return;
            }
            
            // Check if front image exists
            $product['has_image'] = false;
            if (!empty($product['image_path'])) {
                $imagePath = __DIR__ . '/../../public/' . $product['image_path'];
                $product['has_image'] = file_exists($imagePath);
            }
            
            // Check if back image exists
            $product['has_back_image'] = false;
            if (!empty($product['back_image_path'])) {
                $backImagePath = __DIR__ . '/../../public/' . $product['back_image_path'];
                $product['has_back_image'] = file_exists($backImagePath);
            }
            
            // Check if left sleeve image exists
            $product['has_left_sleeve_image'] = false;
            if (!empty($product['left_sleeve_image_path'])) {
                $leftSleevePath = __DIR__ . '/../../public/' . $product['left_sleeve_image_path'];
                $product['has_left_sleeve_image'] = file_exists($leftSleevePath);
            }
            
            // Check if right sleeve image exists
            $product['has_right_sleeve_image'] = false;
            if (!empty($product['right_sleeve_image_path'])) {
                $rightSleevePath = __DIR__ . '/../../public/' . $product['right_sleeve_image_path'];
                $product['has_right_sleeve_image'] = file_exists($rightSleevePath);
            }
            
            // Get sizes
            $stmt = $this->db->prepare("SELECT * FROM product_sizes WHERE product_id = ? ORDER BY size_order");
            $stmt->execute([$productId]);
            $sizes = $stmt->fetchAll();
            
            // Get variants (if table exists)
            $variants = [];
            try {
                $stmt = $this->db->prepare("SELECT * FROM product_variants WHERE product_id = ?");
                $stmt->execute([$productId]);
                $variants = $stmt->fetchAll();
            } catch (PDOException $e) {
                // Table might not exist yet
                $variants = [];
            }
            
            echo json_encode([
                'product' => $product,
                'sizes' => $sizes,
                'variants' => $variants
            ]);
        } catch (Exception $e) {
            error_log('apiGetProduct error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Could not load product.']);
        }
    }

    // ==================== PREMADE DESIGNS ====================

    public function premadeDesigns(): void {
        $this->requireAdmin();

        // Get all sections
        $sections = $this->db->query("SELECT * FROM design_sections ORDER BY name")->fetchAll();

        // Get all products for the checkbox list
        $products = $this->db->query("SELECT id, name, base_price, image_path FROM products WHERE active = 1 ORDER BY name")->fetchAll();

        // Get all designs with section names and product count
        $designs = $this->db->query("
            SELECT d.*, s.name as section_name, s.slug as section_slug,
                   (SELECT COUNT(*) FROM design_products WHERE design_id = d.id) as product_count
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            ORDER BY s.name, d.name
        ")->fetchAll();

        require __DIR__ . '/../views/admin/premade_designs.php';
    }

    public function addPremadeDesign(): void {
        $this->requireAdmin();

        $stmt = $this->db->prepare("INSERT INTO premade_designs (section_id, name, description, price, active, is_fixed) VALUES (?, ?, ?, ?, 1, 1)");
        $stmt->execute([
            $_POST['section_id'],
            $_POST['name'],
            $_POST['description'] ?? null,
            $_POST['price'],
        ]);

        $designId = $this->db->lastInsertId();

        // Handle image upload
        $this->handleDesignImageUpload($designId);

        // Handle product associations
        $this->saveDesignProducts($designId);

        header('Location: /admin/premade');
    }

    public function updatePremadeDesign(): void {
        $this->requireAdmin();

        $designId = $this->getIdFromUrl();

        // Handle image removal
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            $this->removeDesignImage($designId);
        } else {
            // Handle image upload (if new image provided)
            $this->handleDesignImageUpload($designId);
        }

        $stmt = $this->db->prepare("UPDATE premade_designs SET section_id = ?, name = ?, description = ?, price = ?, active = ?, is_fixed = 1 WHERE id = ?");
        $stmt->execute([
            $_POST['section_id'],
            $_POST['name'],
            $_POST['description'] ?? null,
            $_POST['price'],
            isset($_POST['is_active']) ? 1 : 0,
            $designId
        ]);

        // Handle product associations
        $this->saveDesignProducts($designId);

        header('Location: /admin/premade');
    }

    private function saveDesignProducts(int $designId): void {
        // Delete existing associations
        $stmt = $this->db->prepare("DELETE FROM design_products WHERE design_id = ?");
        $stmt->execute([$designId]);

        // Add new associations
        if (!empty($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            $stmt = $this->db->prepare("INSERT INTO design_products (design_id, product_id) VALUES (?, ?)");
            foreach ($_POST['product_ids'] as $productId) {
                $stmt->execute([$designId, (int)$productId]);
            }
        }
    }

    public function deletePremadeDesign(): void {
        $this->requireAdmin();

        $designId = $this->getIdFromUrl();

        // Delete image file
        $this->removeDesignImage($designId);

        // Delete from database
        $stmt = $this->db->prepare("DELETE FROM premade_designs WHERE id = ?");
        $stmt->execute([$designId]);

        header('Location: /admin/premade');
    }

    public function apiGetPremadeDesign(): void {
        $this->requireAdmin();

        header('Content-Type: application/json');

        $designId = $this->getIdFromUrl();

        $stmt = $this->db->prepare("SELECT * FROM premade_designs WHERE id = ?");
        $stmt->execute([$designId]);
        $design = $stmt->fetch();

        if (!$design) {
            http_response_code(404);
            echo json_encode(['error' => 'Design not found']);
            return;
        }

        // Get associated product IDs
        $stmt = $this->db->prepare("SELECT product_id FROM design_products WHERE design_id = ?");
        $stmt->execute([$designId]);
        $productIds = array_column($stmt->fetchAll(), 'product_id');

        echo json_encode([
            'design' => $design,
            'product_ids' => array_map('intval', $productIds),
            'is_fixed' => (bool)$design['is_fixed']
        ]);
    }

    public function positionEditor(): void {
        $this->requireAdmin();
        $designId = $this->getIdFromUrl();

        $stmt = $this->db->prepare("
            SELECT d.*, s.name as section_name
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            WHERE d.id = ?
        ");
        $stmt->execute([$designId]);
        $design = $stmt->fetch();

        if (!$design) {
            http_response_code(404);
            echo "Design not found";
            return;
        }

        // Get associated products
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.image_path, p.back_image_path
            FROM products p
            JOIN design_products dp ON p.id = dp.product_id
            WHERE dp.design_id = ? AND p.active = 1
            ORDER BY p.name
        ");
        $stmt->execute([$designId]);
        $products = $stmt->fetchAll();

        $title = 'Position Editor — ' . htmlspecialchars($design['name']);
        require __DIR__ . '/../views/admin/premade_position.php';
    }

    public function savePosition(): void {
        $this->requireAdmin();
        $designId = $this->getIdFromUrl();

        // Handle back image removal
        if (isset($_POST['remove_back_image']) && $_POST['remove_back_image'] === '1') {
            $this->removeBackDesignImage($designId);
        } else {
            $this->handleBackDesignImageUpload($designId);
        }

        $stmt = $this->db->prepare("
            UPDATE premade_designs SET
                design_pos_x = ?,
                design_pos_y = ?,
                design_pos_size = ?,
                design_pos_back_x = ?,
                design_pos_back_y = ?,
                design_pos_back_size = ?
            WHERE id = ?
        ");
        $stmt->execute([
            (float)($_POST['design_pos_x'] ?? 0),
            (float)($_POST['design_pos_y'] ?? 0),
            (float)($_POST['design_pos_size'] ?? 55),
            (float)($_POST['design_pos_back_x'] ?? 0),
            (float)($_POST['design_pos_back_y'] ?? 0),
            (float)($_POST['design_pos_back_size'] ?? 55),
            $designId
        ]);

        header('Location: /admin/premade/position/' . $designId . '?saved=1');
    }

    private function handleBackDesignImageUpload(int $designId): void {
        if (!isset($_FILES['back_image']) || $_FILES['back_image']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $publicRoot = __DIR__ . '/../../public';
        $result = Upload::saveImage(
            $_FILES['back_image'],
            $publicRoot . '/images/designs/',
            'design_' . $designId . '_back',
            Upload::DEFAULT_MAX_BYTES,
            $publicRoot
        );

        if (isset($result['error'])) {
            error_log("Back design image upload rejected for design $designId: " . $result['error']);
            return;
        }

        $stmt = $this->db->prepare("UPDATE premade_designs SET back_image_path = ? WHERE id = ?");
        $stmt->execute([$result['path'], $designId]);
    }

    private function removeBackDesignImage(int $designId): void {
        $uploadDir = __DIR__ . '/../../public/images/designs/';
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $uploadDir . 'design_' . $designId . '_back.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $stmt = $this->db->prepare("UPDATE premade_designs SET back_image_path = NULL WHERE id = ?");
        $stmt->execute([$designId]);
    }

    private function handleDesignImageUpload(int $designId): void {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $publicRoot = __DIR__ . '/../../public';
        $result = Upload::saveImage(
            $_FILES['image'],
            $publicRoot . '/images/designs/',
            'design_' . $designId,
            Upload::DEFAULT_MAX_BYTES,
            $publicRoot
        );

        if (isset($result['error'])) {
            error_log("Design image upload rejected for design $designId: " . $result['error']);
            return;
        }

        $stmt = $this->db->prepare("UPDATE premade_designs SET image_path = ? WHERE id = ?");
        $stmt->execute([$result['path'], $designId]);
    }

    private function removeDesignImage(int $designId): void {
        $uploadDir = __DIR__ . '/../../public/images/designs/';

        // Delete any existing images for this design
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $uploadDir . 'design_' . $designId . '.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Clear image_path in database
        $stmt = $this->db->prepare("UPDATE premade_designs SET image_path = NULL WHERE id = ?");
        $stmt->execute([$designId]);
    }
}
