<?php $title = t('auth.2fa.title', false); ?>
<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <h1><?= t('auth.2fa.title') ?></h1>
    <p class="form-subtitle"><?= t('auth.2fa.lead') ?></p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login/two-factor">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="code"><?= t('auth.2fa.code') ?></label>
            <?php // Not inputmode="numeric": a recovery code has letters. ?>
            <input type="text" id="code" name="code" autocomplete="one-time-code" maxlength="11" required autofocus>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;"><?= t('auth.2fa.button') ?></button>
    </form>

    <div class="form-footer">
        <p><?= t('auth.2fa.recovery_hint') ?></p>
    </div>
</div>

</section>

<?php require View::path('layouts/customer_footer'); ?>
