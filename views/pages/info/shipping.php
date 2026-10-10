<?php
$title        = t('info.shipping.title', false);
$infoTitle    = t('info.shipping.title', false);
$infoSubtitle = t('info.shipping.subtitle', false);
$infoUpdated  = '2026-10-09';
// Every order goes to an ACS point for this fixed fee (Pickup).
$deliveryFee  = Pickup::acsFee();
ob_start();
?>
<h2><?= t('info.shipping.h1') ?></h2>
<p><?= t('info.shipping.p1', false) ?></p>

<h2><?= t('info.shipping.h2') ?></h2>
<p><?= t('info.shipping.p2_lead') ?></p>
<ul>
    <li><?= t('info.shipping.p2_a') ?></li>
</ul>
<p><?= t('info.shipping.p2_after', false) ?></p>

<?php if ($deliveryFee !== null): ?>
<h2><?= t('info.shipping.h3') ?></h2>
<p><?= t('info.shipping.p3', true, ['fee' => money($deliveryFee)]) ?></p>
<?php endif; ?>

<h2><?= t('info.shipping.h4') ?></h2>
<p><?= t('info.shipping.p4', false) ?></p>

<?php // The Customs and Import Taxes section was removed with the move to
      // Cyprus-only delivery: there is no destination country for duty to be
      // charged in, so the section could only confuse. ?>

<?php
$infoBody = ob_get_clean();
require View::path('pages/info/_layout');
