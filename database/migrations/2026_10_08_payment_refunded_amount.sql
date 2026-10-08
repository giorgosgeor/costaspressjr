-- How much of a payment has been refunded, in EUR. Orders are not
-- refundable; the one exception is a customer cancelling before production
-- (OrderCancellation), who gets back what they paid minus Stripe's
-- processing fee. Keeping the refunded amount lets the order page say
-- exactly what came back. Kept in step with Stripe by
-- OrderPlacement::syncPaymentStatus (refunds made in the Dashboard too).
ALTER TABLE order_payments ADD COLUMN refunded_amount DECIMAL(10,2) NULL AFTER amount;
