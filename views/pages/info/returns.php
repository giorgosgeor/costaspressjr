<?php
$title        = t('info.returns.title', false);
$infoTitle    = t('info.returns.h1_full', false);
$infoSubtitle = t('info.returns.subtitle', false);
$infoUpdated  = '2026-10-09';
ob_start();
?>
<?php // EU consumer law: pre-made designs carry the 14-day right of withdrawal;
      // custom designs are made to the customer's specifications and don't
      // (Consumer Rights Directive, art. 16(c)). OrderCancellation and the
      // order confirmation email (OrderConfirmation) follow the same rules. ?>
<h2><?= t('info.returns.h1') ?></h2>
<p><?= t('info.returns.p1', false) ?></p>
<p><?= t('info.returns.p1_how', false) ?></p>
<p><?= t('info.returns.p1_return') ?></p>
<p><?= t('info.returns.p1_refund') ?></p>

<h2><?= t('info.returns.h_custom') ?></h2>
<p><?= t('info.returns.p_custom', false) ?></p>

<h2><?= t('info.returns.h2') ?></h2>
<p><?= t('info.returns.p2', false) ?></p>

<h2><?= t('info.returns.h3') ?></h2>
<p><?= t('info.returns.p3') ?></p>

<h2><?= t('info.returns.h4') ?></h2>
<p><?= t('info.returns.p4', false) ?></p>
<p><?= t('info.returns.p4_intro') ?></p>
<ul>
    <li><?= t('info.returns.p4_a') ?></li>
    <li><?= t('info.returns.p4_b') ?></li>
    <li><?= t('info.returns.p4_c') ?></li>
</ul>

<h2 id="withdrawal-form"><?= t('info.returns.h_form') ?></h2>
<p><?= t('info.returns.form_intro') ?></p>
<div class="withdrawal-form">
    <?php foreach (WithdrawalForm::lines() as $line): ?>
    <p><?= e($line) ?></p>
    <?php endforeach; ?>
</div>
<?php
$infoBody = ob_get_clean();
require View::path('pages/info/_layout');
