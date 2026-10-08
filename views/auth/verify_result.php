<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="alert alert-<?= $status === 'success' ? 'success' : 'error' ?> verify-result-alert">
        <?= htmlspecialchars($message) ?>
    </div>

    <?php if (!empty($canResend)): ?>
    <?php // Signed in and still unconfirmed: replace the dead link right here. ?>
    <form method="post" action="/account/resend-verification" class="verify-result-resend">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-block"><?= t('auth.verify.resend') ?></button>
    </form>
    <?php endif; ?>

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
