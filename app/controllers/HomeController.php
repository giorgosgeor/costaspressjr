<?php

class HomeController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Public landing page - shown when not logged in
     */
    public function landing(): void {
        require __DIR__ . '/../views/public/landing.php';
    }

    /**
     * Main entry point - admins go to /admin, everyone else sees the home page
     */
    public function index(): void {
        if (Auth::isAdmin()) {
            header('Location: /admin');
            exit;
        }

        // Guests and customers both see the home page
        $customerController = new CustomerController($this->db);
        $customerController->home();
    }

    /**
     * Health check endpoint for uptime monitors.
     * Returns 200 OK with a tiny JSON body when the app and DB are reachable,
     * 503 with an error string otherwise. Designed to be called frequently
     * (every 60s by something like UptimeRobot), so it does the cheapest
     * possible DB ping rather than running a full query.
     */
    public function health(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            $this->db->query('SELECT 1')->fetch();
        } catch (\Throwable $e) {
            http_response_code(503);
            echo json_encode([
                'status' => 'error',
                'service' => 'database',
            ]);
            return;
        }

        echo json_encode([
            'status' => 'ok',
            'time'   => gmdate('c'),
        ]);
    }

    /**
     * Dynamic sitemap for search engines.
     */
    public function sitemap(): void {
        $host = $_SERVER['HTTP_HOST'] ?? 'www.costaspressjr.com';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $host;

        $urls = [
            ['path' => '/',          'changefreq' => 'weekly',  'priority' => '1.0'],
            ['path' => '/shop',      'changefreq' => 'weekly',  'priority' => '0.9'],
            ['path' => '/about',     'changefreq' => 'monthly', 'priority' => '0.5'],
            ['path' => '/contact',   'changefreq' => 'yearly',  'priority' => '0.4'],
            ['path' => '/faq',       'changefreq' => 'monthly', 'priority' => '0.4'],
            ['path' => '/shipping',  'changefreq' => 'yearly',  'priority' => '0.3'],
            ['path' => '/returns',   'changefreq' => 'yearly',  'priority' => '0.3'],
            ['path' => '/sizing',    'changefreq' => 'yearly',  'priority' => '0.3'],
            ['path' => '/terms',     'changefreq' => 'yearly',  'priority' => '0.2'],
            ['path' => '/privacy',   'changefreq' => 'yearly',  'priority' => '0.2'],
            ['path' => '/cookies',   'changefreq' => 'yearly',  'priority' => '0.2'],
        ];

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($base . $u['path'], ENT_XML1) . "</loc>\n";
            echo "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
            echo "    <priority>" . $u['priority'] . "</priority>\n";
            echo "  </url>\n";
        }
        echo '</urlset>' . "\n";
    }
}
