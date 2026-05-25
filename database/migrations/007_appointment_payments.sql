-- Add payment tracking columns to appointments
ALTER TABLE appointments
  ADD COLUMN payment_status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid' AFTER status,
  ADD COLUMN paid_at DATETIME NULL AFTER payment_status;
