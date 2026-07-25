    <?php
    class Cart {
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
        public function addItem(array $data, int $cartId): bool {
            $stmt = $this->db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, unit_price, is_custom_design, design_data, size_name, color_name, color_hex, custom_design_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
                $cartId,
                $data['product_id'],
                $data['quantity'],
                $data['unit_price'] ?? 0,
                !empty($data['custom']) ? 1 : 0,
                isset($data['elements']) ? json_encode($data['elements']) : null,
                $data['size_name'] ?? null,
                $data['color_name'] ?? null,
                $data['color_hex'] ?? null,
                $data['custom_design_fee'] ?? 0
            ]);
        }
    private PDO $db;
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    // Get or create a cart for a user
    public function getOrCreateCartId(int $userId): int {
        $stmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if ($cart) return (int)$cart['id'];
        $stmt = $this->db->prepare("INSERT INTO carts (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        return (int)$this->db->lastInsertId();
    }
    
}
