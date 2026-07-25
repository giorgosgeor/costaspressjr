-- Add design position columns to premade_designs
-- Positions stored as percentages relative to the design area dimensions
-- x/y: offset from center as % of half-area (0 = centered)
-- size: design width as % of design area width
ALTER TABLE premade_designs
    ADD COLUMN design_pos_x FLOAT NOT NULL DEFAULT 0 AFTER image_path,
    ADD COLUMN design_pos_y FLOAT NOT NULL DEFAULT 0 AFTER design_pos_x,
    ADD COLUMN design_pos_size FLOAT NOT NULL DEFAULT 55 AFTER design_pos_y;
