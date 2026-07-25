-- ============================================================
-- 2026-06-14 — size-chart column
-- Adds products.size_chart_image so each product can point to its
-- own size guide image under /images/size-charts/*.png.
-- Nullable: existing rows stay as-is until the seed script sets
-- the path.
-- ============================================================

ALTER TABLE products
    ADD COLUMN size_chart_image VARCHAR(255) NULL DEFAULT NULL AFTER image_path;
