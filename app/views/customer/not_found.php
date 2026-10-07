<?php $title = t('notfound.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section page-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('notfound.title', false), null]];
            $heading = t('notfound.title', false);
            $lead    = t('notfound.lead', false);
            require __DIR__ . '/../partials/page_head.php';
        ?>

        <p style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;">
            <a href="/" class="btn btn-primary"><?= t('notfound.home') ?></a>
            <a href="/shop" class="btn btn-outline"><?= t('notfound.shop') ?></a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
