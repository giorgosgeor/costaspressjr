<?php

/**
 * Products: the list, adding and editing (sizes, colours, mockup images),
 * and each product's print areas.
 */
class AdminProductController extends AdminController {
    // ==================== PRODUCTS ====================

    public function index(): void {
        $this->requireAdmin();

        $products = $this->db->query("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id) as size_count,
                   (SELECT COUNT(*) FROM product_colors WHERE product_id = p.id AND is_available = 1) as color_count
            FROM products p 
            ORDER BY p.id ASC
        ")->fetchAll();

        $this->render('admin/products', get_defined_vars());
    }

    public function create(): void {
        $this->requireAdmin();

        $colors = $this->db->query("SELECT id, color_name as name, color_hex as hex_code FROM available_colors WHERE is_active = 1 ORDER BY color_name")->fetchAll();

        $this->render('admin/products_add', get_defined_vars());
    }

    public function store(): void {
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

    public function edit(): void {
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

        $this->render('admin/products_edit', get_defined_vars());
    }

    public function update(): void {
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


        // The variants are rebuilt from the form below, but their supplier
        // price and stock live only on these rows — the form doesn't carry
        // them. Remember them first; without this every save dropped
        // unit_price to NULL and coloured garments fell back to the
        // (cheaper) white price.
        $kept = [];
        $stmt = $this->db->prepare("
            SELECT v.size_id, v.color_id, v.unit_price, v.stock_quantity, ac.color_name
              FROM product_variants v LEFT JOIN available_colors ac ON ac.id = v.color_id
             WHERE v.product_id = ?
        ");
        $stmt->execute([$productId]);
        foreach ($stmt->fetchAll() as $v) {
            $kept[$v['size_id'] . '|' . $v['color_id']] = $v;
        }
        // A colour newly ticked for a size takes the price of a colour that
        // size already had on the same side of the supplier's white/colour
        // split; with neither, it falls back to base price + size modifier.
        $isWhite = fn(?string $name) => in_array(strtolower(trim((string)$name)), ['white', 'ivory', 'off white', 'off-white'], true);
        $siblingPrice = [];
        foreach ($kept as $v) {
            if ($v['unit_price'] !== null) {
                $siblingPrice[$v['size_id'] . '|' . ($isWhite($v['color_name']) ? 'w' : 'c')] = $v['unit_price'];
            }
        }
        $colorNames = $this->db->query("SELECT id, color_name FROM available_colors")->fetchAll(PDO::FETCH_KEY_PAIR);

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
                $variantStmt = $this->db->prepare("INSERT INTO product_variants (product_id, size_id, color_id, stock_quantity, unit_price, is_available) VALUES (?, ?, ?, ?, ?, 1)");
                $colorStmt = $this->db->prepare("INSERT IGNORE INTO product_colors (product_id, color_id, is_available) VALUES (?, ?, 1)");

                $order = 1;
                $keptSizeIds = [];
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
                    $keptSizeIds[] = (int)$sizeId;

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
                            $prev  = $kept[$sizeId . '|' . $colorId] ?? null;
                            $price = $prev['unit_price']
                                ?? $siblingPrice[$sizeId . '|' . ($isWhite($colorNames[$colorId] ?? '') ? 'w' : 'c')]
                                ?? null;
                            try {
                                $variantStmt->execute([$productId, (int)$sizeId, $colorId, (int)($prev['stock_quantity'] ?? 0), $price]);
                                $colorStmt->execute([$productId, $colorId]);
                            } catch (PDOException $e) {
                                error_log("updateProduct variant insert failed (product=$productId size=$sizeId color=$colorId): " . $e->getMessage());
                            }
                        }
                    }
                }

                // Sizes removed in the form (the ✕) can't always be deleted —
                // a saved design may point at them — so mark them unavailable.
                // Otherwise they came back in this editor with no colours.
                $flag = $this->db->prepare(
                    "UPDATE product_sizes SET is_available = 0 WHERE product_id = ?"
                    . ($keptSizeIds ? " AND id NOT IN (" . implode(',', array_map('intval', $keptSizeIds)) . ")" : '')
                );
                $flag->execute([$productId]);
            } catch (PDOException $e) {
                error_log("updateProduct SQL error (prepare/insert): " . $e->getMessage());
            }
        }

        header('Location: /admin/products');
    }

    public function destroy(): void {
        $this->requireAdmin();

        $productId = $this->getIdFromUrl();
        
        // Delete product (sizes and colors will cascade delete)
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        // Delete all product images
        $uploadDir = public_path('images/products/');
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

    public function apiShow(): void {
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
                $imagePath = public_path($product['image_path']);
                $product['has_image'] = file_exists($imagePath);
            }
            
            // Check if back image exists
            $product['has_back_image'] = false;
            if (!empty($product['back_image_path'])) {
                $backImagePath = public_path($product['back_image_path']);
                $product['has_back_image'] = file_exists($backImagePath);
            }
            
            // Check if left sleeve image exists
            $product['has_left_sleeve_image'] = false;
            if (!empty($product['left_sleeve_image_path'])) {
                $leftSleevePath = public_path($product['left_sleeve_image_path']);
                $product['has_left_sleeve_image'] = file_exists($leftSleevePath);
            }
            
            // Check if right sleeve image exists
            $product['has_right_sleeve_image'] = false;
            if (!empty($product['right_sleeve_image_path'])) {
                $rightSleevePath = public_path($product['right_sleeve_image_path']);
                $product['has_right_sleeve_image'] = file_exists($rightSleevePath);
            }
            
            // Get sizes. Unavailable ones are sizes removed from the product
            // that a saved design still points at, so the row can't go.
            $stmt = $this->db->prepare("SELECT * FROM product_sizes WHERE product_id = ? AND is_available = 1 ORDER BY size_order");
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

    public function designArea(): void {
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
        $this->render('admin/design_area_editor', get_defined_vars());
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

    private function handleProductImageUpload(int $productId): void {
        $fileField = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileField = 'image';
        } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileField = 'product_image';
        }
        if (!$fileField) return;

        $publicRoot = public_path();
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
        $uploadDir = public_path('images/products/');
        
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

        $publicRoot = public_path();
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
        $uploadDir = public_path('images/products/');
        
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

        $publicRoot = public_path();
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
        $uploadDir = public_path('images/products/');
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
}
