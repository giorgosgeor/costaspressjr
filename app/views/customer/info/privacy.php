<?php
$title        = t('info.privacy.title', false);
$infoTitle    = t('info.privacy.title', false);
$infoSubtitle = t('info.privacy.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<p><?= t('info.privacy.intro') ?></p>

<h2><?= t('info.privacy.h1') ?></h2>
<ul>
    <li><?= t('info.privacy.account_data', false) ?></li>
    <li><?= t('info.privacy.order_data', false) ?></li>
    <li><?= t('info.privacy.designs', false) ?></li>
    <li><?= t('info.privacy.usage', false) ?></li>
</ul>

<h2><?= t('info.privacy.h2') ?></h2>
<p><?= t('info.privacy.p2') ?></p>

<h2><?= t('info.privacy.h3') ?></h2>
<p><?= t('info.privacy.p3') ?></p>

<h2><?= t('info.privacy.h4') ?></h2>
<p><?= t('info.privacy.p4', false) ?></p>

<h2><?= t('info.privacy.h5') ?></h2>
<p><?= t('info.privacy.p5') ?></p>

<h2><?= t('info.privacy.h6') ?></h2>
<p><?= t('info.privacy.p6', false) ?></p>

<h2><?= t('info.privacy.h7') ?></h2>
<p><?= t('info.privacy.p7') ?></p>

<h2><?= t('info.privacy.h8') ?></h2>
<p><?= t('info.privacy.p8', false) ?></p>

<?php if (Env::get('APP_ENV', 'production') !== 'production'): ?>
<p class="info-disclaimer"><?= t('info.privacy.placeholder', false) ?></p>
<?php endif; ?>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
