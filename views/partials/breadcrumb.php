<?php
/**
 * Breadcrumb trail, the same on every page.
 *
 * $crumbs: list of [label, href] pairs, plain text (escaped here). The last
 * entry is the current page and is not a link; its href is ignored.
 */
$crumbs = $crumbs ?? [];
$lastCrumb = count($crumbs) - 1;

// The same trail for search engines (BreadcrumbList), with full addresses in
// the page's language; the last entry is this page.
$crumbItems = [];
foreach ($crumbs as $i => [$crumbLabel, $crumbHref]) {
    $crumbPath    = $i < $lastCrumb && $crumbHref ? $crumbHref : strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $crumbItems[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string)$crumbLabel, 'item' => app_url(I18n::url($crumbPath))];
}
?>
<?php if ($crumbItems): ?><?= View::jsonLd(['@type' => 'BreadcrumbList', 'itemListElement' => $crumbItems]) ?><?php endif; ?>
<nav class="breadcrumb" aria-label="<?= t('common.breadcrumb') ?>">
    <ol>
        <?php foreach ($crumbs as $i => [$crumbLabel, $crumbHref]): ?>
        <li><?php if ($i < $lastCrumb && $crumbHref): ?><a href="<?= htmlspecialchars($crumbHref) ?>"><?= htmlspecialchars($crumbLabel) ?></a><?php else: ?><span aria-current="page"><?= htmlspecialchars($crumbLabel) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
