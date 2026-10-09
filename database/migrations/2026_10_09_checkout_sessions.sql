-- Stripe-hosted Checkout (CheckoutController::createCheckoutSession).
-- The customer now pays on Stripe's own page, and Stripe creates the
-- PaymentIntent only then, so a pending checkout is saved before its intent
-- exists. checkout_ref is our own key for it: it travels in the intent's
-- metadata, and OrderPlacement fills in payment_intent_id from it when the
-- payment arrives (on the return page or through the webhook).
ALTER TABLE pending_checkouts MODIFY payment_intent_id VARCHAR(255) NULL;
ALTER TABLE pending_checkouts ADD COLUMN checkout_ref CHAR(32) NULL AFTER payment_intent_id;
ALTER TABLE pending_checkouts ADD UNIQUE KEY uniq_pending_ref (checkout_ref);
