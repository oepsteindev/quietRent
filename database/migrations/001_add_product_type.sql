-- Migration: Add product_type field to accounts table
-- This allows the same codebase to serve multiple product variants

ALTER TABLE accounts
ADD COLUMN product_type ENUM('landlords', 'dentists', 'agents')
NOT NULL DEFAULT 'landlords'
AFTER name;

-- Add index for product_type queries
CREATE INDEX idx_accounts_product_type ON accounts(product_type);
