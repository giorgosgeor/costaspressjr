-- ============================================================
-- 2026-06-14 — supplier-pricing migration
-- Adds two columns the existing schema can't represent yet:
--   products.supplier_ref     identifies #13, #10, #69, etc.
--   product_variants.unit_price  per (size, color) override so
--                                WHITE / COLOR / BIG / CBIG matrix
--                                can live on the right variant.
-- Both columns are NULLABLE so existing rows are unaffected.
-- Pricing logic stays: final = COALESCE(variant.unit_price,
--                              products.base_price + product_sizes.price_modifier)
-- ============================================================

ALTER TABLE products
    ADD COLUMN supplier_ref VARCHAR(16) NULL DEFAULT NULL AFTER slug,
    ADD UNIQUE KEY uk_products_supplier_ref (supplier_ref);

ALTER TABLE product_variants
    ADD COLUMN unit_price DECIMAL(10,2) NULL DEFAULT NULL AFTER stock_quantity;
