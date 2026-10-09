<?php
$title        = t('info.privacy.title', false);
$infoTitle    = t('info.privacy.title', false);
$infoSubtitle = t('info.privacy.subtitle', false);
$infoUpdated  = '2026-10-09';
ob_start();
?>
<p><?= t('info.privacy.intro') ?></p>

<?php // The controller, from .env (Business) — the GDPR requires it here. ?>
<?php if ($business['name'] !== ''): ?>
<h2><?= t('info.privacy.who_h') ?></h2>
<p><?= t('info.privacy.who', false, [
    'name'    => e($business['name']),
    'address' => e(implode(', ', $business['address'])),
    'email'   => '<a href="mailto:' . e($business['email']) . '">' . e($business['email']) . '</a>',
]) ?></p>
<?php endif; ?>

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
<ul>
    <li><?= t('info.privacy.share_stripe', false) ?></li>
    <li><?= t('info.privacy.share_acs', false) ?></li>
    <li><?= t('info.privacy.share_osm', false) ?></li>
    <li><?= t('info.privacy.share_email', false) ?></li>
    <li><?= t('info.privacy.share_hosting', false) ?></li>
</ul>

<h2><?= t('info.privacy.h4') ?></h2>
<p><?= t('info.privacy.p4', false) ?></p>

<h2><?= t('info.privacy.h5') ?></h2>
<p><?= t('info.privacy.p5') ?></p>

<h2><?= t('info.privacy.h6') ?></h2>
<p><?= t('info.privacy.p6', false) ?></p>

<h2><?= t('info.privacy.h7') ?></h2>
<p><?= t('info.privacy.p7') ?></p>

<?php // Support chat. Sits before Contact because it ends by pointing there,
      // and because a reader who has just been told their message may leave
      // the EU should find the way to reach a human on the next line. ?>
<h2><?= t('info.privacy.h_assistant') ?></h2>
<p><?= t('info.privacy.p_assistant_1') ?></p>
<p><?= t('info.privacy.p_assistant_2') ?></p>
<p><?= t('info.privacy.p_assistant_3', false) ?></p>
<p><?= t('info.privacy.p_assistant_4') ?></p>

<h2><?= t('info.privacy.h8') ?></h2>
<p><?= t('info.privacy.p8', false) ?></p>

<?php if (Env::get('APP_ENV', 'production') !== 'production'): ?>
<p class="info-disclaimer"><?= t('info.privacy.placeholder', false) ?></p>
<?php endif; ?>
<?php
$infoBody = ob_get_clean();
require View::path('pages/info/_layout');
