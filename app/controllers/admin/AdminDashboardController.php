<?php

/**
 * The admin home page and the customer list.
 */
class AdminDashboardController extends AdminController {
    public function index(): void {
        $this->requireAdmin();

        // Guests (anonymous checkout sessions) aren't real registrations.
        $userCount = $this->db->query("SELECT COUNT(*) FROM users WHERE role <> 'guest'")->fetchColumn();
        $productCount = $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $orderCount = $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

        $this->render('admin/dashboard', get_defined_vars());
    }

    public function users(): void {
        $this->requireAdmin();

        // Guest rows are checkout plumbing, not accounts — a guest's contact
        // details live on their order (shipping block), which is where the
        // admin actually needs them.
        $users = $this->db->query("SELECT id, username, email, phone, role, created_at FROM users WHERE role <> 'guest' ORDER BY id DESC")->fetchAll();

        $this->render('admin/users', get_defined_vars());
    }
}
