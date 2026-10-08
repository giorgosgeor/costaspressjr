# costaspressjr

## Requirements

- **PHP 8.5** (production refuses to start on anything older) with the
  `pdo_mysql`, `curl`, `mbstring`, `fileinfo` and `openssl` extensions.
  On the server, use the same PHP 8.5 binary for the cron jobs in `database/`.
- MySQL or MariaDB.
- Apache with `mod_rewrite` and `mod_headers`, the document root pointed at
  `public/` (never the project root), and HTTPS.

Configuration lives in `.env` — start from `.env.example`. Database setup is
in `database/README.md`; a fresh production database comes from
`php database/export_for_production.php`.

## How the code is organised

```
public/              the only folder the web server exposes
  index.php          front controller: every page request starts here
  css/  js/  images/
app/                 PHP classes (no HTML)
  bootstrap.php      loads .env, autoloads classes, loads helpers.php
  routes.php         every URL and the controller method behind it
  helpers.php        t(), e(), money(), web_path(), public_path()
  core/              the small framework: Router, View, Csrf, Auth, I18n, …
  services/          the shop's logic: Pricing, CartPricing, Stripe,
                     OrderPlacement, Pickup, ShopAssistant, …
  models/            database access: Cart, CustomDesign, Favorites
  controllers/       one controller per area of the shop
  controllers/admin/ the admin panel's controllers
  config/  lang/     database connection; en/el translations
views/               HTML templates, grouped like the controllers
  layouts/ partials/ pages/ shop/ cart/ checkout/ account/ auth/ admin/
database/            migrations and command-line scripts
```

A request goes `public/index.php` → `app/routes.php` → a controller method,
which loads data (models, services) and calls
`$this->render('shop/product', ['product' => $product, …])`. The template
`views/shop/product.php` gets exactly those variables and nothing else.

### Where things go

| Kind of code | Lives in | Named |
|---|---|---|
| Page logic (read input, load data, pick a template) | `app/controllers/` | `ShopController::product()` |
| Business rules (prices, payments, orders) | `app/services/` | `CartPricing`, `OrderPlacement` |
| SQL for one kind of record | `app/models/` or the controller | `Cart::contents()` |
| HTML | `views/<area>/<page>.php` | `views/shop/product.php` |
| A page's CSS | `public/css/pages/<page>.css` (`admin/` for the admin) | kebab-case |
| A page's JavaScript | `public/js/pages/<page>.js` (`admin/`, `site/` = every page, `lib/` = shared) | kebab-case |

### Template rules

- **Display only.** Templates echo, loop and branch. No SQL, no business
  rules — compute it in the controller and pass it in.
- **No inline JavaScript.** The site's Content-Security-Policy refuses
  inline `<script>` and `on*` attributes, so they would silently not run.
  - Page data for scripts: `<?= View::json('product-data', [...]) ?>`, read in
    the script with `JSON.parse(document.getElementById('product-data').textContent)`.
  - Scripts: `<?= View::script('/js/pages/product.js') ?>`.
  - Behaviour: `data-on-click="fn" data-args='[12]'`, `data-href`,
    `data-confirm`, `data-fallback` … (all listed in `public/js/site/actions.js`).
    `fn` must be a global function in one of the page's scripts.
- **No `<style>` blocks.** Page CSS goes in `public/css/pages/` and is listed
  before the header include: `$extraCss[] = '/css/pages/x.css'` (loads before
  `mobile.css`, so phone rules win). `$pageCss` loads after `mobile.css`; it
  exists for the stylesheets that started life as inline blocks.
- **Escape output:** `<?= e($value) ?>`; translations with `<?= t('key') ?>`.
- **Includes** use `View::path()`: `require View::path('layouts/customer_header');`
- Full-screen pop-ups go in `$overlays` (see `views/shop/designer.php`) so the
  footer prints them outside `<main>`, above the sticky header.
