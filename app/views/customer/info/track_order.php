<?php
$title        = t('info.track.title', false);
$infoTitle    = t('info.track.h1', false);
$infoSubtitle = t('info.track.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<?php if (\Auth::check()): ?>
<p><?= t('info.track.signed_in') ?></p>
<p><a href="/account" class="btn"><?= t('info.track.go') ?></a></p>
<?php else: ?>
<p><?= t('info.track.guest_intro') ?></p>
<p><?= t('info.track.guest_lead', false) ?></p>
<?php endif; ?>

<h2><?= t('info.track.h2') ?></h2>
<p><?= t('info.track.p2') ?></p>

<h2><?= t('info.track.h3') ?></h2>
<p><?= t('info.track.p3', false) ?></p>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
