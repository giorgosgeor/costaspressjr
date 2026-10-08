<?php

/**
 * The colour palette products choose from.
 */
class AdminColorController extends AdminController {
    // ==================== COLORS ====================

    public function index(): void {
        $this->requireAdmin();

        $colors = $this->db->query("SELECT * FROM available_colors ORDER BY color_name")->fetchAll();

        $this->render('admin/colors', ['colors' => $colors]);
    }

    public function store(): void {
        $this->requireAdmin();

        $stmt = $this->db->prepare("INSERT INTO available_colors (color_name, color_hex, is_active) VALUES (?, ?, 1)");
        $stmt->execute([
            $_POST['color_name'],
            $_POST['color_hex']
        ]);

        header('Location: /admin/colors');
    }

    public function destroy(): void {
        $this->requireAdmin();

        $colorId = $this->getIdFromUrl();
        
        $stmt = $this->db->prepare("DELETE FROM available_colors WHERE id = ?");
        $stmt->execute([$colorId]);

        header('Location: /admin/colors');
    }

    // ==================== API ENDPOINTS ====================

    public function apiIndex(): void {
        $this->requireAdmin();
        
        header('Content-Type: application/json');
        
        $colors = $this->db->query("SELECT id, color_name as name, color_hex as hex_code FROM available_colors WHERE is_active = 1 ORDER BY color_name")->fetchAll();
        
        echo json_encode($colors);
    }
}
