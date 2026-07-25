-- Record the Stripe PaymentIntent id against each payment so a single
-- succeeded intent can never be replayed into multiple orders. The UNIQUE
-- index enforces uniqueness even under concurrent checkout submissions
-- (the second insert fails at the database level). NULL is allowed and may
-- repeat, which is fine for legacy rows and any future non-card methods.
ALTER TABLE order_payments ADD COLUMN payment_intent_id VARCHAR(255) NULL;
CREATE UNIQUE INDEX uniq_order_payments_intent ON order_payments (payment_intent_id);
