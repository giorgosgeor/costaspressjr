<?php

/**
 * A signed-in customer's own pages: the account overview, their orders,
 * favourites and cookie choice.
 */
class AccountController extends Controller {
    /**
     * My Account page - shows saved designs, order history, profile
     */
    public function index(): void {
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

        $favorites = (new Favorites($this->db))->forUser($userId);

        $this->render('account/index', ['user' => $user, 'savedDesigns' => $savedDesigns, 'orders' => $orders, 'uploads' => $uploads, 'favorites' => $favorites]);
    }

    public function orders(): void {
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

        $this->render('account/orders', ['orders' => $orders]);
    }

    public function order(): void {
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

        $this->render('account/order', ['order' => $order, 'orderItems' => $orderItems]);
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
}
