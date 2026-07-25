<?php
$title        = t('info.faq.title', false);
$infoTitle    = t('info.faq.h1', false);
$infoSubtitle = t('info.faq.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<h2><?= t('info.faq.q1') ?></h2>
<p><?= t('info.faq.a1') ?></p>

<h2><?= t('info.faq.q2') ?></h2>
<p><?= t('info.faq.a2', false) ?></p>

<h2><?= t('info.faq.q3') ?></h2>
<p><?= t('info.faq.a3') ?></p>

<h2><?= t('info.faq.q4') ?></h2>
<p><?= t('info.faq.a4') ?></p>

<h2><?= t('info.faq.q5') ?></h2>
<p><?= t('info.faq.a5', false) ?></p>

<h2><?= t('info.faq.q6') ?></h2>
<p><?= t('info.faq.a6') ?></p>

<h2><?= t('info.faq.q7') ?></h2>
<p><?= t('info.faq.a7', false) ?></p>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
