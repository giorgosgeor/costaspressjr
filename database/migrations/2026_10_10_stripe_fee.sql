-- Performance audit P8. Stripe's fee for a payment never changes once it is
-- charged, so it is kept here the first time it is read: an order's page
-- then shows the cancellation sums without asking Stripe on every view
-- (OrderCancellation::quote).
ALTER TABLE order_payments ADD COLUMN stripe_fee_cents INT NULL;
