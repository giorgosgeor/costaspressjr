<?php
/**
 * The few global functions templates use. Loaded by app/bootstrap.php, so
 * they exist everywhere — keep this file small and free of side effects.
 */

/**
 * Translation, for templates: <?= t('home.hero.title') ?>
 * Returns the translation HTML-escaped — pass `false` for the second argument
 * when the translation contains intentional HTML.
 */
function t(string $key, bool $escape = true, array $params = []): string
{
    $s = I18n::t($key, $params);
    return $escape ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : $s;
}

/** HTML-escape a value for output: <?= e($product['name']) ?> */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Absolute path of a file under public/: public_path('images/logo.png').
 * With no argument, the public/ folder itself (no trailing slash).
 */
function public_path(string $path = ''): string
{
    $root = dirname(__DIR__) . '/public';
    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

/** A price as the shop shows it: money(12.5) → "€12.50" */
function money(mixed $amount): string
{
    return '€' . number_format((float)$amount, 2);
}

/**
 * A stored image path as a URL path. Paths are saved as "public/…",
 * "images/…" or "/images/…"; all three come back as "/images/…".
 */
function web_path(?string $path): string
{
    $path = (string)$path;
    if (strpos($path, 'public/') === 0) $path = substr($path, 7);
    return ($path !== '' && $path[0] !== '/') ? '/' . $path : $path;
}
