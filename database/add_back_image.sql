-- Add back image path to products table for front/back design support
ALTER TABLE products ADD COLUMN back_image_path VARCHAR(255) NULL AFTER image_path;
