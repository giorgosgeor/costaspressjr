<?php
/**
 * Tiny translation helper.
 *
 * Locale resolution order: ?lang query → lang cookie → Accept-Language → default 'en'.
 * Once resolved, the chosen locale is persisted in a 1-year cookie so subsequent
 * visits skip the browser-detection step.
 */
class I18n
{
    public const SUPPORTED = ['en', 'el'];
    public const DEFAULT_LOCALE = 'en';
    public const COOKIE_NAME = 'lang';

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

        // 2. Persisted cookie.
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            $c = strtolower(substr($_COOKIE[self::COOKIE_NAME], 0, 5));
            if (in_array($c, self::SUPPORTED, true)) {
                return $c;
            }
        }

        // 3. Accept-Language header (browser detection on first visit).
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            // Look for el / el-GR / el-CY first.
            if (preg_match('/\bel(-[a-z]{2})?\b/', $accept)) {
                return 'el';
            }
            if (preg_match('/\ben(-[a-z]{2})?\b/', $accept)) {
                return 'en';
            }
        }

        return self::DEFAULT_LOCALE;
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
