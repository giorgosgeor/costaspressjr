# Stripe Checkout — remaining setup

Customers fill in their details and pickup point on `/checkout` as before.
**Continue to payment** then sends them to Stripe's hosted Checkout page, and
after paying they come back to `/checkout/complete`, which shows the order.

## Values to Replace

None. The four values the Checkout Studio prompt leaves as placeholders are
already real in this shop.

**Files containing them:**
- [app/controllers/CheckoutController.php](app/controllers/CheckoutController.php)

| Field | Current value | Why it needs no change |
|-------|---------------|------------------------|
| mode | `payment` | One-time purchases; nothing is sold as a subscription. |
| success_url | `APP_URL/checkout/complete?session_id={CHECKOUT_SESSION_ID}` | The existing confirmation page: it places the order and shows it. |
| cancel_url | `APP_URL/checkout` | Back to the details page, still filled in. |
| line_items | Built from the cart: one line per item (`price_data`, VAT included), plus the ACS pickup fee | Prices depend on the garment, the design and the quantity, so there are no fixed Stripe Price IDs to use. |

## Configured Parameters

These parameters were configured in Checkout Studio and are set in the call.

**Files containing these parameters:**
- [app/controllers/CheckoutController.php](app/controllers/CheckoutController.php)

| Parameter | Value | Note |
|-----------|-------|------|
| ui_mode | `hosted_page` | The project has no Stripe SDK; its own client pins API version `2026-08-26.dahlia` ([app/services/Stripe.php](app/services/Stripe.php)), which accepts `hosted_page` (tested). |
| billing_address_collection | `auto` | |
| phone_number_collection | `enabled: true` | The checkout already asks for a phone number for the pickup, so customers type it twice. |
| automatic_tax | `enabled: true` | Needs Stripe Tax set up (Setup, step 3). Lines are `tax_behavior: inclusive`, so VAT is worked out of the price, never added on top. |
| allow_promotion_codes | `false` | |
| submit_type | `auto` | |
| consent_collection | `terms_of_service: required` | Sent with **live keys only**. It needs the Terms URL in the account's public details (Setup, step 2), which Stripe accepts only with the full business details. With test keys it is left out, so testing works without them. The shop's own terms checkbox applies either way. |
| name_collection | `individual: enabled, optional` | |
| integration_identifier | `hosted_web_0001` | |
| origin_context | `web` | |

Not sent:

| Parameter | Value in Checkout Studio | Why |
|-----------|--------------------------|-----|
| payment_method_collection | `always` | Allowed only in subscription mode. |
| saved_payment_method_options | `payment_method_save: enabled` | Stripe refuses it without a Customer (tested). The shop keeps no Stripe Customers, so a saved card could never be offered back. See Next steps. |

## Setup

