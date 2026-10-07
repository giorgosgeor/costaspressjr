<?php

/**
 * Orders: the list, the production view of one order, and its status.
 */
class AdminOrderController extends AdminController {
    // ==================== ORDERS ====================

    public function index(): void {
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

        $this->render('admin/orders', get_defined_vars());
    }

    public function show(): void {
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

            // Fall back to the DESIGN's own artwork and text when the per-order
            // copies are absent. Checkout copies cart_item_uploads into
            // order_item_uploads, but nothing has ever populated the cart-side
            // table — so for every existing custom order those two tables are
            // empty and the files are only reachable through design_id. Without
            // this the admin has no way to obtain the artwork to print.
            if (!empty($item['design_id'])) {
                if (empty($item['uploads'])) {
                    $stmt = $this->db->prepare("
                        SELECT id, original_filename, stored_file_path,
                               view_placement AS placement,
                               position_x, position_y, width, height
                        FROM custom_design_uploads WHERE design_id = ? ORDER BY layer_order, id
                    ");
                    $stmt->execute([$item['design_id']]);
                    $item['uploads'] = $stmt->fetchAll();
                    $item['uploads_from_design'] = !empty($item['uploads']);
                }
                if (empty($item['texts'])) {
                    $stmt = $this->db->prepare("
                        SELECT id, text_content, font_family, font_size, text_color,
                               is_bold, is_italic, is_underline,
                               view_placement AS placement, position_x, position_y
                        FROM custom_design_texts WHERE design_id = ? ORDER BY layer_order, id
                    ");
                    $stmt->execute([$item['design_id']]);
                    $item['texts'] = $stmt->fetchAll();
                    $item['texts_from_design'] = !empty($item['texts']);
                }
            }
            
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

        $this->render('admin/order_detail', get_defined_vars());
    }

    public function updateStatus(): void {
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
}
