<?php
$title        = t('info.returns.title', false);
$infoTitle    = t('info.returns.h1_full', false);
$infoSubtitle = t('info.returns.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<h2><?= t('info.returns.h1') ?></h2>
<p><?= t('info.returns.p1', false) ?></p>

<h2><?= t('info.returns.h2') ?></h2>
<p><?= t('info.returns.p2', false) ?></p>

<h2><?= t('info.returns.h3') ?></h2>
<p><?= t('info.returns.p3') ?></p>

<h2><?= t('info.returns.h4') ?></h2>
<ul>
    <li><?= t('info.returns.p4_a') ?></li>
    <li><?= t('info.returns.p4_b') ?></li>
    <li><?= t('info.returns.p4_c') ?></li>
</ul>

<?php if (Env::get('APP_ENV', 'production') !== 'production'): ?>
<p class="info-disclaimer"><?= t('info.returns.placeholder', false) ?></p>
<?php endif; ?>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
