-- Migration 007: Create returns management table
CREATE TABLE IF NOT EXISTS returns (
    return_id INT PRIMARY KEY AUTO_INCREMENT,
    return_number VARCHAR(20) UNIQUE NOT NULL,
    order_id INT NULL COMMENT 'For online orders',
    sale_id INT NULL COMMENT 'For POS sales',
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    return_reason VARCHAR(255),
    return_status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    notes LONGTEXT,
    return_date DATE,
    created_by INT,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Index for faster lookups
CREATE INDEX idx_return_status ON returns(return_status);
CREATE INDEX idx_return_date ON returns(return_date);
CREATE INDEX idx_product_id ON returns(product_id);
