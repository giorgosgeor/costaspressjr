-- order_payments.card_cvv is left over from the card form that predates
-- Stripe. No code writes it any more, but the column — and the CVVs typed
-- into that form — survive on any database database/create_order_payments.php
-- was never run against. Storing a CVV after authorisation is forbidden
-- outright by PCI DSS (req. 3.3.1), test data or not, so the column goes.
-- The runner treats "Can't DROP" as already applied.
ALTER TABLE order_payments DROP COLUMN card_cvv;
