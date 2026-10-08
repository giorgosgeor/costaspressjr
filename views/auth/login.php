<?php $title = t('auth.login.title', false); ?>
<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <h1><?= t('auth.login.welcome') ?></h1>
    <p class="form-subtitle"><?= t('auth.login.subtitle') ?></p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php // After a password reset (AuthController::resetPassword). ?>
    <?php if (isset($success)): ?>
        <div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" action="/login">
        <?= Csrf::field() ?>
        <?php if (!empty($redirect)): ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="identifier"><?= t('auth.login.identifier') ?></label>
            <input type="text" id="identifier" name="identifier" placeholder="<?= t('auth.login.identifier_placeholder') ?>" required>
        </div>
        <div class="form-group">
            <label for="password"><?= t('auth.login.password') ?></label>
            <input type="password" id="password" name="password" placeholder="<?= t('auth.login.password_placeholder') ?>" required>
            <?php // The reset flow already existed but nothing linked to it, so it
                  // was unreachable for anyone who had actually forgotten. ?>
            <a href="/forgot-password" class="form-hint-link"><?= t('auth.login.forgot') ?></a>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;"><?= t('auth.login.button') ?></button>
    </form>

    <div class="form-footer">
        <p><?= t('auth.login.no_account') ?> <a href="/register"><?= t('auth.login.create') ?></a></p>
    </div>
</div>

</section>

<?php require View::path('layouts/customer_footer'); ?>
