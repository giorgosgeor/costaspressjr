<?php $title = t('auth.forgot.title', false); ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="auth-brand">
    <a href="/">
        <img src="/images/logo.png" alt="<?= t('site.brand') ?>" style="height:120px; width:auto; object-fit:contain;">
    </a>
</div>

<div class="form-container">
    <h2><?= t('auth.forgot.title') ?></h2>
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

<?php require __DIR__ . '/../layouts/footer.php'; ?>
