<?php $title = 'Forgot Password'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="auth-brand">
    <a href="/">
        <img src="/images/logo.png" alt="Costaspressjr" style="height:120px; width:auto; object-fit:contain;">
    </a>
</div>

<div class="form-container">
    <h2>Forgot Password</h2>
    <p class="form-subtitle">Enter your email and we'll send you a reset link.</p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php else: ?>
    <form method="post" action="/forgot-password">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="btn btn-block" style="margin-top:8px;">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="form-footer">
        <p><a href="/login">Back to login</a></p>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
