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
