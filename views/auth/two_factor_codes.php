<?php
// After two-step sign-in is set up: the recovery codes, shown this once.
// After a sign-in with a recovery code: how many are left ($codesLeft).
$title = t(!empty($codes) ? 'auth.2fa.codes.title' : 'auth.2fa.used.title', false);
?>
<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <?php if (!empty($codes)): ?>
        <h1><?= t('auth.2fa.codes.title') ?></h1>
        <p class="form-subtitle"><?= t('auth.2fa.codes.lead') ?></p>
        <ul style="columns:2; list-style:none; margin:16px 0; padding:0; font-family:monospace; font-size:1.1rem; line-height:1.8; user-select:all;">
            <?php foreach ($codes as $code): ?>
                <li><?= e($code) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <h1><?= t('auth.2fa.used.title') ?></h1>
        <p class="form-subtitle"><?= t('auth.2fa.used.lead', true, ['count' => (int)($codesLeft ?? 0)]) ?></p>
    <?php endif; ?>
    <a href="/admin" class="btn btn-block"><?= t('auth.2fa.codes.continue') ?></a>
</div>

</section>

<?php require View::path('layouts/customer_footer'); ?>
