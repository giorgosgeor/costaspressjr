<?php

/**
 * The shop's own pages: home, about, contact, the information pages and
 * order tracking.
 */
class PageController extends Controller {
    /**
     * Main entry point - admins go to /admin, everyone else sees the home page
     */
    public function index(): void {
        if (Auth::isAdmin()) {
            header('Location: /admin');
            exit;
        }

        // Guests and customers both see the home page
        $this->home();
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

        $this->render('pages/home', ['user' => $user, 'featuredProducts' => $featuredProducts, 'bulkSavingPct' => $bulkSavingPct]);
    }

    public function about(): void {
        $this->render('pages/about');
    }

    public function contact(): void {
        $this->render('pages/contact');
    }

    public function sendContact(): void {
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
    public function info(string $slug): void {
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

        $this->render('pages/info/' . basename($allowed[$slug], '.php'));
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

        $this->render('pages/info/track_order', ['trackResult' => $trackResult]);
    }
}
