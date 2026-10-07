<?php
/**
 * The heading every inner page opens with: breadcrumb, title, one line
 * of lead text, and an optional note on the right (a count, a date).
 *
 * $crumbs   see partials/breadcrumb.php
 * $heading  plain text title
 * $lead     plain text, optional
 * $headNote HTML, optional (callers escape what goes in)
 */
?>
<header class="page-head">
    <?php if (!empty($crumbs)) require __DIR__ . '/breadcrumb.php'; ?>
    <div class="page-head-row">
        <div class="page-head-text">
            <h1 class="page-head-title"><?= htmlspecialchars($heading ?? '') ?></h1>
            <?php if (!empty($lead)): ?>
            <p class="page-head-lead"><?= htmlspecialchars($lead) ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($headNote)): ?>
        <div class="page-head-note"><?= $headNote ?></div>
        <?php endif; ?>
    </div>
</header>
