<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::metaTag() ?>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <title><?= htmlspecialchars($title ?? 'Admin Panel') ?> — Costaspressjr</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/admin.css')) ?>">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url($css)) ?>">
    <?php endforeach; endif; ?>
</head>
<body>
    <?php
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        function adminNavActive(string $path, string $current): string {
            if ($path === '/admin') {
                return $current === '/admin' ? ' active' : '';
            }
            return str_starts_with($current, $path) ? ' active' : '';
        }
    ?>
    <nav class="admin-nav">
        <a href="/admin" class="admin-nav-brand">
            <img src="/images/logo.png" alt="Costaspressjr" style="height:38px; width:auto; object-fit:contain;">
            <span style="font-size:0.75rem; opacity:0.5; margin-left:6px;">Admin</span>
        </a>
        <a href="/admin" class="<?= adminNavActive('/admin', $currentPath) ?>">Dashboard</a>
        <a href="/admin/users" class="<?= adminNavActive('/admin/users', $currentPath) ?>">Users</a>
        <a href="/admin/products" class="<?= adminNavActive('/admin/products', $currentPath) ?>">Products</a>
        <a href="/admin/products/design-area" class="<?= adminNavActive('/admin/products/design-area', $currentPath) ?>">Design Areas</a>
        <a href="/admin/premade" class="<?= adminNavActive('/admin/premade', $currentPath) ?>">Premade</a>
        <a href="/admin/orders" class="<?= adminNavActive('/admin/orders', $currentPath) ?>">Orders</a>
        <a href="/admin/colors" class="<?= adminNavActive('/admin/colors', $currentPath) ?>">Colors</a>
        <a href="/admin/tools/bg-remover" class="<?= adminNavActive('/admin/tools/bg-remover', $currentPath) ?>">BG Remover</a>
        <a href="/admin/tools/image-cropper" class="<?= adminNavActive('/admin/tools/image-cropper', $currentPath) ?>">Cropper</a>
        <form method="post" action="/logout" class="logout-form" style="margin-left:auto;">
            <?= Csrf::field() ?>
            <button type="submit" class="logout-link">Logout</button>
        </form>
    </nav>
    <div class="container">
