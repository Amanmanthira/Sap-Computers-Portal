-- Migration 005: Parse existing POS customer data from notes field
-- This migration extracts customer name, phone, and payment method from the old concatenated format
-- and populates the newly added columns

-- First, extract customer_name from notes field where it starts with "Cust:"
UPDATE sales 
SET customer_name = TRIM(SUBSTRING_INDEX(SUBSTRING(notes, LOCATE('Cust:', notes) + 5), '|', 1))
WHERE notes LIKE '%Cust:%' AND customer_name IS NULL;

-- Extract customer_phone from notes field where it contains "Ph:"
UPDATE sales 
SET customer_phone = TRIM(SUBSTRING_INDEX(SUBSTRING(notes, LOCATE('Ph:', notes) + 3), '|', 1))
WHERE notes LIKE '%Ph:%' AND customer_phone IS NULL;

-- Extract payment_method from notes field where it contains "Pay:"
UPDATE sales 
SET payment_method = TRIM(SUBSTRING(notes, LOCATE('Pay:', notes) + 4))
WHERE notes LIKE '%Pay:%' AND payment_method IS NULL;

-- Handle cases where customer_name is still NULL but we have "POS Sale" or similar
UPDATE sales 
SET customer_name = 'POS Customer'
WHERE customer_name IS NULL AND sale_id IS NOT NULL;

-- Handle cases where customer_phone is still NULL or is "N/A"
UPDATE sales 
SET customer_phone = ''
WHERE (customer_phone IS NULL OR customer_phone = 'N/A' OR TRIM(customer_phone) = '') AND sale_id IS NOT NULL;

-- Handle cases where payment_method is still NULL
UPDATE sales 
SET payment_method = 'Cash'
WHERE payment_method IS NULL AND sale_id IS NOT NULL;

-- Verify the migration worked
SELECT 
  sale_id, 
  customer_name, 
  customer_phone, 
  payment_method, 
  notes
FROM sales
LIMIT 20;
