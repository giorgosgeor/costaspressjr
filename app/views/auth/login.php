<?php $title = t('auth.login.title', false); ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="auth-brand">
    <a href="/">
        <img src="/images/logo.png" alt="<?= t('site.brand') ?>" style="height:120px; width:auto; object-fit:contain;">
    </a>
</div>

<div class="form-container">
    <h2><?= t('auth.login.welcome') ?></h2>
    <p class="form-subtitle"><?= t('auth.login.subtitle') ?></p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
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
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;"><?= t('auth.login.button') ?></button>
    </form>

    <div class="form-footer">
        <p><?= t('auth.login.no_account') ?> <a href="/register"><?= t('auth.login.create') ?></a></p>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
