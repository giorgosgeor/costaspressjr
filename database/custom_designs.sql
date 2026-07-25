CREATE TABLE IF NOT EXISTS custom_designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    size_id INT NOT NULL,
    color_id INT NOT NULL,
    elements_json TEXT NOT NULL,
    color_hex VARCHAR(16),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (size_id) REFERENCES product_sizes(id),
    FOREIGN KEY (color_id) REFERENCES available_colors(id)
);
