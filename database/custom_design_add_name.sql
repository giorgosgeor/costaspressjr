-- Add 'name' column to custom_design table
ALTER TABLE custom_designs ADD COLUMN name VARCHAR(255) DEFAULT NULL AFTER id;