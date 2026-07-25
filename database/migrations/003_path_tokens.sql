-- Per-design / per-cart-item random folder token, used for the upload and
-- preview directory names. Replaces predictable {id} folder paths so a leaked
-- URL doesn't expose neighbouring users' artwork.
--
-- The runner swallows "Duplicate column" errors so re-running this is safe.

ALTER TABLE custom_designs
    ADD COLUMN path_token CHAR(32) NULL DEFAULT NULL AFTER id;

ALTER TABLE cart_items
    ADD COLUMN path_token CHAR(32) NULL DEFAULT NULL AFTER id;
