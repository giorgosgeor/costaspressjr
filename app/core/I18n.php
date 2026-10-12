<?php
/**
 * Tiny translation helper, and which language each request is in.
 *
 * Pages: the address decides. English pages sit at the root (/about), Greek
 * ones under /el/ (/el/about), so search engines can find and index both.
 * route() reads the prefix before routing and takes it off, so routes and
 * controllers only ever see /about; localizeLinks() puts it back on a Greek
 * page's internal links. The lang cookie (1 year) remembers the language of
 * the last page viewed, so a visitor reading Greek who follows a plain link
 * (an email, a script's redirect) is sent on to the Greek page.
 *
 * Everything else (form posts, JSON, the admin) is in the visitor's language:
 * ?lang query → lang cookie → Accept-Language → default 'en'.
 */
class I18n
{
    public const SUPPORTED = ['en', 'el'];
    public const DEFAULT_LOCALE = 'en';
    public const COOKIE_NAME = 'lang';

    /** Addresses that aren't pages: no language prefix, no language redirect. */
    private const NOT_PAGES = ['/admin', '/api', '/lang', '/stripe', '/custom-design', '/assistant',
                               '/health', '/sitemap.xml', '/robots.txt',
                               '/images', '/css', '/js', '/fonts', '/uploads'];

