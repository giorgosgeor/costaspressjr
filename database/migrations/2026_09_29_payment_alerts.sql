-- One row per alert emailed about a payment (see app/core/PaymentAlert.php).
-- Stripe retries a failed webhook for up to three days and the reconcile job
-- runs every half hour, so without this the same "paid, no order, refund
-- failed" email would arrive dozens of times.
CREATE TABLE IF NOT EXISTS payment_alerts (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    payment_intent_id VARCHAR(255) NOT NULL,
    kind              VARCHAR(30)  NOT NULL,
    created_at        TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_payment_alert (payment_intent_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
