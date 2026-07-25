<!DOCTYPE html>
<html lang="<?= htmlspecialchars(I18n::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::metaTag() ?>
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <title><?= htmlspecialchars($title ?? t('site.brand', false)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/style.css')) ?>">
    <style>
    .auth-lang-switcher {
        position: fixed;
        top: 18px;
        right: 18px;
        display: inline-flex;
        align-items: center;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 9999px;
        overflow: hidden;
        font-size: 0.82rem;
        font-weight: 600;
        z-index: 100;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .auth-lang-switcher a {
        padding: 6px 12px;
        color: var(--ink-soft);
        text-decoration: none;
    }
    .auth-lang-switcher a:hover { color: var(--ink); background: #f4f4f6; }
    .auth-lang-switcher a.active { background: var(--spot); color: #fff; }
    .auth-lang-switcher .sep { width: 1px; height: 14px; background: var(--border); }
    </style>
</head>
<body class="auth-body">
    <div class="auth-lang-switcher" role="group" aria-label="Language">
        <?php $loc = I18n::locale(); ?>
        <a href="/lang/en" class="<?= $loc === 'en' ? 'active' : '' ?>" hreflang="en">EN</a>
        <span class="sep" aria-hidden="true"></span>
        <a href="/lang/el" class="<?= $loc === 'el' ? 'active' : '' ?>" hreflang="el">EL</a>
    </div>