1. **Database.** Run `php database/migrate.php`. It adds `pending_checkouts.checkout_ref` and is already applied on the local database.
2. **Terms of service URL** (before going live). Dashboard → Settings → Public details → Terms of service: `https://www.your-domain/terms`. Stripe asks for the full business details on that page, which you will have when you activate the account for live payments. With live keys, "Continue to payment" fails until it is set, and the log says "You cannot collect consent to your terms of service unless a URL is set". With test keys the shop doesn't ask Stripe for terms consent, so this isn't needed for testing.
3. **Stripe Tax.** Dashboard → Settings → Tax:
   - Head office address: the business address in Cyprus.
   - Default product tax code: one that fits clothing.
   - Default tax behavior: *inclusive*, since prices include VAT.
   - Registrations: add Cyprus VAT if the business is VAT-registered. Without a registration, Stripe charges no tax.

   Stripe Tax has its own fee ([stripe.com/tax/pricing](https://stripe.com/tax/pricing)). If the business is not VAT-registered, consider turning automatic tax off in Checkout Studio and removing `automatic_tax` from the call.
4. **Restricted key.** The live `rk_live_…` key needs **Checkout Sessions: Write**. The local test key already has it: the test sessions were created and read with it. The other permissions stay as listed in [DEPLOY.md](DEPLOY.md).
5. **Webhook.** No change. Checkout payments arrive as `payment_intent.succeeded`, as before.
6. **Branding.** Dashboard → Settings → Branding: the logo, colours and font of Stripe's page.
7. **Payment methods.** Dashboard → Settings → Payment methods: Checkout offers the ones switched on there. Google Pay is not shown while automatic tax is on and no shipping address is collected (Stripe's rule).
8. **Environment variables.** No new ones. `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` are used as before. No page loads Stripe.js any more, but the production check still compares `STRIPE_PUBLISHABLE_KEY`'s mode with the secret key's, so keep it set.

## Files

New:
- [database/migrations/2026_10_09_checkout_sessions.sql](database/migrations/2026_10_09_checkout_sessions.sql)
- STRIPE_INTEGRATION_TODO.md (this file)

Changed:
- [app/controllers/CheckoutController.php](app/controllers/CheckoutController.php): `createCheckoutSession()` replaces `createPaymentIntent()`, and `complete()` also handles `?session_id=`.
- [app/services/Stripe.php](app/services/Stripe.php): `createCheckoutSession()` and `retrieveCheckoutSession()` added; `createPaymentIntent()` removed.
- [app/services/OrderPlacement.php](app/services/OrderPlacement.php): finds the pending checkout by `checkout_ref`.
- [app/routes.php](app/routes.php): `POST /api/create-checkout-session` replaces `/api/create-payment-intent`.
- [views/checkout/show.php](views/checkout/show.php), [public/js/pages/checkout.js](public/js/pages/checkout.js) and [public/css/pages/checkout.css](public/css/pages/checkout.css): the payment step is gone, and the button reads "Continue to payment".
- [public/.htaccess](public/.htaccess): Stripe is no longer in the Content Security Policy.
- `app/lang/en.json`, `app/lang/el.json` and [DEPLOY.md](DEPLOY.md).

## How it works

1. On `/checkout`, the customer gives a name, phone and email (guests only), picks the store or an ACS point, and ticks the terms box.
2. **Continue to payment** posts to `/api/create-checkout-session`. The server:
   - checks everything again and prices the cart
   - pauses accounts with many recent cancellations
   - creates the Checkout Session
   - saves the checkout in `pending_checkouts` under a random `checkout_ref`, which also goes into the PaymentIntent's metadata
   - returns the session's URL
3. The browser goes to Stripe's page, and the customer pays there. Cancelling on Stripe's page returns them to `/checkout`.
4. Stripe redirects to `/checkout/complete?session_id=…`. The session's PaymentIntent becomes an order: OrderPlacement finds the checkout by `checkout_ref`. The confirmation page shows the order, and the confirmation email goes out.
5. If the customer never comes back, the `payment_intent.succeeded` webhook places the order, and `database/reconcile_payments.php` catches anything the webhook missed.

Cancellations and refunds work as before: OrderCancellation refunds the PaymentIntent.

## Testing

- Use test keys (`rk_test_…` / `pk_test_…`).
- Test cards (any future expiry date and any CVC):
  - `4242 4242 4242 4242` succeeds
  - `4000 0025 0000 3155` asks for 3D Secure
  - `4000 0000 0000 9995` is declined
- Webhooks locally: run `stripe listen --forward-to localhost:8000/stripe/webhook` and put the `whsec_…` it prints in `STRIPE_WEBHOOK_SECRET`.
- After a test payment, check that:
  - the order appears in `/admin/orders`
  - the confirmation email is in `storage/mail.log` (with `MAIL_TRANSPORT=log`)
  - the order can be cancelled from My Account

## Next steps

- **Saved cards.** Keep a Stripe Customer per account (`users.stripe_customer_id`) and pass it to Checkout. Delete it when the account is deleted, and say so in the Privacy Policy. Then `saved_payment_method_options` can be sent.
- **Asked twice.** The name and phone are asked twice: on our form for the pickup and on Stripe's for the payment. So is the terms box: ours also covers the Privacy and Refund policies, Stripe's covers only the Terms. To ask once, turn off `phone_number_collection`, `name_collection` or `consent_collection` in Checkout Studio and in the call.

## Resources

- https://support.stripe.com
- https://docs.stripe.com/mcp
