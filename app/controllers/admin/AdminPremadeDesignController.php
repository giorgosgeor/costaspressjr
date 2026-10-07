<?php

/**
 * Premade designs: adding and editing them, which products they come on,
 * and where they sit on each product.
 */
class AdminPremadeDesignController extends AdminController {
    // ==================== PREMADE DESIGNS ====================

    public function index(): void {
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

        $this->render('admin/premade/index', get_defined_vars());
    }

    public function store(): void {
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

    public function update(): void {
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

    public function destroy(): void {
        $this->requireAdmin();

        $designId = $this->getIdFromUrl();

        // Delete image file
        $this->removeDesignImage($designId);

        // Delete from database
        $stmt = $this->db->prepare("DELETE FROM premade_designs WHERE id = ?");
        $stmt->execute([$designId]);

        header('Location: /admin/premade');
    }

    public function apiShow(): void {
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

    public function position(): void {
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

        // Associated products, each carrying its OWN placement for this design.
        // Placement lives on the link row so one design can sit differently on
        // every garment; the design-level columns are only the fallback for a
        // link that has never been positioned.
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.image_path, p.back_image_path,
                   COALESCE(dp.design_pos_x,         d.design_pos_x,         0)  AS pos_x,
                   COALESCE(dp.design_pos_y,         d.design_pos_y,         0)  AS pos_y,
                   COALESCE(dp.design_pos_size,      d.design_pos_size,      55) AS pos_size,
                   COALESCE(dp.design_pos_back_x,    d.design_pos_back_x,    0)  AS pos_back_x,
                   COALESCE(dp.design_pos_back_y,    d.design_pos_back_y,    0)  AS pos_back_y,
                   COALESCE(dp.design_pos_back_size, d.design_pos_back_size, 55) AS pos_back_size
            FROM products p
            JOIN design_products dp ON p.id = dp.product_id
            JOIN premade_designs d  ON d.id = dp.design_id
            WHERE dp.design_id = ? AND p.active = 1
            ORDER BY p.name
        ");
        $stmt->execute([$designId]);
        $products = $stmt->fetchAll();

        $title = 'Position Editor — ' . htmlspecialchars($design['name']);
        $this->render('admin/premade/position', get_defined_vars());
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

        $pos = [
            (float)($_POST['design_pos_x'] ?? 0),
            (float)($_POST['design_pos_y'] ?? 0),
            (float)($_POST['design_pos_size'] ?? 55),
            (float)($_POST['design_pos_back_x'] ?? 0),
            (float)($_POST['design_pos_back_y'] ?? 0),
            (float)($_POST['design_pos_back_size'] ?? 55),
        ];
        $productId = (int)($_POST['product_id'] ?? 0);

        if ($productId > 0) {
            // Placement is per garment: write it to this design/product link
            // only, leaving the design's other products untouched.
            $stmt = $this->db->prepare("
                UPDATE design_products SET
                    design_pos_x = ?, design_pos_y = ?, design_pos_size = ?,
                    design_pos_back_x = ?, design_pos_back_y = ?, design_pos_back_size = ?
                WHERE design_id = ? AND product_id = ?
            ");
            $stmt->execute(array_merge($pos, [$designId, $productId]));
        } else {
            // No product chosen (a design with no products linked yet) — fall
            // back to the design-level values, which seed any future link.
            $stmt = $this->db->prepare("
                UPDATE premade_designs SET
                    design_pos_x = ?, design_pos_y = ?, design_pos_size = ?,
                    design_pos_back_x = ?, design_pos_back_y = ?, design_pos_back_size = ?
                WHERE id = ?
            ");
            $stmt->execute(array_merge($pos, [$designId]));
        }

        $back = '/admin/premade/position/' . $designId . '?saved=1'
              . ($productId > 0 ? '&product=' . $productId : '');
        header('Location: ' . $back);
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

    private function handleDesignImageUpload(int $designId): void {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $publicRoot = public_path();
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
        $uploadDir = public_path('images/designs/');

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

    private function handleBackDesignImageUpload(int $designId): void {
        if (!isset($_FILES['back_image']) || $_FILES['back_image']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $publicRoot = public_path();
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
        $uploadDir = public_path('images/designs/');
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $file = $uploadDir . 'design_' . $designId . '_back.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $stmt = $this->db->prepare("UPDATE premade_designs SET back_image_path = NULL WHERE id = ?");
        $stmt->execute([$designId]);
    }
}
