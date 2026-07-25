-- Improved Custom Design Schema
-- This stores uploaded images as separate files and keeps full element data

-- =============================
-- 1. Add email column to custom_designs if not exists
-- =============================
ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER name;

-- =============================
-- 2. Create table for storing design uploaded images
-- =============================
CREATE TABLE IF NOT EXISTS custom_design_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    design_id INT NOT NULL,
    element_id VARCHAR(50) NOT NULL COMMENT 'Matches element id from JS (e.g., element-1)',
    original_filename VARCHAR(255) DEFAULT NULL,
    stored_file_path VARCHAR(255) NOT NULL COMMENT 'Path to the stored image file on disk',
    file_size INT DEFAULT 0 COMMENT 'File size in bytes',
    mime_type VARCHAR(50) DEFAULT 'image/png',
    view_placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    position_x DECIMAL(10,2) DEFAULT 0,
    position_y DECIMAL(10,2) DEFAULT 0,
    width DECIMAL(10,2) DEFAULT 80,
    height DECIMAL(10,2) DEFAULT 80,
    rotation DECIMAL(5,2) DEFAULT 0,
    is_flipped TINYINT(1) DEFAULT 0,
    color_overlay VARCHAR(10) DEFAULT NULL COMMENT 'Hex color for overlay effect',
    bg_removed TINYINT(1) DEFAULT 0 COMMENT 'Background removed flag',
    layer_order INT DEFAULT 0 COMMENT 'Z-index order in design',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (design_id) REFERENCES custom_designs(id) ON DELETE CASCADE,
    INDEX idx_design_id (design_id),
    INDEX idx_element_id (element_id)
);

-- =============================
-- 3. Create table for storing design text elements
-- =============================
CREATE TABLE IF NOT EXISTS custom_design_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    design_id INT NOT NULL,
    element_id VARCHAR(50) NOT NULL COMMENT 'Matches element id from JS',
    text_content TEXT NOT NULL,
    font_family VARCHAR(100) DEFAULT 'Arial, sans-serif',
    font_size INT DEFAULT 24,
    text_color VARCHAR(10) DEFAULT '#000000',
    is_bold TINYINT(1) DEFAULT 0,
    is_italic TINYINT(1) DEFAULT 0,
    is_underline TINYINT(1) DEFAULT 0,
    view_placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    position_x DECIMAL(10,2) DEFAULT 0,
    position_y DECIMAL(10,2) DEFAULT 0,
    layer_order INT DEFAULT 0 COMMENT 'Z-index order in design',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (design_id) REFERENCES custom_designs(id) ON DELETE CASCADE,
    INDEX idx_design_id (design_id),
    INDEX idx_element_id (element_id)
);

-- =============================
-- 4. Add preview image path to custom_designs
-- =============================
ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS preview_front_path VARCHAR(255) DEFAULT NULL AFTER color_hex;
ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS preview_back_path VARCHAR(255) DEFAULT NULL AFTER preview_front_path;

-- =============================
-- 5. Increase elements_json size for JSON data (backup/compatibility)
-- =============================
ALTER TABLE custom_designs MODIFY COLUMN elements_json LONGTEXT;
