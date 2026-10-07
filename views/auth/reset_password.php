<?php $title = 'Reset Password'; ?>
<?php $bodyClass = 'auth-page'; $noindex = true; ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="auth-section">
<div class="form-container">
    <h1>Reset Password</h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php $token = htmlspecialchars((string)($_GET['token'] ?? $_POST['token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>

    <?php if (!isset($error) || strpos($error ?? '', 'Invalid or missing') === false): ?>
    <form method="post" action="/reset-password">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= $token ?>">
        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" placeholder="New password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;">Reset Password</button>
    </form>
    <?php endif; ?>

    <div class="form-footer">
        <p><a href="/login">Back to login</a></p>
    </div>
</div>

</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
