<?php
$title        = t('info.shipping.title', false);
$infoTitle    = t('info.shipping.title', false);
$infoSubtitle = t('info.shipping.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<h2><?= t('info.shipping.h1') ?></h2>
<p><?= t('info.shipping.p1', false) ?></p>

<h2><?= t('info.shipping.h2') ?></h2>
<p><?= t('info.shipping.p2_lead') ?></p>
<ul>
    <li><?= t('info.shipping.p2_a') ?></li>
    <li><?= t('info.shipping.p2_b') ?></li>
    <li><?= t('info.shipping.p2_c') ?></li>
</ul>
<p><?= t('info.shipping.p2_after') ?></p>

<h2><?= t('info.shipping.h3') ?></h2>
<p><?= t('info.shipping.p3') ?></p>

<h2><?= t('info.shipping.h4') ?></h2>
<p><?= t('info.shipping.p4', false) ?></p>

<h2><?= t('info.shipping.h5') ?></h2>
<p><?= t('info.shipping.p5') ?></p>

<?php if (Env::get('APP_ENV', 'production') !== 'production'): ?>
<p class="info-disclaimer"><?= t('info.shipping.placeholder', false) ?></p>
<?php endif; ?>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
