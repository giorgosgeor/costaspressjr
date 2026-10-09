<?php

/**
 * Machine-facing endpoints: the uptime health check, robots.txt and the
 * sitemap. robots.txt and the sitemap name the site by APP_URL, so they are
 * right on whatever domain it is deployed to (and behind a proxy, where the
 * request itself can look like plain http).
 */
class SiteController extends Controller {
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
     * robots.txt. Anything but production asks not to be indexed at all, so
     * a staging copy never competes with the real shop in search results.
     */
    public function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        if (Env::get('APP_ENV', 'production') !== 'production') {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }
        $private = ['/admin', '/login', '/register', '/logout', '/account', '/cart', '/checkout',
                    '/orders', '/api/', '/custom-design/', '/health', '/verify-email', '/forgot-password',
                    '/reset-password', '/track-order', '/assistant/', '/stripe/', '/lang/'];
        echo "User-agent: *\n";
        foreach ($private as $path) {
            echo "Disallow: $path\n";
        }
        echo "Allow: /\n\nSitemap: " . self::base() . "/sitemap.xml\n";
    }

    /**
     * Dynamic sitemap for search engines: the shop's pages, every active
     * product and every active premade design.
     */
    public function sitemap(): void {
        $base = self::base();

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
            ['path' => '/shop/premade',        'changefreq' => 'weekly', 'priority' => '0.8'],
            ['path' => '/shop/premade/anime',  'changefreq' => 'weekly', 'priority' => '0.7'],
            ['path' => '/shop/select_product', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ];
        try {
            foreach ($this->db->query("SELECT id FROM products WHERE active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $urls[] = ['path' => '/product/' . (int)$id, 'changefreq' => 'weekly', 'priority' => '0.6'];
            }
            foreach ($this->db->query("SELECT id FROM premade_designs WHERE active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $urls[] = ['path' => '/shop/design/' . (int)$id, 'changefreq' => 'weekly', 'priority' => '0.6'];
            }
        } catch (PDOException $e) {
            // The pages above are still worth listing.
        }

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

    /** The site's own address (APP_URL), without a trailing slash. */
    private static function base(): string {
        $url = rtrim((string)Env::get('APP_URL', ''), '/');
        if ($url !== '') {
            return $url;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
