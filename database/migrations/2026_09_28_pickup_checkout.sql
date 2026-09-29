-- Collection-only fulfilment. There is no home delivery: a customer collects
-- from the store (free) or from an ACS point (fee). ACS points are ACS
-- stores, shop-in-a-shop counters and Smartpoint lockers; they come from the
-- ACS_Stations web service (database/sync_acs_points.php) or are entered by
-- hand in /admin/pickup-points.

CREATE TABLE IF NOT EXISTS pickup_points (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    carrier         VARCHAR(20)  NOT NULL DEFAULT 'acs',
    kind            VARCHAR(20)  NOT NULL DEFAULT 'store',
    external_code   VARCHAR(40)  NULL,
    station_code    VARCHAR(20)  NULL,
    branch_code     VARCHAR(20)  NULL,
    name            VARCHAR(150) NOT NULL,
    address         VARCHAR(255) NOT NULL,
    city            VARCHAR(100) NULL,
    zipcode         VARCHAR(10)  NULL,
    phone           VARCHAR(80)  NULL,
    hours           VARCHAR(120) NULL,
    hours_saturday  VARCHAR(120) NULL,
    lat             DECIMAL(9,6) NOT NULL,
    lng             DECIMAL(9,6) NOT NULL,
    source          VARCHAR(10)  NOT NULL DEFAULT 'manual',
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pickup_external (carrier, external_code),
    KEY idx_pickup_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- What the customer chose at checkout, keyed by the PaymentIntent it was
-- created with. It has to live in the database rather than the session:
-- Revolut Pay and PayPal can return the customer in a different browser, and
-- the Stripe webhook has no session at all, yet both must be able to place
-- the order.
CREATE TABLE IF NOT EXISTS pending_checkouts (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    payment_intent_id VARCHAR(255)  NOT NULL,
    user_id           INT           NOT NULL,
    cart_id           INT           NOT NULL,
    delivery_method   VARCHAR(20)   NOT NULL,
    pickup_point_id   INT           NULL,
    pickup_point      TEXT          NULL,
    contact_name      VARCHAR(100)  NOT NULL,
    contact_phone     VARCHAR(30)   NOT NULL,
    contact_email     VARCHAR(254)  NULL,
    shipping_fee      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_cents      INT           NOT NULL,
    created_at        TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pending_intent (payment_intent_id),
    KEY idx_pending_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pickup_point is a JSON snapshot (name, address, code) taken at order time,
-- so the order still says where to send it after the point is renamed or
-- removed from the network.
ALTER TABLE orders ADD COLUMN delivery_method VARCHAR(20) NULL AFTER shipping_address;
ALTER TABLE orders ADD COLUMN pickup_point_id INT NULL AFTER delivery_method;
ALTER TABLE orders ADD COLUMN pickup_point TEXT NULL AFTER pickup_point_id;
ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER pickup_point;
