<?php
/**
 * Breadcrumb trail, the same on every page.
 *
 * $crumbs: list of [label, href] pairs, plain text (escaped here). The last
 * entry is the current page and is not a link; its href is ignored.
 */
$crumbs = $crumbs ?? [];
$lastCrumb = count($crumbs) - 1;
?>
<nav class="breadcrumb" aria-label="<?= t('common.breadcrumb') ?>">
    <ol>
        <?php foreach ($crumbs as $i => [$crumbLabel, $crumbHref]): ?>
        <li><?php if ($i < $lastCrumb && $crumbHref): ?><a href="<?= htmlspecialchars($crumbHref) ?>"><?= htmlspecialchars($crumbLabel) ?></a><?php else: ?><span aria-current="page"><?= htmlspecialchars($crumbLabel) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
