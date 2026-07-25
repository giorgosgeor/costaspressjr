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
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
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

/**
 * Shorthand used in views: <?= t('home.hero.title') ?>
 * Returns the translation HTML-escaped — pass `false` for the second argument
 * when the translation contains intentional HTML.
 */
function t(string $key, bool $escape = true, array $params = []): string
{
    $s = I18n::t($key, $params);
    return $escape ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : $s;
}
