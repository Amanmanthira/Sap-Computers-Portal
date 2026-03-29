-- Add warranty period and purchase reference to warranty system

ALTER TABLE products 
ADD COLUMN warranty_months INT DEFAULT 12 AFTER model;

ALTER TABLE warranties
ADD COLUMN order_id INT NULL AFTER product_id,
ADD COLUMN purchase_date DATE NULL AFTER warranty_status,
ADD COLUMN warranty_expiry_date DATE NULL AFTER purchase_date,
ADD COLUMN verified_by INT NULL AFTER warranty_expiry_date,
ADD COLUMN verified_at TIMESTAMP NULL AFTER verified_by,
ADD FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL,
ADD FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_warranty_verification ON warranties(verified_by, verified_at);
CREATE INDEX idx_warranty_expiry ON warranties(warranty_expiry_date);
