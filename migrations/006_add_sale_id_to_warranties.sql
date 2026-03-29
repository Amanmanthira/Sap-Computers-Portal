-- Migration 006: Add sale_id column to warranties to handle both online orders and POS sales
-- Makes order_id nullable and adds sale_id to reference POS sales

ALTER TABLE warranties 
ADD COLUMN sale_id INT NULL AFTER order_id;

-- Add foreign key constraint for sale_id
ALTER TABLE warranties
ADD CONSTRAINT warranties_ibfk_sale FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE SET NULL;

-- Make order_id nullable since we'll now use either order_id OR sale_id
ALTER TABLE warranties 
MODIFY COLUMN order_id INT NULL;
