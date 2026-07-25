<?php $title = t('auth.register.title', false); ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="auth-brand">
    <a href="/">
        <img src="/images/logo.png" alt="<?= t('site.brand') ?>" style="height:72px; width:auto; object-fit:contain;">
    </a>
</div>

<div class="form-container">
    <h2><?= t('auth.register.welcome') ?></h2>
    <p class="form-subtitle"><?= t('auth.register.subtitle') ?></p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/register">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="username"><?= t('auth.register.username') ?></label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" placeholder="<?= t('auth.register.username_placeholder') ?>" minlength="3" maxlength="32" pattern="[A-Za-z0-9_.\-]+" title="<?= t('auth.register.username_title') ?>" required>
        </div>
        <div class="form-group">
            <label for="email"><?= t('auth.register.email') ?></label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="<?= t('auth.register.email_placeholder') ?>" maxlength="254" required>
        </div>
        <div class="form-group">
            <label for="phone"><?= t('auth.register.phone') ?> <span style="color:rgba(255,255,255,0.35); font-weight:400;"><?= t('auth.register.phone_optional') ?></span></label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" placeholder="<?= t('auth.register.phone_placeholder') ?>" pattern="[0-9\-\+\s\(\)]{7,20}" title="<?= t('auth.register.phone_title') ?>">
        </div>
        <div class="form-group">
            <label for="password"><?= t('auth.register.password') ?></label>
            <input type="password" id="password" name="password" placeholder="<?= t('auth.register.password_placeholder') ?>" minlength="8" maxlength="200" required>
            <small class="form-hint-light"><?= t('auth.register.password_hint') ?></small>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;"><?= t('auth.register.button') ?></button>
    </form>

    <div class="form-footer">
        <p><?= t('auth.register.have_account') ?> <a href="/login"><?= t('auth.register.sign_in') ?></a></p>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
