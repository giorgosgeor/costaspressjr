-- Security audit S3. WriteLimit counts design saves, cart lines and preview
-- images per IP address over the last hour, so one visitor cannot fill the
-- disk. IPs are hashed the same way as in login_attempts.
CREATE TABLE IF NOT EXISTS write_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip_hash    CHAR(64)    NOT NULL,
    kind       VARCHAR(16) NOT NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_write_log_ip (ip_hash, kind, created_at),
    KEY idx_write_log_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A design an order or a cart still uses is never deleted (CustomDesign::delete,
-- database/cleanup_guests.php). These make that check an index lookup.
ALTER TABLE order_items ADD INDEX idx_order_items_design (design_id);
ALTER TABLE cart_items ADD INDEX idx_cart_items_design (design_id);
