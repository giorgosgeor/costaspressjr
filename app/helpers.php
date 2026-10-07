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
