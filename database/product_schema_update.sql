-- Product Management Schema Update
-- Run this SQL to update your database for the new product management system

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables that might reference products first
DROP TABLE IF EXISTS cart_item_uploads;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS order_uploads;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS product_variants;
DROP TABLE IF EXISTS product_colors;
DROP TABLE IF EXISTS product_sizes;
DROP TABLE IF EXISTS product_type_sizes;
DROP TABLE IF EXISTS available_colors;
DROP TABLE IF EXISTS products;

-- Products table: Each row is a product type (T-shirt, Hoodie, Jacket, etc.)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert the default products
INSERT INTO products (name, slug, description, base_price, active) VALUES
('T-Shirt', 'tshirt', 'Classic cotton t-shirt for custom printing', 15.00, 1),
('Jacket', 'jacket', 'Comfortable jacket for custom designs', 45.00, 1),
('Hoodie', 'hoodie', 'Warm hoodie perfect for custom prints', 35.00, 1),
('Longsleeve T-Shirt', 'longsleeve', 'Long sleeve cotton t-shirt', 20.00, 1),
('Mug', 'mug', 'Ceramic mug for custom printing', 12.00, 1);

-- Product sizes: Available sizes for each product with price modifiers
CREATE TABLE product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size_name VARCHAR(20) NOT NULL,
    size_order INT DEFAULT 0,
    price_modifier DECIMAL(10,2) DEFAULT 0.00,
    is_available TINYINT(1) DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_size (product_id, size_name)
);

-- Insert default sizes for each product
-- T-Shirt sizes (product_id = 1)
INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier) VALUES
(1, 'XS', 1, 0.00), (1, 'S', 2, 0.00), (1, 'M', 3, 0.00), (1, 'L', 4, 0.00),
(1, 'XL', 5, 2.00), (1, '2XL', 6, 3.00), (1, '3XL', 7, 4.00);

-- Jacket sizes (product_id = 2)
INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier) VALUES
(2, 'S', 1, 0.00), (2, 'M', 2, 0.00), (2, 'L', 3, 0.00),
(2, 'XL', 4, 3.00), (2, '2XL', 5, 5.00), (2, '3XL', 6, 7.00);

-- Hoodie sizes (product_id = 3)
INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier) VALUES
(3, 'S', 1, 0.00), (3, 'M', 2, 0.00), (3, 'L', 3, 0.00),
(3, 'XL', 4, 2.00), (3, '2XL', 5, 4.00), (3, '3XL', 6, 6.00);

-- Longsleeve sizes (product_id = 4)
INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier) VALUES
(4, 'XS', 1, 0.00), (4, 'S', 2, 0.00), (4, 'M', 3, 0.00), (4, 'L', 4, 0.00),
(4, 'XL', 5, 2.00), (4, '2XL', 6, 3.00), (4, '3XL', 7, 4.00);

-- Mug sizes (product_id = 5)
INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier) VALUES
(5, '11oz', 1, 0.00), (5, '15oz', 2, 3.00);

-- Available colors table (global color palette)
CREATE TABLE available_colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    color_name VARCHAR(50) NOT NULL UNIQUE,
    color_hex VARCHAR(7) NOT NULL,
    is_active TINYINT(1) DEFAULT 1
);

-- Insert default colors
INSERT INTO available_colors (color_name, color_hex) VALUES
('Black', '#000000'),
('White', '#FFFFFF'),
('Navy Blue', '#1e3a5f'),
('Red', '#dc3545'),
('Royal Blue', '#007bff'),
('Forest Green', '#228b22'),
('Gray', '#6c757d'),
('Charcoal', '#36454f'),
('Maroon', '#800000'),
('Orange', '#fd7e14'),
('Yellow', '#ffc107'),
('Pink', '#e83e8c'),
('Purple', '#6f42c1'),
('Brown', '#8b4513'),
('Olive', '#6b8e23');

-- Product colors: Which colors are available for each product
CREATE TABLE product_colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    color_id INT NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES available_colors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_color (product_id, color_id)
);

-- Assign all colors to all products by default
INSERT INTO product_colors (product_id, color_id, is_available)
SELECT p.id, c.id, 1 FROM products p CROSS JOIN available_colors c;

-- Product variants: Size + Color combinations with stock tracking
-- This binds specific sizes to specific colors for each product
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size_id INT NOT NULL,
    color_id INT NOT NULL,
    stock_quantity INT DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (size_id) REFERENCES product_sizes(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES available_colors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_variant (product_id, size_id, color_id)
);

-- ⚠ DO NOT RE-RUN THIS BLOCK ⚠
-- This was a one-time seed for the very first migration. It cross-joins every
-- size of a product with every color of a product, which is wrong for normal
-- operation — sizes and colors are now picked per-row in the admin modal.
-- Re-running this destroys the per-size color selection. Left here only for
-- historical fidelity of the migration; the statement is commented out.
--
-- INSERT INTO product_variants (product_id, size_id, color_id, stock_quantity, is_available)
-- SELECT ps.product_id, ps.id, pc.color_id, 0, 1
-- FROM product_sizes ps
-- JOIN product_colors pc ON ps.product_id = pc.product_id;

-- Cart items table
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- Cart item uploads table (for custom designs)
CREATE TABLE cart_item_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_item_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_item_id) REFERENCES cart_items(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- Order uploads table
CREATE TABLE order_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
