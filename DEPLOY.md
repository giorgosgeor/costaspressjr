# Going live

The steps to put the shop on a server, in order. Every one of them is needed;
the site itself refuses to start in production until the settings it can't do
without are in place (it says which in the PHP error log, and `/health`
answers 500 until then).

## 0. Have these ready

| What | Where it comes from |
|---|---|
| Hosting with **PHP 8.5**, MySQL/MariaDB, Apache (`mod_rewrite`, `mod_headers`), HTTPS, SSH or a cron panel | your host |
| The domain, pointing at the host, with an SSL certificate | your registrar / host |
| A **live** Stripe account (identity and bank details accepted) | dashboard.stripe.com |
| An SMTP provider (e.g. Brevo, free tier) and access to the domain's DNS | the provider |
| The business's legal name, address, email (phone, opening hours, registration and VAT numbers if any) | you |

## 1. Code

1. Upload the project (or `git clone` it) **outside** the web root, and point
   the domain's document root at its `public/` folder — never at the project
   folder itself.
2. Copy `php.ini` to `public/.user.ini` (upload limits for designs).
3. Make `storage/`, `public/images/` and `public/uploads/` writable by the web
   server (customers' designs and previews, admin uploads).

## 2. Database

1. On your computer: `php database/export_for_production.php`. It writes
   `storage/export/<date>/` with `database.sql` (the catalogue, no test
   customers or orders) and an `images/` folder.
2. On the host: create an empty database and a user for it; import
   `database.sql` into it.
3. Upload the **contents** of `images/` into `public/` on the server (product
   photos and size charts are not in git).
4. On the server: `php database/migrate.php` — applies anything newer than
   the export. Run it again after every update.
5. First admin: `php database/create_admin.php --email=you@… --username=…`
   (asks for the password). No shell? Export with `--with-admin` instead,
   after giving that admin a strong password. Admins also sign in with a
   code from an authenticator app (Google Authenticator, Microsoft
   Authenticator, 2FAS…): the first sign-in sets it up and shows ten
   recovery codes to keep somewhere safe. Phone and codes both lost:
   `php database/reset_admin_2fa.php --email=you@…`, or in phpMyAdmin set
   `totp_secret` and `totp_enabled_at` to NULL on that admin's row.

## 3. `.env`

Copy `.env.example` to `.env` on the server (never into git) and fill in:

- `APP_ENV=production`, `APP_URL=https://www.your-domain` (no trailing slash)
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `BUSINESS_NAME`, `BUSINESS_ADDRESS`, `BUSINESS_EMAIL` (required) and
  `BUSINESS_PHONE`, `BUSINESS_HOURS`, `BUSINESS_REG_NO`, `BUSINESS_VAT_NO`
- Mail: `MAIL_TRANSPORT=smtp`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`,
  `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM_ADDRESS` (an address on your
  domain), `CONTACT_EMAIL` (the inbox you read), `ALERT_EMAIL`
- Stripe: `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`
  (step 4). Leave `STRIPE_ALLOW_TEST_KEYS` empty.
- Behind Cloudflare or another proxy only: `TRUSTED_PROXIES` and
  `CLIENT_IP_HEADER` (see `.env.example`). Without them the sign-in limits
  treat every visitor as one, and a few failed sign-ins lock everyone out.
- Delivery: `ACS_PICKUP_FEE=3.00` (required). Every order goes to the ACS
  point or Smartpoint locker the customer picks, so the checkout also needs
  active ACS points (/admin/pickup-points, or the sync in step 6).

## 4. Stripe (live mode)

1. **Restricted key** (Developers → API keys → Create restricted key) with
   only: **Checkout Sessions *write*** (customers pay on Stripe's hosted
   Checkout page), PaymentIntents *write*, Refunds *write*, Charges *read*,
   Disputes *read*, **Balance transactions *read*** (a cancelled custom-design
   order is refunded minus Stripe's fee, which is read from there). That
   `rk_live_…` key is `STRIPE_SECRET_KEY`; the `pk_live_…` key is
   `STRIPE_PUBLISHABLE_KEY`.
2. **Webhook** (Developers → Webhooks → Add endpoint):
   `https://www.your-domain/stripe/webhook`, events `payment_intent.succeeded`,
   `charge.refunded`, `charge.dispute.created`, `charge.dispute.closed`. Its
   signing secret (`whsec_…`) is `STRIPE_WEBHOOK_SECRET`.
3. Settings → Payment methods: switch on the ones to offer.
4. Settings → Public details: **Terms of service URL**
   `https://www.your-domain/terms`. With live keys the Checkout page asks
   customers to accept it, and Stripe refuses to open the page without it.
   (Test keys skip that, so testing doesn't need it.)
5. Settings → Branding: logo and colours for the Checkout page.

Stripe Tax isn't needed: the shop's prices already include VAT, and Stripe
charges them as they are.

`STRIPE_INTEGRATION_TODO.md` has the details of each.

## 5. Email that arrives

In the domain's DNS, add the SPF and DKIM records your SMTP provider gives
you, and a DMARC record (`_dmarc`, e.g. `v=DMARC1; p=none; rua=mailto:you@…`).
Without them verification and password-reset emails land in spam.

## 6. Cron

```
0,30 * * * *  php /path/to/database/reconcile_payments.php >> /path/to/storage/reconcile.log 2>&1
15 4 * * *    php /path/to/database/cleanup_guests.php --apply >> /path/to/storage/cleanup.log 2>&1
```

Use the host's PHP 8.5 binary. The first places any paid order the webhook
missed and emails `ALERT_EMAIL` if something needs a person. The second
deletes the carts, designs and images of guests (shoppers without an account)
untouched for 30 days; a guest with an order is never deleted. With ACS
credentials, also `php database/sync_acs_points.php` once a day.

## 7. Before telling anyone

1. `https://www.your-domain/health` answers `{"status":"ok"}`; `http://`
   redirects to `https://`.
2. Contact page, footer, Terms and Privacy show the business details.
3. Register an account: the verification email arrives (not in spam).
4. Place a real order with your own card — the smallest one, to an ACS point.
   "Continue to payment" opens Stripe's page; after paying you land on the
   order confirmation, and the confirmation email arrives. The order appears
   in /admin/orders and the Stripe Dashboard shows the payment.
5. Open that order from My Account and cancel it. A custom-design order comes
   back minus Stripe's fee, one with a pre-made design in full, and an email
   should reach `ALERT_EMAIL`. (If the cancel fails with a permissions error,
   the restricted key is missing Balance transactions *read*.)
6. Send a message from the contact form; it reaches `CONTACT_EMAIL`.
7. The security headers and compression are on:

   ```
   curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" https://www.your-domain/
   ```

   The reply must include `Content-Security-Policy`,
   `Strict-Transport-Security`, `X-Content-Type-Options: nosniff` and
   `Content-Encoding: gzip`. `public/.htaccess` sets them, using Apache's
   mod_headers and mod_deflate. Without mod_headers every page answers 500
   and the error log says `Invalid command 'Header'`: ask the host to enable
   it. Without `Content-Encoding: gzip`, ask for mod_deflate.
8. Speed, now that real visitors can be measured: run PageSpeed Insights
   (pagespeed.web.dev) on the home page, `/shop/select_product` and
   `/shop/custom`, and Lighthouse (Chrome DevTools) on `/checkout` with
   something in your cart. Write down LCP, INP and CLS. Targets: LCP at most
   2.5 s, INP at most 200 ms, CLS at most 0.1.

## After launch

- Point an uptime monitor (e.g. UptimeRobot) at `/health`.
- Back up the database daily, and `public/images/designs/` and `public/uploads/`
  (customers' uploads).
- After each update: upload the code, then `php database/migrate.php`.
