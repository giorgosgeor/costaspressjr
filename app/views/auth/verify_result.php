<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="auth-brand">
    <a href="/">
        <img src="/images/logo.png" alt="<?= t('site.brand') ?>" style="height:72px; width:auto; object-fit:contain;">
    </a>
</div>

<div class="form-container">
    <h2><?= htmlspecialchars($title) ?></h2>
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

<?php require __DIR__ . '/../layouts/footer.php'; ?>
