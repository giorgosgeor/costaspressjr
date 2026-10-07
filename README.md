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
