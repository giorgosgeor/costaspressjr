<?php $title = t('auth.2fa.setup.title', false); ?>
<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="auth-section">
<div class="form-container">
    <h1><?= t('auth.2fa.setup.title') ?></h1>
    <p class="form-subtitle"><?= t('auth.2fa.setup.lead') ?></p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <ol style="padding-left:1.2em; line-height:1.6;">
        <li><?= t('auth.2fa.setup.step1') ?></li>
        <li>
            <?= t('auth.2fa.setup.step2') ?>
            <?php // user-select:all: one click selects the whole key for copying. ?>
            <code style="display:block; margin:8px 0; padding:10px 12px; font-size:1.15rem; letter-spacing:.08em; word-break:break-all; user-select:all; background:rgba(0,0,0,.05); border-radius:6px;"><?= e($secret) ?></code>
            <a href="<?= e($uri) ?>"><?= t('auth.2fa.setup.on_phone') ?></a>
        </li>
        <li><?= t('auth.2fa.setup.step3') ?></li>
    </ol>

    <form method="post" action="/login/two-factor/setup">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="code"><?= t('auth.2fa.code') ?></label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;"><?= t('auth.2fa.setup.button') ?></button>
    </form>
</div>

</section>

<?php require View::path('layouts/customer_footer'); ?>
