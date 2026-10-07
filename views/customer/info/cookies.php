<?php
$title        = t('info.cookies.title', false);
$infoTitle    = t('info.cookies.title', false);
$infoSubtitle = t('info.cookies.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<p><?= t('info.cookies.intro') ?></p>

<h2><?= t('info.cookies.essential') ?></h2>
<p><?= t('info.cookies.essential_lead') ?></p>
<ul>
    <li><?= t('info.cookies.essential_phpsessid', false) ?></li>
    <li><?= t('info.cookies.essential_csrf') ?></li>
</ul>

<h2><?= t('info.cookies.preference') ?></h2>
<p><?= t('info.cookies.preference_lead') ?></p>

<h2><?= t('info.cookies.analytics') ?></h2>
<p><?= t('info.cookies.analytics_lead') ?></p>

<h2><?= t('info.cookies.managing') ?></h2>
<p><?= t('info.cookies.managing_lead') ?></p>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
