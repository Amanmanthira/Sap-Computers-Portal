CREATE DATABASE IF NOT EXISTS sap_computers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sap_computers;

-- ============================================================
-- TABLE: branches
-- ============================================================
CREATE TABLE IF NOT EXISTS branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    manager_name VARCHAR(100),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_branch_name (branch_name)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: suppliers
-- ============================================================
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(150) UNIQUE,
    address TEXT,
    company_registration VARCHAR(100),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: categories
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('seller','supplier','staff') NOT NULL DEFAULT 'seller',
    supplier_id INT NULL,
    branch_id INT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: products
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(200) NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    category_id INT NOT NULL,
    supplier_id INT NOT NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reorder_level INT NOT NULL DEFAULT 5,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    INDEX idx_brand (brand),
    INDEX idx_category (category_id),
    INDEX idx_supplier (supplier_id),
    FULLTEXT INDEX ft_product_search (product_name, brand, model)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: stock
-- ============================================================
CREATE TABLE IF NOT EXISTS stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    branch_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_branch (product_id, branch_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_branch (branch_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: grn (Goods Received Note)
-- ============================================================
CREATE TABLE IF NOT EXISTS grn (
    grn_id INT AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    branch_id INT NOT NULL,
    invoice_number VARCHAR(100),
    grn_date DATE NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_supplier (supplier_id),
    INDEX idx_branch (branch_id),
    INDEX idx_grn_date (grn_date)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: grn_items
-- ============================================================
CREATE TABLE IF NOT EXISTS grn_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grn_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    total_cost DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (grn_id) REFERENCES grn(grn_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    INDEX idx_grn (grn_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: inventory_movements
-- ============================================================
CREATE TABLE IF NOT EXISTS inventory_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    branch_id INT NOT NULL,
    type ENUM('GRN','Adjustment','Sale','Transfer') NOT NULL,
    quantity INT NOT NULL,
    reference_id INT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_product (product_id),
    INDEX idx_branch (branch_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: sales
-- ============================================================
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_branch (branch_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: sale_items
-- ============================================================
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: branch_orders
-- ============================================================
CREATE TABLE IF NOT EXISTS branch_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    created_by INT NOT NULL,
    status ENUM('pending','partial','completed','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_branch (branch_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: branch_order_items
-- ============================================================
CREATE TABLE IF NOT EXISTS branch_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    status ENUM('pending','accepted','rejected') DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES branch_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: branch_orders
-- ============================================================
CREATE TABLE IF NOT EXISTS branch_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    created_by INT NOT NULL,
    status ENUM('pending','partial','completed','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_branch (branch_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: branch_order_items
-- ============================================================
CREATE TABLE IF NOT EXISTS branch_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    status ENUM('pending','accepted','rejected') DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES branch_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO branches (branch_name, address, phone, manager_name) VALUES
('Gampaha (Main)', 'No. 45, Main Street, Gampaha', '0332 222 111', 'Kasun Perera'),
('Kiribathgoda', 'No. 12, Kandy Road, Kiribathgoda', '0112 905 432', 'Nimal Silva'),
('Negombo', 'No. 78, Lewis Place, Negombo', '0312 223 456', 'Suresh Fernando'),
('Colombo', 'No. 23, Galle Road, Colombo 03', '0112 345 678', 'Amali Jayawardena');

INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, company_registration) VALUES
('Singer Sri Lanka', 'Rohan Dias', '0112 000 100', 'orders@singer.lk', 'Singer Building, Colombo 03', 'PV/00001'),
('Dell Technologies', 'Sarah Johnson', '+1-800-915-3355', 'sri.lanka@dell.com', 'Dell Office, Colombo 07', 'PV/00002'),
('Lenovo Lanka', 'Priya Wickrama', '0112 678 900', 'lenovo.lk@lenovo.com', 'Lenovo Centre, Colombo 02', 'PV/00003'),
('HP Sri Lanka', 'David Chen', '0112 456 789', 'hpsrilanka@hp.com', 'HP Tower, Colombo 01', 'PV/00004'),
('ASUS Lanka', 'Nilufar Rashid', '0112 333 444', 'asus@asus.lk', 'ASUS Office, Colombo 06', 'PV/00005');

INSERT INTO categories (category_name, description) VALUES
('Laptop', 'Portable computers and notebooks'),
('Desktop', 'Desktop computers and workstations'),
('Monitor', 'Display screens and monitors'),
('Printer', 'Printers, scanners and multifunction devices'),
('Accessories', 'Keyboards, mice, headsets and other accessories'),
('Components', 'PC components: RAM, SSD, GPU, PSU etc.');

-- Admin user: password = Admin@123
INSERT INTO users (name, email, password, role, supplier_id, branch_id) VALUES
('SAP Admin', 'admin@sapcomputers.lk', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool3eMVQIKNxRy3RbJNEae', 'seller', NULL, NULL),
('Kasun Perera', 'kasun@sapcomputers.lk', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool3eMVQIKNxRy3RbJNEae', 'seller', NULL, NULL),
('Singer Portal', 'orders@singer.lk', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool3eMVQIKNxRy3RbJNEae', 'supplier', 1, NULL),
('Dell Portal', 'sri.lanka@dell.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool3eMVQIKNxRy3RbJNEae', 'supplier', 2, NULL),
('Branch Staff', 'staff1@sapcomputers.lk', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool3eMVQIKNxRy3RbJNEae', 'staff', NULL, 1);

INSERT INTO products (product_name, brand, model, category_id, supplier_id, cost_price, selling_price, reorder_level) VALUES
('Inspiron 15 3000', 'Dell', 'INS15-3520', 1, 2, 85000.00, 99500.00, 5),
('IdeaPad Slim 3', 'Lenovo', 'IPS3-15ABA7', 1, 3, 78000.00, 92000.00, 5),

-- sample sale (one item) 
INSERT INTO sales (sale_number, branch_id, total_amount, notes, created_by) VALUES
('SALE-2026-0001',1,99500.00,'Demo sale',1);
INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total_price) VALUES
(LAST_INSERT_ID(),1,1,99500.00,99500.00);

-- log movement for the sample sale
INSERT INTO inventory_movements (product_id,branch_id,type,quantity,reference_id,created_by) VALUES
(1,1,'Sale',1, (SELECT sale_id FROM sales WHERE sale_number='SALE-2026-0001'), 1);


('HP Pavilion 15', 'HP', 'PAV15-EH3', 1, 4, 92000.00, 108000.00, 3),
('VivoBook 15', 'ASUS', 'X1502ZA', 1, 5, 88000.00, 103000.00, 4),
('OptiPlex 3000', 'Dell', 'OPT3000-MT', 2, 2, 65000.00, 78000.00, 3),
('ThinkCentre M70s', 'Lenovo', 'M70S-GEN3', 2, 3, 70000.00, 84000.00, 3),
('HP EliteDesk 800', 'HP', 'EDS800-G9', 2, 4, 95000.00, 112000.00, 2),
('Dell 24 Monitor P2422H', 'Dell', 'P2422H', 3, 2, 28000.00, 34500.00, 5),
('HP V24i Monitor', 'HP', 'V24i-FHD', 3, 4, 22000.00, 27500.00, 5),
('HP LaserJet Pro M404n', 'HP', 'M404N', 4, 4, 35000.00, 42000.00, 3),
('HP Wireless Keyboard+Mouse', 'HP', 'CS10-COMBO', 5, 4, 3200.00, 4500.00, 10),
('Logitech MK235 Combo', 'Logitech', 'MK235', 5, 1, 2800.00, 3800.00, 10),
('Kingston 16GB DDR4', 'Kingston', 'KVR3200S8S6/16', 6, 1, 6500.00, 8500.00, 8),
('Samsung 512GB SSD', 'Samsung', '870EVO-512G', 6, 1, 12000.00, 15500.00, 8);

INSERT INTO stock (product_id, branch_id, quantity) VALUES
(1,1,12),(1,2,5),(1,3,3),(1,4,7),
(2,1,8),(2,2,4),(2,3,6),(2,4,2),
(3,1,6),(3,2,3),(3,3,2),(3,4,4),
(4,1,9),(4,2,5),(4,3,4),(4,4,3),
(5,1,4),(5,2,2),(5,3,3),(5,4,1),
(6,1,5),(6,2,3),(6,3,2),(6,4,2),
(7,1,3),(7,2,1),(7,3,2),(7,4,2),
(8,1,15),(8,2,8),(8,3,6),(8,4,9),
(9,1,10),(9,2,7),(9,3,5),(9,4,8),
(10,1,5),(10,2,2),(10,3,3),(10,4,2),
(11,1,20),(11,2,15),(11,3,12),(11,4,18),
(12,1,25),(12,2,18),(12,3,14),(12,4,20),
(13,1,30),(13,2,22),(13,3,18),(13,4,25),
(14,1,20),(14,2,15),(14,3,12),(14,4,18);

INSERT INTO grn (grn_number, supplier_id, branch_id, invoice_number, grn_date, total_amount, created_by) VALUES
('GRN-2024-001', 2, 1, 'DELL-INV-0012', '2024-01-10', 765000.00, 1),
('GRN-2024-002', 3, 1, 'LEN-INV-0025', '2024-01-22', 468000.00, 1),
('GRN-2024-003', 4, 2, 'HP-INV-0041', '2024-02-05', 387500.00, 2),
('GRN-2024-004', 1, 3, 'SGR-INV-0089', '2024-02-18', 215600.00, 1),
('GRN-2024-005', 5, 4, 'ASUS-INV-0015', '2024-03-07', 528000.00, 2),
('GRN-2024-006', 2, 1, 'DELL-INV-0033', '2024-03-20', 340000.00, 1);

INSERT INTO grn_items (grn_id, product_id, quantity, unit_cost, total_cost) VALUES
(1, 1, 5, 85000.00, 425000.00),(1, 8, 12, 28000.00, 336000.00),
(2, 2, 6, 78000.00, 468000.00),
(3, 3, 3, 92000.00, 276000.00),(3, 9, 5, 22000.00, 110000.00),(3, 11, 3, 3200.00, 9600.00),
(4, 12, 30, 2800.00, 84000.00),(4, 13, 20, 6500.00, 130000.00),
(5, 4, 6, 88000.00, 528000.00),
(6, 5, 4, 65000.00, 260000.00),(6, 8, 3, 28000.00, 84000.00);
