-- Migration: Create warranty management system
-- Date: 2026-03-25
-- Description: Create warranty, warranty_movements, and warranty_parts tables

-- Create warranties table
CREATE TABLE IF NOT EXISTS warranties (
    warranty_id INT AUTO_INCREMENT PRIMARY KEY,
    warranty_number VARCHAR(50) UNIQUE NOT NULL,
    product_id INT NOT NULL,
    serial_number VARCHAR(255),
    customer_name VARCHAR(150),
    customer_phone VARCHAR(20),
    customer_email VARCHAR(150),
    issue_description TEXT,
    warranty_status ENUM('pending','in-progress','completed','cancelled') DEFAULT 'pending',
    intake_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_completion DATE,
    completed_date DATETIME,
    notes TEXT,
    created_by INT NOT NULL,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_warranty_number (warranty_number),
    INDEX idx_serial_number (serial_number),
    INDEX idx_status (warranty_status),
    INDEX idx_intake_date (intake_date)
) ENGINE=InnoDB;

-- Create warranty_movements table
CREATE TABLE IF NOT EXISTS warranty_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    warranty_id INT NOT NULL,
    movement_type ENUM('intake','diagnosis','repair','waiting_parts','dispatch','completed','cancelled') NOT NULL,
    description TEXT,
    location VARCHAR(150),
    dispatch_method VARCHAR(100),
    tracking_number VARCHAR(100),
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warranty_id) REFERENCES warranties(warranty_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_warranty (warranty_id),
    INDEX idx_type (movement_type),
    INDEX idx_date (movement_date)
) ENGINE=InnoDB;

-- Create warranty_parts table
CREATE TABLE IF NOT EXISTS warranty_parts (
    part_id INT AUTO_INCREMENT PRIMARY KEY,
    warranty_id INT NOT NULL,
    product_id INT NOT NULL,
    part_name VARCHAR(200) NOT NULL,
    quantity INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warranty_id) REFERENCES warranties(warranty_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    INDEX idx_warranty (warranty_id)
) ENGINE=InnoDB;
