-- Add back design image and back position columns to premade_designs
ALTER TABLE premade_designs
    ADD COLUMN back_image_path VARCHAR(255) NULL AFTER design_pos_size,
    ADD COLUMN design_pos_back_x FLOAT NOT NULL DEFAULT 0 AFTER back_image_path,
    ADD COLUMN design_pos_back_y FLOAT NOT NULL DEFAULT 0 AFTER design_pos_back_x,
    ADD COLUMN design_pos_back_size FLOAT NOT NULL DEFAULT 55 AFTER design_pos_back_y;
