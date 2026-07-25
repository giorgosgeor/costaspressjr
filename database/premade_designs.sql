-- Design Sections (Anime, Sports, Music, etc.)
CREATE TABLE IF NOT EXISTS design_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Premade Designs
CREATE TABLE IF NOT EXISTS premade_designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_path VARCHAR(255) NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES design_sections(id) ON DELETE CASCADE
);

-- Design-Product Association (which products a design is available on)
CREATE TABLE IF NOT EXISTS design_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    design_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (design_id) REFERENCES premade_designs(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_design_product (design_id, product_id)
);

-- Insert default sections
INSERT INTO design_sections (name, slug, icon) VALUES 
('Anime', 'anime', '🎌');

-- Add more sections later as needed:
-- INSERT INTO design_sections (name, slug, icon) VALUES ('Sports', 'sports', '⚽');
-- INSERT INTO design_sections (name, slug, icon) VALUES ('Music', 'music', '🎵');
-- INSERT INTO design_sections (name, slug, icon) VALUES ('Gaming', 'gaming', '🎮');
