<?php require __DIR__ . '/../../layouts/customer_header.php'; ?>

<section class="info-page">
    <div class="container">
        <?php
            $pageName = $infoTitle ?? t('info.breadcrumb.page', false);
            $crumbs   = [[t('header.nav.home', false), '/'], [$pageName, null]];
            $heading  = $pageName;
            $lead     = $infoSubtitle ?? '';
            $headNote = I18n::t('info.last_updated', ['date' => htmlspecialchars($infoUpdated ?? date('F Y'))]);
            require __DIR__ . '/../../partials/page_head.php';
        ?>
        <article class="info-article">
            <div class="info-article-body">
                <?= $infoBody ?? '' ?>
            </div>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../layouts/customer_footer.php'; ?>