    private static string $locale = self::DEFAULT_LOCALE;
    private static array $messages = [];
    private static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        $locale = self::resolveLocale();
        self::setLocale($locale, false);
    }

    public static function setLocale(string $locale, bool $persist = true): void
    {
        if (!in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT_LOCALE;
        }
        self::$locale = $locale;
        self::loadMessages($locale);

        if ($persist) {
            self::writeCookie($locale);
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * Before routing (public/index.php): set the request's language and take
     * the language prefix off REQUEST_URI. Returns an address to redirect to
     * instead when a plain (English) page is asked for by someone who reads
     * another language: by the lang cookie, or on the home page only, by the
     * browser's language on a first visit. Crawlers send neither, so they
     * always get the language the address names.
     */
    public static function route(string $method, bool $wantsJson): ?string
    {
        self::$booted = true;
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $tail = strpbrk($uri, '?') ?: '';

        // A prefixed page: /el/about, or /el and /el/ for the home page.
        foreach (self::SUPPORTED as $locale) {
            $prefix = '/' . $locale;
            if ($locale === self::DEFAULT_LOCALE || ($path !== $prefix && !str_starts_with($path, $prefix . '/'))) {
                continue;
            }
            $rest = substr($path, strlen($prefix)) ?: '/';
            if (self::isPagePath($rest)) {
                $_SERVER['REQUEST_URI'] = $rest . $tail;
                self::setLocale($locale);
                return null;
            }
        }

        // A plain page address: English, unless the visitor reads another language.
        if (in_array($method, ['GET', 'HEAD'], true) && !$wantsJson && self::isPagePath($path)) {
            $wanted = self::cookieLocale() ?? ($path === '/' ? self::browserLocale() : null);
            if ($wanted !== null && $wanted !== self::DEFAULT_LOCALE) {
                return self::url($path, $wanted) . $tail;
            }
            self::setLocale(self::DEFAULT_LOCALE);
            return null;
        }

        self::setLocale(self::resolveLocale(), false);
        return null;
    }

    /** Whether an address (a path, no query) is a page, with a copy in each language. */
    public static function isPagePath(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        foreach ([...self::NOT_PAGES, ...array_map(static fn($l) => '/' . $l, self::SUPPORTED)] as $p) {
            if ($path === $p || str_starts_with($path, $p . '/')) {
                return false;
            }
        }
        return true;
    }

    /**
     * A page's address in a language (by default the current one):
     * url('/about', 'el') → '/el/about', url('/', 'el') → '/el/'. Addresses
     * that aren't pages (images, /api/, /admin, …) come back unchanged.
     */
    public static function url(string $path, ?string $locale = null): string
    {
        $locale ??= self::$locale;
        if ($locale === self::DEFAULT_LOCALE || !self::isPagePath((string)strtok($path, '?#'))) {
            return $path;
        }
        return '/' . $locale . $path;
    }

    /**
     * A page's internal links in the page's language: on a Greek page,
     * href="/shop" becomes href="/el/shop". Templates (and translations)
     * write plain paths; public/index.php passes every page that isn't in
     * the default language through this, so someone reading Greek, a crawler
     * included, stays on Greek pages. Links that aren't pages are left alone.
     */
    public static function localizeLinks(string $html): string
    {
        if (self::$locale === self::DEFAULT_LOCALE) {
            return $html;
        }
        return preg_replace_callback(
            '#\b((?:data-)?href)="(/(?!/)[^"]*)"#',
            static fn(array $m): string => $m[1] . '="' . self::url($m[2]) . '"',
            $html
        ) ?? $html;
    }

    public static function t(string $key, array $params = [], ?string $fallback = null): string
    {
        $msg = self::$messages[$key] ?? null;

        // Fall back to English if the current locale is missing the key.
        if ($msg === null && self::$locale !== self::DEFAULT_LOCALE) {
            static $enFallback = null;
            if ($enFallback === null) {
                $enFallback = self::loadFile(self::DEFAULT_LOCALE) ?? [];
            }
            $msg = $enFallback[$key] ?? null;
        }

        if ($msg === null) {
            $msg = $fallback ?? $key;
        }

        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $msg = str_replace('{' . $k . '}', (string)$v, $msg);
            }
        }
        return $msg;
    }

    /**
     * Return all loaded messages — used to ship the dictionary to JavaScript
     * so client-side code can call window.I18N.t(key).
     */
    public static function all(): array
    {
        return self::$messages;
    }

    /**
     * The dictionary for client-side code (window.I18N), as the footer's
     * script tag: a static file per language, public/js/i18n/<locale>.js,
     * which the browser caches like any other script, rather than 65 KB of
     * JSON inside every page (audit P4). Its first line names the hash of the
     * app/lang JSON it was built from; when the translations change it is
     * built again (on a developer's machine: commit it with them). Where it
     * can't be (a read-only server), the dictionary goes inline as before:
     * heavier, never out of date.
     */
    public static function clientScript(): string
    {
        $source = __DIR__ . '/../lang/' . self::$locale . '.json';
        $path   = '/js/i18n/' . self::$locale . '.js';
        $file   = dirname(__DIR__, 2) . '/public' . $path;
        $stamp  = '/* app/lang/' . self::$locale . '.json ' . (is_file($source) ? md5_file($source) : '') . ' */';

        $current = is_file($file) && @file_get_contents($file, false, null, 0, strlen($stamp)) === $stamp;
        if (!$current && is_file($source)) {
            $js  = $stamp . "\n// Built by I18n::clientScript(); edit app/lang/" . self::$locale . ".json instead.\n"
                 . 'window.I18N = ' . json_encode(['locale' => self::$locale, 'messages' => self::$messages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            $current = (is_dir(dirname($file)) || @mkdir(dirname($file), 0755, true))
                && @file_put_contents($tmp, $js) !== false
                && @rename($tmp, $file);
            @unlink($tmp);
        }
        return $current
            ? View::script($path)
            : View::json('i18n-data', ['locale' => self::$locale, 'messages' => self::$messages]);
    }

    private static function resolveLocale(): string
    {
        // 1. Explicit query parameter wins.
        if (!empty($_GET['lang']) && is_string($_GET['lang'])) {
            $q = strtolower(substr($_GET['lang'], 0, 5));
            if (in_array($q, self::SUPPORTED, true)) {
                self::writeCookie($q);
                return $q;
            }
        }

        // 2. Persisted cookie, 3. Accept-Language (browser detection on first visit).
        return self::cookieLocale() ?? self::browserLocale() ?? self::DEFAULT_LOCALE;
    }

    /** The language in the lang cookie, if it holds a supported one. */
    private static function cookieLocale(): ?string
    {
        if (empty($_COOKIE[self::COOKIE_NAME]) || !is_string($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $c = strtolower(substr($_COOKIE[self::COOKIE_NAME], 0, 5));
        return in_array($c, self::SUPPORTED, true) ? $c : null;
    }

    /** The browser's language (Accept-Language), if it is one we have. */
    private static function browserLocale(): ?string
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }
        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        // Look for el / el-GR / el-CY first.
        if (preg_match('/\bel(-[a-z]{2})?\b/', $accept)) {
            return 'el';
        }
        if (preg_match('/\ben(-[a-z]{2})?\b/', $accept)) {
            return 'en';
        }
        return null;
    }

    private static function loadMessages(string $locale): void
    {
        self::$messages = self::loadFile($locale) ?? [];
    }

    private static function loadFile(string $locale): ?array
    {
        $path = __DIR__ . '/../lang/' . $locale . '.json';
        if (!is_file($path)) return null;
        $raw = file_get_contents($path);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function writeCookie(string $locale): void
    {
        if (headers_sent()) return;
        // Secure in production, like the session cookie (public/index.php):
        // production is HTTPS-only (.htaccess), and behind a TLS proxy the
        // request itself looks like plain http, so HTTPS alone would miss it.
        $secure = Env::get('APP_ENV', 'production') === 'production'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, $locale, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false, // readable by JS for client-side string lookups
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $locale;
    }
}
