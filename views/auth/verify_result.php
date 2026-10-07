<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="alert alert-<?= $status === 'success' ? 'success' : 'error' ?>" style="margin-top:12px;">
        <?= htmlspecialchars($message) ?>
    </div>

    <div class="form-footer">
        <?php if (\Auth::check()): ?>
        <p><a href="/account"><?= t('auth.verify.go_account') ?></a></p>
        <?php else: ?>
        <p><a href="/login"><?= t('auth.verify.sign_in') ?></a></p>
        <?php endif; ?>
    </div>
</div>

</section>

<?php require View::path('layouts/customer_footer'); ?>
