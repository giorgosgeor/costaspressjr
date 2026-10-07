<?php $title = 'Dashboard'; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="admin-header">
    <h1>Dashboard</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3><?= $userCount ?></h3>
        <p>Total Users</p>
    </div>
    <div class="stat-card">
        <h3><?= $productCount ?></h3>
        <p>Total Products</p>
    </div>
    <div class="stat-card">
        <h3><?= $orderCount ?></h3>
        <p>Total Orders</p>
    </div>
</div>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
