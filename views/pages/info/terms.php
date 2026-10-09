<?php
$title         = t('info.terms.title', false);
$infoTitle     = t('info.terms.title', false);
$infoSubtitle  = t('info.terms.subtitle', false);
$infoUpdated   = '08/10/2026';
ob_start();
?>
<p><?= t('info.terms.intro') ?></p>

<?php // Who is selling, from .env (Business) — required on a web shop. ?>
<?php if ($business['name'] !== ''): ?>
<h2><?= t('info.terms.who_h') ?></h2>
<p><?= t('info.terms.who', false, [
    'name'    => e($business['name']),
    'address' => e(implode(', ', $business['address'])),
    'email'   => '<a href="mailto:' . e($business['email']) . '">' . e($business['email']) . '</a>',
]) ?>
<?php if ($business['registration'] !== ''): ?><br><?= t('footer.reg_no', true, ['no' => $business['registration']]) ?><?php endif; ?>
<?php if ($business['vat'] !== ''): ?><br><?= t('footer.vat_no', true, ['no' => $business['vat']]) ?><?php endif; ?>
</p>
<?php endif; ?>

<h2><?= t('info.terms.h1') ?></h2>
<p><?= t('info.terms.p1') ?></p>

<h2><?= t('info.terms.h2') ?></h2>
<p><?= t('info.terms.p2') ?></p>

<h2><?= t('info.terms.h3') ?></h2>
<p><?= t('info.terms.p3') ?></p>

<h2><?= t('info.terms.h4') ?></h2>
<p><?= t('info.terms.p4') ?></p>

<h2><?= t('info.terms.h5') ?></h2>
<p><?= t('info.terms.p5', false) ?></p>

<h2><?= t('info.terms.h6') ?></h2>
<p><?= t('info.terms.p6') ?></p>

<h2><?= t('info.terms.h7') ?></h2>
<p><?= t('info.terms.p7') ?></p>

<h2><?= t('info.terms.h8') ?></h2>
<p><?= t('info.terms.p8') ?></p>

<h2><?= t('info.terms.h9') ?></h2>
<p><?= t('info.terms.p9', false) ?></p>

<h2><?= t('info.terms.h10') ?></h2>
<p><?= t('info.terms.p10') ?></p>

<?php if (Env::get('APP_ENV', 'production') !== 'production'): ?>
<p class="info-disclaimer"><?= t('info.terms.placeholder', false) ?></p>
<?php endif; ?>
<?php
$infoBody = ob_get_clean();
require View::path('pages/info/_layout');
