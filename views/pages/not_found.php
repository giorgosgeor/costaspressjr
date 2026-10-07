<?php $title = t('notfound.title', false); ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="section page-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('notfound.title', false), null]];
            $heading = t('notfound.title', false);
            $lead    = t('notfound.lead', false);
            require View::path('partials/page_head');
        ?>

        <p style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;">
            <a href="/" class="btn btn-primary"><?= t('notfound.home') ?></a>
            <a href="/shop" class="btn btn-outline"><?= t('notfound.shop') ?></a>
        </p>
    </div>
</section>

<?php require View::path('layouts/customer_footer'); ?>
