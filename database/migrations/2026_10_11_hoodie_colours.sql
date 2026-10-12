-- Two B&C colours the owner offers on the hooded hoodie (2026-10-11): Dark
-- Cherry from #29a and Blue Fog from #29b (supplier_prices.php, colors_from).
--
-- Hex values approximate B&C's swatches, kept apart from Burgundy (#6C1D2D)
-- and Light Blue (#A9C8E8). Fine-tune either on /admin/colors.
-- INSERT IGNORE: color_name is UNIQUE, so re-running changes nothing.
INSERT IGNORE INTO available_colors (color_name, color_hex, is_active) VALUES
    ('Dark Cherry', '#561629', 1),
    ('Blue Fog',    '#A3B5C6', 1);
