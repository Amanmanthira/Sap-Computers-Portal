-- Fix POS Sales to properly store customer details for warranty linking

ALTER TABLE sales
ADD COLUMN customer_name VARCHAR(100) NULL AFTER branch_id,
ADD COLUMN customer_phone VARCHAR(20) NULL AFTER customer_name,
ADD COLUMN payment_method VARCHAR(50) NULL AFTER customer_phone;

-- Create index for customer search in warranty system
CREATE INDEX idx_sales_customer_name ON sales(customer_name);
CREATE INDEX idx_sales_customer_phone ON sales(customer_phone);
CREATE INDEX idx_sales_created_at ON sales(created_at);
