<?php require __DIR__ . '/../../layouts/customer_header.php'; ?>

<section class="info-page">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= t('header.nav.home') ?></a> <span>&rsaquo;</span>
            <span><?= htmlspecialchars($infoTitle ?? t('info.breadcrumb.page', false)) ?></span>
        </nav>
        <article class="info-article">
            <header class="info-article-header">
                <h1><?= htmlspecialchars($infoTitle ?? t('info.breadcrumb.page', false)) ?></h1>
                <?php if (!empty($infoSubtitle)): ?>
                <p class="info-article-subtitle"><?= htmlspecialchars($infoSubtitle) ?></p>
                <?php endif; ?>
                <p class="info-article-updated"><?= I18n::t('info.last_updated', ['date' => htmlspecialchars($infoUpdated ?? date('F Y'))]) ?></p>
            </header>
            <div class="info-article-body">
                <?= $infoBody ?? '' ?>
            </div>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../layouts/customer_footer.php'; ?>
