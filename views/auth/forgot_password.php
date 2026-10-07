<?php $title = t('auth.forgot.title', false); ?>
<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <h1><?= t('auth.forgot.title') ?></h1>
    <p class="form-subtitle"><?= t('auth.forgot.lead') ?></p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div>
    <?php else: ?>
    <form method="post" action="/forgot-password">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="email"><?= t('auth.forgot.email') ?></label>
            <input type="email" id="email" name="email" autocomplete="email"
                   placeholder="<?= t('auth.forgot.email_placeholder') ?>" required>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;"><?= t('auth.forgot.button') ?></button>
    </form>
    <?php endif; ?>

    <div class="form-footer">
        <p><a href="/login"><?= t('auth.forgot.back') ?></a></p>
    </div>
</div>

</section>

<?php require View::path('layouts/customer_footer'); ?>
