-- Update custom_designs table to add name and email columns and allow NULL for size_id and color_id
-- Run this script to update your database schema

-- Add name column
ALTER TABLE custom_designs ADD COLUMN name VARCHAR(100) DEFAULT NULL AFTER id;

-- Add email column  
ALTER TABLE custom_designs ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER color_hex;

-- Modify size_id to allow NULL (user selects at cart time)
ALTER TABLE custom_designs MODIFY COLUMN size_id INT DEFAULT NULL;

-- Modify color_id to allow NULL (user selects at cart time)
ALTER TABLE custom_designs MODIFY COLUMN color_id INT DEFAULT NULL;

-- Drop foreign key constraints temporarily if they exist, then re-add with ON DELETE SET NULL
-- Note: You may need to adjust constraint names based on your actual database

-- For size_id
-- ALTER TABLE custom_designs DROP FOREIGN KEY custom_designs_ibfk_3;
-- ALTER TABLE custom_designs ADD CONSTRAINT custom_designs_ibfk_3 FOREIGN KEY (size_id) REFERENCES product_sizes(id) ON DELETE SET NULL;

-- For color_id  
-- ALTER TABLE custom_designs DROP FOREIGN KEY custom_designs_ibfk_4;
-- ALTER TABLE custom_designs ADD CONSTRAINT custom_designs_ibfk_4 FOREIGN KEY (color_id) REFERENCES available_colors(id) ON DELETE SET NULL;
