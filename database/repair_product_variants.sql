-- One-shot repair for the "every size has every color" corruption caused by
-- the seed cross-join in product_schema_update.sql being re-run.
--
-- HOW TO USE:
--   1. Back up the database first.
--   2. Run this script. It deletes ALL rows in product_variants.
--   3. Open each product in the admin Edit modal and click "Save Changes".
--      That re-inserts the correct per-size color mapping from the form's
--      hidden CSV input. Without that re-save, the product will have no
--      colors on the customer page until you do.
--
-- This is intentionally destructive but reversible: the admin save is the
-- canonical source of truth for the size↔color matrix, so a fresh save fixes
-- each product.

DELETE FROM product_variants;
-- product_colors holds a flat "this product uses these colors" list and is
-- harmless to keep, but if you also want it rebuilt from the next save:
DELETE FROM product_colors;
