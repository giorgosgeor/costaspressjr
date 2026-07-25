-- Update cart_items table to use only IDs for color and size, and ensure variant_id is present
ALTER TABLE cart_items 
    DROP COLUMN color_name,
    DROP COLUMN color_hex,
    DROP COLUMN size_name;

ALTER TABLE cart_items 
    ADD COLUMN color_id INT DEFAULT NULL AFTER variant_id,
    ADD COLUMN size_id INT DEFAULT NULL AFTER variant_id;

-- If columns already exist, you may need to use MODIFY instead of ADD
-- Ensure foreign keys if desired:
-- ALTER TABLE cart_items ADD CONSTRAINT fk_cart_items_color_id FOREIGN KEY (color_id) REFERENCES available_colors(id);
-- ALTER TABLE cart_items ADD CONSTRAINT fk_cart_items_size_id FOREIGN KEY (size_id) REFERENCES product_sizes(id);
