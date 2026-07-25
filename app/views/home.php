<?php $title = 'Home'; ?>
<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="container home-container">
    <h1>Welcome to Costaspressjr</h1>
    
    <?php if ($loggedIn): ?>
        <p>You are logged in!</p>
        <div class="home-links">
            <a href="/admin" class="btn">Admin Panel</a>
            <form method="post" action="/logout" style="display:inline;">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-danger">Logout</button>
            </form>
        </div>
    <?php else: ?>
        <p>Please login or register to continue.</p>
        <div class="home-links">
            <a href="/login" class="btn">Login</a>
            <a href="/register" class="btn btn-success">Register</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
