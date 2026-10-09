<?php require View::path('layouts/customer_header'); ?>

<section class="info-page">
    <div class="container">
        <?php
            $pageName = $infoTitle ?? t('info.breadcrumb.page', false);
            $crumbs   = [[t('header.nav.home', false), '/'], [$pageName, null]];
            $heading  = $pageName;
            $lead     = $infoSubtitle ?? '';
            // $infoUpdated is a Y-m-d date, shown in words in the page's language.
            $headNote = isset($infoUpdated) ? I18n::t('info.last_updated', ['date' => htmlspecialchars(long_date($infoUpdated))]) : '';
            require View::path('partials/page_head');
        ?>
        <article class="info-article">
            <div class="info-article-body">
                <?= $infoBody ?? '' ?>
            </div>
        </article>
    </div>
</section>

<?php require View::path('layouts/customer_footer'); ?>
