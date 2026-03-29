-- Migration: Add serial number support to GRN items
-- Date: 2026-03-25
-- Description: Add serial_number column to grn_items table to track individual serial numbers for each product

ALTER TABLE grn_items ADD COLUMN serial_number VARCHAR(255);
