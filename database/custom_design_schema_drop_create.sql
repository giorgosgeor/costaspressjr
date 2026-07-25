-- Custom Design Schema Update: Drop and Create Tables (except products and carts)
-- This script will DROP and CREATE all relevant tables except 'products' and 'carts'.

-- =============================
-- 1. Drop tables if they exist
-- =============================
DROP TABLE IF EXISTS order_item_texts;
DROP TABLE IF EXISTS order_item_uploads;
DROP TABLE IF EXISTS order_item_designs;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS order_payments;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_item_uploads;
DROP TABLE IF EXISTS cart_items;

-- =============================
-- 2. Orders table (if not exists) - base order info
-- =============================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('pending', 'processing', 'in-transit', 'delivered', 'cancelled') DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_products INT DEFAULT 0,
    shipping_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================
-- 2.1 Order payments - basic payment details (placeholder)
-- =============================
CREATE TABLE IF NOT EXISTS order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(30) DEFAULT 'card',
    card_brand VARCHAR(30) DEFAULT NULL,
    card_holder VARCHAR(100) DEFAULT NULL,
    card_last4 VARCHAR(4) DEFAULT NULL,
    card_exp_month INT DEFAULT NULL,
    card_exp_year INT DEFAULT NULL,
    -- NEVER store CVV (PCI-DSS forbids it). Cards are handled by Stripe; the
    -- CVV never reaches this server. Column intentionally removed.
    billing_zip VARCHAR(20) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(30) DEFAULT 'paid',
    payment_intent_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_order_payments_intent (payment_intent_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- =============================
-- 3. Order items - each product in an order
-- =============================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    size_name VARCHAR(50) DEFAULT NULL,
    color_name VARCHAR(50) DEFAULT NULL,
    color_hex VARCHAR(10) DEFAULT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    custom_design_fee DECIMAL(10,2) DEFAULT 0.00,
    is_custom_design TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- =============================
-- 4. Custom design data - stores the complete design layout
CREATE TABLE IF NOT EXISTS order_item_designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    design_data JSON NOT NULL COMMENT 'JSON containing all design elements with positions, sizes, views',
    preview_image_path VARCHAR(255) DEFAULT NULL COMMENT 'Path to a rendered preview image of the design',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

-- =============================
-- 5. Design element uploads - stores individual uploaded images
CREATE TABLE IF NOT EXISTS order_item_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    stored_file_path VARCHAR(255) NOT NULL COMMENT 'Path to the stored image file',
    placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 80,
    height INT DEFAULT 80,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

-- =============================
-- 6. Design text elements - stores text added by customers
CREATE TABLE IF NOT EXISTS order_item_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    text_content TEXT NOT NULL,
    font_family VARCHAR(100) DEFAULT 'Arial',
    font_size INT DEFAULT 24,
    text_color VARCHAR(10) DEFAULT '#000000',
    is_bold TINYINT(1) DEFAULT 0,
    is_italic TINYINT(1) DEFAULT 0,
    is_underline TINYINT(1) DEFAULT 0,
    placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

-- =============================
-- 7. Cart tables for storing designs before checkout (except carts)
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    size_name VARCHAR(50) DEFAULT NULL,
    color_name VARCHAR(50) DEFAULT NULL,
    color_hex VARCHAR(10) DEFAULT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    custom_design_fee DECIMAL(10,2) DEFAULT 0.00,
    is_custom_design TINYINT(1) DEFAULT 0,
    design_data JSON DEFAULT NULL COMMENT 'JSON of design elements before order',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- Cart item uploads (temporary storage before order)
CREATE TABLE IF NOT EXISTS cart_item_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_item_id INT NOT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    stored_file_path VARCHAR(255) NOT NULL,
    placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 80,
    height INT DEFAULT 80,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_item_id) REFERENCES cart_items(id) ON DELETE CASCADE
);
