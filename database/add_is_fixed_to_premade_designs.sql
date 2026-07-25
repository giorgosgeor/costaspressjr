-- Add is_fixed column to premade_designs
-- When is_fixed = 1, customers cannot move/resize the design on the product mockup
ALTER TABLE premade_designs
    ADD COLUMN is_fixed TINYINT(1) NOT NULL DEFAULT 0 AFTER active;

-- Set all existing anime designs as fixed by default
UPDATE premade_designs pd
JOIN design_sections ds ON pd.section_id = ds.id
SET pd.is_fixed = 1
WHERE ds.slug = 'anime';
